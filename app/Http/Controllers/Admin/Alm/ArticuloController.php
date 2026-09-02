<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\ArticuloStoreRequest;
use App\Http\Requests\Admin\Alm\ArticuloUpdateRequest;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Services\Alm\GeneradorCodigoArticulo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de Almacén: qué guarda la bodega y cómo se comporta.
 *
 * Vive en `alm_articulos`, su propia tabla, y se liga a `costos_productos` por
 * una referencia anulable. Cada lado manda sobre lo suyo — Compras sobre el
 * código y la descripción con los que cotiza, Almacén sobre el área, la
 * clasificación, el stock mínimo y la etiqueta que se imprime.
 *
 * **El alta crea las dos.** Quien da de alta un artículo aquí sabe que la
 * empresa compra eso, así que nace con su producto en Compras ya ligado. Los
 * artículos sueltos vienen por el otro camino: la carga inicial de un almacén,
 * que vuelca material que nadie revisó renglón por renglón y que se empareja
 * después.
 *
 * **La edición no toca a Compras.** Corregir aquí una descripción cambia la del
 * artículo, no la del producto: el día que difieran, eso es información para
 * quien empareja, y no un seeder cambiándole la unidad a algo que se está
 * cotizando.
 */
class ArticuloController extends Controller
{
    public function __construct(private readonly GeneradorCodigoArticulo $generador) {}

    public function index(Request $request): Response
    {
        $articulos = Articulo::query()
            ->with('area:id,descripcion')
            ->withSum('existencias as existencia_total', 'cantidad')
            ->when($request->string('search')->trim()->value(), $this->buscador(...))
            ->when($request->string('tipo')->value(), fn (Builder $q, string $t) => $q->where('tipo', $t))
            ->when($request->string('area_id')->value(), fn (Builder $q, string $a) => $q->where('area_id', $a))
            ->when($request->string('clase')->value(), fn (Builder $q, string $c) => $q->where('clasificacion_abc', $c))
            ->when($request->boolean('sin_ligar'), fn (Builder $q) => $q->sinLigar())
            ->orderBy('descripcion')
            ->paginate(25)
            ->withQueryString()
            ->through($this->fila(...));

        return Inertia::render('admin/almacen/articulos/index', [
            'articulos' => $articulos,
            'filters' => $request->only(['search', 'tipo', 'area_id', 'clase', 'sin_ligar']),
            // La bandeja de entrada, ahora al revés: artículos que la bodega
            // guarda y que todavía no se emparejan con nada de Compras. Salen de
            // la carga inicial de un almacén. Va como cuenta y no como filtro por
            // default porque no es un error, es trabajo pendiente.
            'sinLigar' => Articulo::query()->sinLigar()->count(),
            ...$this->opciones(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/almacen/articulos/create', [
            // Se muestra desde ahora para que quien da de alta sepa con qué va a
            // quedar etiquetado. Es una previsualización: el definitivo se
            // aparta al guardar, dentro de la transacción.
            'codigoSugerido' => $this->generador->siguiente(),
            ...$this->opciones(),
        ]);
    }

    /**
     * Da de alta las dos mitades: el producto con el que Compras va a poder
     * cotizarlo y el artículo con el que Almacén lo va a guardar, ligados.
     *
     * Van en una transacción porque un artículo sin su producto sería material
     * que nadie puede comprar, y un producto sin su artículo, algo que se compra
     * y no se puede recibir.
     */
    public function store(ArticuloStoreRequest $request): RedirectResponse
    {
        $datos = $request->safe()->except('imagen');
        $usuarioId = $request->user()?->getAuthIdentifier();
        $imagen = $this->guardarImagen($request);

        $articulo = DB::transaction(function () use ($datos, $usuarioId, $imagen): Articulo {
            $codigo = $this->generador->siguiente();

            // Compras se lleva lo que necesita para cotizar, y nada más.
            $producto = Producto::create([
                'codigo' => $codigo,
                'descripcion' => $datos['descripcion'],
                'unidad' => $datos['unidad'],
                'idsteelex' => $datos['idsteelex'] ?? null,
                'activo' => true,
                'creado_por' => $usuarioId,
            ]);

            return Articulo::create([
                ...$datos,
                'producto_id' => $producto->id,
                'codigo' => $codigo,
                // Nace igual al código: la etiqueta que se imprime es la nuestra
                // salvo que la caja ya traiga una de fábrica.
                'codigo_barras' => $datos['codigo_barras'] ?? $codigo,
                'imagen' => $imagen,
                'activo' => true,
                'creado_por' => $usuarioId,
            ]);
        });

        return to_route('admin.alm.articulos.show', $articulo);
    }

    public function show(Articulo $articulo): Response
    {
        $articulo->load(['area:id,descripcion', 'producto:id,codigo']);

        return Inertia::render('admin/almacen/articulos/show', [
            'articulo' => $this->fila($articulo->loadSum('existencias as existencia_total', 'cantidad')),
            'existencias' => $this->existenciasDe($articulo),
            'precios' => $this->preciosDe($articulo),
            // Los lugares donde se puede acomodar, por almacén. Van con la ficha
            // porque acomodar es lo único que se corrige desde aquí: quien está
            // guardando el material no debería tener que entrar a otra pantalla.
            'ubicaciones' => $this->ubicacionesDe($articulo),
        ]);
    }

    public function edit(Articulo $articulo): Response
    {
        return Inertia::render('admin/almacen/articulos/edit', [
            'articulo' => $this->fila($articulo->loadSum('existencias as existencia_total', 'cantidad')),
            ...$this->opciones(),
        ]);
    }

    /**
     * Sólo el artículo. Compras se queda como está aunque aquí se corrija la
     * descripción: si acaban diciendo cosas distintas, eso se ve y se resuelve
     * emparejando, que es mejor que Almacén reescribiendo en silencio el
     * renglón con el que se está cotizando.
     */
    public function update(ArticuloUpdateRequest $request, Articulo $articulo): RedirectResponse
    {
        $datos = $request->safe()->except('imagen');

        if ($request->hasFile('imagen')) {
            $anterior = $articulo->imagen;
            $datos['imagen'] = $this->guardarImagen($request);

            if ($anterior !== null) {
                Storage::disk('public')->delete($anterior);
            }
        }

        // El código de barras vacío vuelve al del artículo: quitar el del
        // fabricante debe dejar el nuestro, no dejar la etiqueta en blanco.
        $datos['codigo_barras'] = $datos['codigo_barras'] ?? $articulo->codigo;

        $articulo->update($datos);

        return to_route('admin.alm.articulos.show', $articulo);
    }

    /**
     * Se busca por lo que la gente tiene enfrente, no por lo que recuerda: quien
     * llega con la caja en la mano trae el código de barras, y quien viene del
     * sistema anterior trae el id de Steelex.
     *
     * La marca y el modelo no entran: son de la pieza, y de eso sabe Activos.
     *
     * @param  Builder<Articulo>  $query
     * @return Builder<Articulo>
     */
    private function buscador(Builder $query, string $termino): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('codigo', 'like', "%{$termino}%")
            ->orWhere('descripcion', 'like', "%{$termino}%")
            ->orWhere('codigo_barras', 'like', "%{$termino}%")
            ->orWhere('idsteelex', 'like', "%{$termino}%")
            ->orWhereHas('area', fn (Builder $a) => $a->where('descripcion', 'like', "%{$termino}%")));
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Articulo $articulo): array
    {
        return [
            'id' => $articulo->id,
            'codigo' => $articulo->codigo,
            'codigo_barras' => $articulo->codigo_barras,
            'descripcion' => $articulo->descripcion,
            'idsteelex' => $articulo->idsteelex,
            'area_id' => $articulo->area_id,
            'area' => $articulo->area?->descripcion,
            'unidad' => $articulo->unidad,
            'tipo' => $articulo->tipo?->value,
            'clasificacion_abc' => $articulo->clasificacion_abc?->value,
            // Con qué lo compra Compras. Null es material sin identidad de
            // compra todavía, y la pantalla lo marca como pendiente de ligar.
            'producto_id' => $articulo->producto_id,
            'se_controla_por_pieza' => $articulo->se_controla_por_pieza,
            'requiere_verificacion' => $articulo->requiere_verificacion,
            'stock_minimo' => $articulo->stock_minimo === null ? null : (float) $articulo->stock_minimo,
            'imagen_url' => $articulo->imagen === null ? null : Storage::disk('public')->url($articulo->imagen),
            'precio_ultimo' => $articulo->producto?->precios()->value('precio'),
            'existencia_total' => (float) ($articulo->existencia_total ?? 0),
        ];
    }

    /**
     * Dónde está y cuánto hay, almacén por almacén. Es la mitad de la ficha que
     * responde «¿lo tenemos?» sin tener que ir a Existencias y filtrar.
     *
     * @return list<array<string, mixed>>
     */
    private function existenciasDe(Articulo $articulo): array
    {
        return Existencia::query()
            ->where('articulo_id', $articulo->id)
            ->with(['almacen:id,clave,nombre,obra_id', 'almacen.obra:id,no', 'ubicacion'])
            ->conSaldo()
            ->get()
            ->map(fn (Existencia $e): array => [
                'id' => $e->id,
                'almacen_id' => $e->almacen_id,
                'almacen' => $e->almacen?->clave,
                'almacen_nombre' => $e->almacen?->nombre,
                'obra' => $e->almacen?->obra?->no,
                'cantidad' => (float) $e->cantidad,
                'costo_promedio' => (float) $e->costo_promedio,
                'ubicacion_id' => $e->ubicacion_id,
                'ubicacion' => $e->ubicacion?->ruta(),
            ])
            ->values()
            ->all();
    }

    /**
     * Las ubicaciones activas de cada almacén donde el artículo tiene saldo, con
     * su ruta ya armada.
     *
     * @return array<int, list<array{id: int, ruta: string}>>
     */
    private function ubicacionesDe(Articulo $articulo): array
    {
        $almacenes = Existencia::query()
            ->where('articulo_id', $articulo->id)
            ->conSaldo()
            ->pluck('almacen_id')
            ->unique();

        return Ubicacion::query()
            ->whereIn('almacen_id', $almacenes)
            ->activas()
            ->with('padre')
            ->orderBy('codigo')
            ->get()
            ->groupBy('almacen_id')
            ->map(fn ($grupo) => $grupo
                ->map(fn (Ubicacion $u): array => ['id' => $u->id, 'ruta' => $u->ruta()])
                ->values()
                ->all())
            ->all();
    }

    /**
     * El histórico de precios sale de las cotizaciones de Compras: aquí sólo se
     * consulta. Capturar un precio en Almacén sería inventar un segundo lugar
     * donde vive el mismo dato.
     *
     * Un artículo sin ligar no tiene precios que enseñar, y eso no es un vacío:
     * es material que todavía no se ha comprado por su nombre.
     *
     * @return list<array<string, mixed>>
     */
    private function preciosDe(Articulo $articulo): array
    {
        if ($articulo->producto_id === null) {
            return [];
        }

        return $articulo->producto->precios()
            ->with('proveedor:id,razon_social,nombre_comercial')
            ->limit(20)
            ->get()
            ->map(fn ($precio): array => [
                'id' => $precio->id,
                'fecha' => $precio->fecha?->toDateString(),
                'proveedor' => $precio->proveedor?->nombre_comercial ?: $precio->proveedor?->razon_social,
                'precio' => (float) $precio->precio,
                'moneda' => strtoupper((string) $precio->moneda),
                'requisicion_id' => $precio->requisicion_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Catálogos que comparten el alta, la edición y los filtros del índice. Si
     * cada pantalla trajera su propia lista, un artículo dado de alta en MTS
     * podría quedarse sin esa opción al corregirlo.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'areas' => Area::query()->activas()->orderBy('descripcion')->get(['id', 'descripcion', 'activo']),
            'unidades' => Producto::UNIDADES,
            'tipos' => array_map(
                fn (ProductoTipo $t): array => ['value' => $t->value, 'label' => $t->etiqueta()],
                ProductoTipo::cases(),
            ),
            'clases' => array_map(
                fn (ClasificacionAbc $c): array => [
                    'value' => $c->value,
                    'label' => $c->etiqueta(),
                    'frecuencia_dias' => $c->frecuenciaDias(),
                    'descripcion' => $c->descripcion(),
                ],
                ClasificacionAbc::cases(),
            ),
        ];
    }

    private function guardarImagen(Request $request): ?string
    {
        if (! $request->hasFile('imagen')) {
            return null;
        }

        return $request->file('imagen')->store('alm/articulos', 'public');
    }
}
