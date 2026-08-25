<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\ArticuloStoreRequest;
use App\Http\Requests\Admin\Alm\ArticuloUpdateRequest;
use App\Models\Alm\Area;
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
 * El catálogo de artículos, que es `costos_productos` visto desde Almacén.
 *
 * No es una tabla nueva: el mismo código sirve para cotizar y para llevar
 * existencias, y duplicarlo sería garantizar que los dos catálogos dejen de
 * empatar. Lo que Almacén administra aquí es cómo se comporta cada artículo —si
 * lleva kardex, si se sigue pieza por pieza, cada cuánto se cuenta— y su
 * identificación física: código de barras, área e imagen.
 */
class ArticuloController extends Controller
{
    public function __construct(private readonly GeneradorCodigoArticulo $generador) {}

    public function index(Request $request): Response
    {
        $articulos = Producto::query()
            ->with('area:id,descripcion')
            ->withSum('existencias as existencia_total', 'cantidad')
            ->when($request->string('search')->trim()->value(), $this->buscador(...))
            ->when($request->string('tipo')->value(), fn (Builder $q, string $t) => $q->where('tipo', $t))
            ->when($request->string('area_id')->value(), fn (Builder $q, string $a) => $q->where('area_id', $a))
            ->when($request->string('clase')->value(), fn (Builder $q, string $c) => $q->where('clasificacion_abc', $c))
            ->when($request->boolean('sin_clasificar'), fn (Builder $q) => $q->sinClasificar())
            ->orderBy('descripcion')
            ->paginate(25)
            ->withQueryString()
            ->through($this->fila(...));

        return Inertia::render('admin/almacen/articulos/index', [
            'articulos' => $articulos,
            'filters' => $request->only(['search', 'tipo', 'area_id', 'clase', 'sin_clasificar']),
            // La bandeja de entrada: lo que Compras tecleó al vuelo y todavía no
            // entra al kardex. Va como cuenta y no como filtro por default
            // porque no es un error, es trabajo pendiente.
            'sinClasificar' => Producto::query()->sinClasificar()->count(),
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

    public function store(ArticuloStoreRequest $request): RedirectResponse
    {
        $datos = $request->safe()->except('imagen');

        $producto = DB::transaction(function () use ($request, $datos): Producto {
            $codigo = $this->generador->siguiente();

            return Producto::create([
                ...$datos,
                'codigo' => $codigo,
                // Nace igual al código: la etiqueta que se imprime es la nuestra
                // salvo que la caja ya traiga una de fábrica.
                'codigo_barras' => $datos['codigo_barras'] ?? $codigo,
                'imagen' => $this->guardarImagen($request),
                'activo' => true,
                'creado_por' => $request->user()?->getAuthIdentifier(),
            ]);
        });

        return to_route('admin.alm.articulos.show', $producto);
    }

    public function show(Producto $articulo): Response
    {
        $articulo->load('area:id,descripcion');

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

    public function edit(Producto $articulo): Response
    {
        return Inertia::render('admin/almacen/articulos/edit', [
            'articulo' => $this->fila($articulo->loadSum('existencias as existencia_total', 'cantidad')),
            ...$this->opciones(),
        ]);
    }

    public function update(ArticuloUpdateRequest $request, Producto $articulo): RedirectResponse
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
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
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
    private function fila(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'codigo_barras' => $producto->codigo_barras,
            'descripcion' => $producto->descripcion,
            'idsteelex' => $producto->idsteelex,
            'area_id' => $producto->area_id,
            'area' => $producto->area?->descripcion,
            'unidad' => $producto->unidad,
            'tipo' => $producto->tipo?->value,
            'clasificacion_abc' => $producto->clasificacion_abc?->value,
            'controla_inventario' => $producto->controla_inventario,
            'se_controla_por_pieza' => $producto->se_controla_por_pieza,
            'requiere_verificacion' => $producto->requiere_verificacion,
            'stock_minimo' => $producto->stock_minimo === null ? null : (float) $producto->stock_minimo,
            'imagen_url' => $producto->imagen === null ? null : Storage::disk('public')->url($producto->imagen),
            'precio_ultimo' => $producto->precios()->value('precio'),
            'existencia_total' => (float) ($producto->existencia_total ?? 0),
        ];
    }

    /**
     * Dónde está y cuánto hay, almacén por almacén. Es la mitad de la ficha que
     * responde «¿lo tenemos?» sin tener que ir a Existencias y filtrar.
     *
     * @return list<array<string, mixed>>
     */
    private function existenciasDe(Producto $producto): array
    {
        return Existencia::query()
            ->where('producto_id', $producto->id)
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
    private function ubicacionesDe(Producto $producto): array
    {
        $almacenes = Existencia::query()
            ->where('producto_id', $producto->id)
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
     * @return list<array<string, mixed>>
     */
    private function preciosDe(Producto $producto): array
    {
        return $producto->precios()
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
            'unidades' => ['PZA', 'KG', 'LTS', 'MTS', 'PAR', 'CTO', 'SRV'],
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
