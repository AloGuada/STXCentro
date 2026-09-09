<?php

namespace App\Services\Alm;

use App\Models\Alm\Articulo;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Costos\Producto;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Junta en un solo artículo los renglones que son el mismo insumo.
 *
 * La carga inicial dio de alta "taladro magnético" una vez por almacén, y
 * Compras a veces otra más. El kardex, las piezas con serie y las partidas de
 * cada uno son reales y no se pierden: se reapuntan al sobreviviente. Lo que
 * desaparece es la identidad repetida, que se desactiva con una flecha
 * (`fusionado_en_id`) hacia la que se quedó.
 *
 * Es la **única** excepción autorizada a "todo lo que mueve saldo pasa por
 * `AlmacenLedger`". No es un movimiento: nada entra ni sale de ninguna bodega.
 * Es una fusión de identidades, y cuando dos renglones del mismo almacén se
 * vuelven uno, sus saldos se suman tal cual, sin asiento, porque un asiento
 * diría que hubo una entrada que nunca ocurrió. Sus kardex se vuelven uno
 * también, y la corrida de saldos se recalcula en orden de fecha para que se
 * lea como si siempre hubieran sido el mismo artículo y termine en el saldo
 * real. La comprobación aritmética del final es lo que garantiza que no se
 * creó ni se perdió nada.
 *
 * Se divide en `planear()` (sólo lee, y es lo que enseña el dry-run) y
 * `ejecutar()` (escribe, y espera correr dentro de una transacción del
 * llamador) para que ver qué pasaría y hacerlo sean el mismo código.
 *
 * Los grupos salen de un CSV revisado a mano o se arman solos
 * (`gruposAutomaticos()`): items activos con la misma descripción
 * normalizada, y se queda el código más bajo. Un grupo puede traer además
 * productos de Compras que no tienen cara de Almacén (`productos`): el
 * sobreviviente adopta uno y los demás se desactivan. Si el grupo es sólo de
 * productos, no hay artículo que sobreviva y la fusión es entre productos.
 *
 * @phpstan-type Grupo array{grupo: string, conservar: ?string, sobrantes: list<string>, productos: list<int>}
 * @phpstan-type Almacen array{almacen_id: int, nombre: string, antes: array<string, float>, cantidad: float, valor: float, fusiona: bool}
 * @phpstan-type Plan array{grupo: string, sobreviviente: ?Articulo, sobrantes: Collection<int, Articulo>, producto_id: ?int, productos_a_desactivar: list<int>, productos_sueltos: Collection<int, Producto>, almacenes: list<Almacen>}
 */
class FusionadorArticulos
{
    private const TABLAS_ALMACEN = [
        'alm_movimientos',
        'alm_ajuste_detalle',
        'alm_pedido_detalle',
        'alm_salida_detalle',
        'alm_transferencia_detalle',
        'alm_activos',
    ];

    private const TABLAS_COSTOS = [
        'costos_requisicion_detalle',
        'costos_ordenes_compra_detalle',
        'costos_entrega_detalle',
        'costos_producto_precios',
    ];

    private const EPSILON = 0.0001;

    /**
     * Lee el CSV de fusión. Columnas: grupo, codigo, descripcion, almacen,
     * stock, conservar, nota. Sólo `grupo`, `codigo` y `conservar` deciden algo;
     * las demás están para que quien revise el archivo sepa qué está mirando.
     *
     * Acepta el BOM que deja Excel, porque el archivo se edita ahí.
     *
     * @return list<Grupo>
     */
    public function gruposDesdeCsv(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new InvalidArgumentException("No se puede leer el archivo {$ruta}.");
        }

        $handle = fopen($ruta, 'r');
        $encabezado = fgetcsv($handle);

        if ($encabezado === false) {
            throw new InvalidArgumentException('El CSV está vacío.');
        }

        $encabezado = array_map(
            fn (string $c): string => strtolower(trim(str_replace("\xEF\xBB\xBF", '', $c))),
            $encabezado,
        );

        foreach (['grupo', 'codigo', 'conservar'] as $requerida) {
            if (! in_array($requerida, $encabezado, true)) {
                throw new InvalidArgumentException("Al CSV le falta la columna \"{$requerida}\".");
            }
        }

        $grupos = [];

        while (($fila = fgetcsv($handle)) !== false) {
            if (count(array_filter($fila, fn ($v): bool => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $fila = array_combine($encabezado, array_pad($fila, count($encabezado), ''));
            $grupo = trim((string) $fila['grupo']);
            $codigo = strtoupper(trim((string) $fila['codigo']));
            $conservar = strtoupper(trim((string) $fila['conservar'])) === 'SI';

            if ($grupo === '' || $codigo === '') {
                throw new InvalidArgumentException('Hay un renglón sin grupo o sin código.');
            }

            $grupos[$grupo] ??= ['grupo' => $grupo, 'conservar' => null, 'sobrantes' => [], 'productos' => []];

            if ($conservar) {
                if ($grupos[$grupo]['conservar'] !== null) {
                    throw new InvalidArgumentException("El grupo \"{$grupo}\" tiene más de un renglón marcado con SI.");
                }

                $grupos[$grupo]['conservar'] = $codigo;
            } else {
                $grupos[$grupo]['sobrantes'][] = $codigo;
            }
        }

        fclose($handle);

        foreach ($grupos as $grupo) {
            if ($grupo['conservar'] === null) {
                throw new InvalidArgumentException("El grupo \"{$grupo['grupo']}\" no tiene ningún renglón marcado con SI.");
            }

            if ($grupo['sobrantes'] === []) {
                throw new InvalidArgumentException("El grupo \"{$grupo['grupo']}\" sólo tiene al sobreviviente; no hay nada que fusionar.");
            }
        }

        return array_values($grupos);
    }

    /**
     * Arma los grupos solo: cada descripción normalizada que tenga más de un
     * item activo es un grupo, y en él se queda el artículo de código más
     * bajo. Los items del grupo que sólo tienen cara de Compras entran como
     * `productos`, para que el sobreviviente los adopte o los desactive. Si
     * ningún item del grupo tiene artículo, el grupo es sólo de productos y
     * `conservar` va nulo.
     *
     * Es exactamente lo que la migración del unique de descripción cuenta como
     * repetido, así que después de fusionar con estos grupos esa migración
     * pasa.
     *
     * @return list<Grupo>
     */
    public function gruposAutomaticos(): array
    {
        $repetidas = Item::query()
            ->where('activo', true)
            ->select('descripcion_normalizada')
            ->groupBy('descripcion_normalizada')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('descripcion_normalizada')
            ->pluck('descripcion_normalizada');

        if ($repetidas->isEmpty()) {
            return [];
        }

        $items = Item::query()
            ->where('activo', true)
            ->whereIn('descripcion_normalizada', $repetidas->all())
            ->with([
                'articulo' => fn ($q) => $q->where('activo', true),
                'producto' => fn ($q) => $q->where('activo', true),
            ])
            ->get()
            ->groupBy('descripcion_normalizada');

        $grupos = [];

        foreach ($repetidas as $descripcion) {
            /** @var Collection<int, Item> $delGrupo */
            $delGrupo = $items->get($descripcion, collect());

            $articulos = $delGrupo
                ->map(fn (Item $i): ?Articulo => $i->articulo)
                ->filter()
                ->sortBy(fn (Articulo $a): string => (string) $a->codigo)
                ->values();

            $productosSueltos = $delGrupo
                ->filter(fn (Item $i): bool => $i->articulo === null && $i->producto !== null)
                ->map(fn (Item $i): int => (int) $i->producto->id)
                ->values()
                ->all();

            $grupos[] = [
                'grupo' => $descripcion,
                'conservar' => $articulos->first()?->codigo === null ? null : strtoupper((string) $articulos->first()->codigo),
                'sobrantes' => $articulos->slice(1)->map(fn (Articulo $a): string => strtoupper((string) $a->codigo))->values()->all(),
                'productos' => $productosSueltos,
            ];
        }

        return $grupos;
    }

    /**
     * Valida y calcula, sin escribir. Todo lo que puede reventar a la mitad se
     * detecta aquí, antes de tocar un solo renglón: códigos que no existen,
     * artículos repetidos entre grupos y series que chocarían.
     *
     * @param  list<Grupo>  $grupos
     * @return list<Plan>
     */
    public function planear(array $grupos): array
    {
        $codigos = collect($grupos)
            ->flatMap(fn (array $g): array => array_filter([$g['conservar'], ...$g['sobrantes']]));

        $repetidos = $codigos->duplicates();

        if ($repetidos->isNotEmpty()) {
            throw new InvalidArgumentException('Estos códigos aparecen más de una vez en los grupos: '.$repetidos->unique()->implode(', ').'.');
        }

        $articulos = Articulo::query()
            ->whereIn('codigo', $codigos->all())
            ->get()
            ->keyBy(fn (Articulo $a): string => strtoupper((string) $a->codigo));

        $faltantes = $codigos->reject(fn (string $c): bool => $articulos->has($c));

        if ($faltantes->isNotEmpty()) {
            throw new InvalidArgumentException('Estos códigos no existen en alm_articulos: '.$faltantes->implode(', ').'.');
        }

        $idsSueltos = collect($grupos)->flatMap(fn (array $g): array => $g['productos'] ?? []);

        if ($idsSueltos->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException('Estos productos aparecen en más de un grupo: #'.$idsSueltos->duplicates()->unique()->implode(', #').'.');
        }

        $sueltos = Producto::query()->whereIn('id', $idsSueltos->all())->get()->keyBy('id');

        $planes = [];

        foreach ($grupos as $grupo) {
            $productosSueltos = collect($grupo['productos'] ?? [])
                ->map(fn (int $id): Producto => $sueltos->get($id) ?? throw new InvalidArgumentException("Grupo \"{$grupo['grupo']}\": el producto #{$id} no existe."))
                ->values();

            $this->validarSueltos($grupo['grupo'], $productosSueltos);

            if ($grupo['conservar'] === null) {
                $planes[] = $this->planearProductos($grupo['grupo'], $productosSueltos);

                continue;
            }

            $sobreviviente = $articulos->get($grupo['conservar']);
            $sobrantes = collect($grupo['sobrantes'])->map(fn (string $c): Articulo => $articulos->get($c))->values();

            if ($sobrantes->isEmpty() && $productosSueltos->isEmpty()) {
                throw new InvalidArgumentException("Grupo \"{$grupo['grupo']}\": sólo tiene al sobreviviente; no hay nada que fusionar.");
            }

            $this->validarSeries($grupo['grupo'], $sobreviviente, $sobrantes);

            $candidatos = $this->productosCandidatos($sobrantes, $productosSueltos);

            $productoId = $sobreviviente->producto_id === null
                ? $candidatos->first()?->id
                : (int) $sobreviviente->producto_id;

            $productosADesactivar = $candidatos
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->reject(fn (int $id): bool => $id === (int) $productoId)
                ->unique()
                ->values()
                ->all();

            $planes[] = [
                'grupo' => $grupo['grupo'],
                'sobreviviente' => $sobreviviente,
                'sobrantes' => $sobrantes,
                'producto_id' => $productoId === null ? null : (int) $productoId,
                'productos_a_desactivar' => $productosADesactivar,
                'productos_sueltos' => $productosSueltos,
                'almacenes' => $this->almacenesDe($sobreviviente, $sobrantes),
            ];
        }

        return $planes;
    }

    /**
     * Los productos que el grupo trae para el sobreviviente, del código más
     * bajo al más alto: los de los artículos sobrantes y los sueltos. El primero
     * es el que se adopta cuando el sobreviviente no tenía.
     *
     * @param  Collection<int, Articulo>  $sobrantes
     * @param  Collection<int, Producto>  $sueltos
     * @return Collection<int, Producto>
     */
    private function productosCandidatos(Collection $sobrantes, Collection $sueltos): Collection
    {
        $idsDeSobrantes = $sobrantes->pluck('producto_id')->filter()->map(fn ($id): int => (int) $id)->all();

        $deSobrantes = $idsDeSobrantes === []
            ? collect()
            : Producto::query()->whereIn('id', $idsDeSobrantes)->get();

        return $deSobrantes
            ->merge($sueltos)
            ->unique('id')
            ->sortBy(fn (Producto $p): string => (string) $p->codigo)
            ->values();
    }

    /**
     * Un grupo sin ningún artículo: se queda el producto de código más bajo y
     * los demás se le reapuntan y se desactivan. No hay saldo que cuadrar
     * porque, sin artículo, ninguno tiene existencias.
     *
     * @param  Collection<int, Producto>  $productos
     * @return Plan
     */
    private function planearProductos(string $grupo, Collection $productos): array
    {
        if ($productos->count() < 2) {
            throw new InvalidArgumentException("Grupo \"{$grupo}\": sin artículo y con menos de dos productos; no hay nada que fusionar.");
        }

        $ordenados = $productos->sortBy(fn (Producto $p): string => (string) $p->codigo)->values();
        $sobreviviente = $ordenados->first();

        return [
            'grupo' => $grupo,
            'sobreviviente' => null,
            'sobrantes' => collect(),
            'producto_id' => (int) $sobreviviente->id,
            'productos_a_desactivar' => $ordenados->slice(1)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'productos_sueltos' => $ordenados,
            'almacenes' => [],
        ];
    }

    /**
     * Un producto "suelto" tiene que serlo de verdad: sin artículo (activo o
     * no) que lo reclame, porque el unique de `alm_articulos(producto_id)` no
     * dejaría que el sobreviviente lo adoptara; y sin renglón de Almacén que lo
     * mencione, porque entonces sí habría saldo que cuadrar y ése es trabajo
     * de la fusión por artículo.
     *
     * @param  Collection<int, Producto>  $productos
     */
    private function validarSueltos(string $grupo, Collection $productos): void
    {
        if ($productos->isEmpty()) {
            return;
        }

        $ids = $productos->pluck('id')->all();

        $conArticulo = Articulo::query()->whereIn('producto_id', $ids)->pluck('codigo');

        if ($conArticulo->isNotEmpty()) {
            throw new InvalidArgumentException(sprintf(
                'Grupo "%s": los productos sueltos tienen artículo (%s); fusiónalos por artículo.',
                $grupo,
                $conArticulo->implode(', '),
            ));
        }

        foreach ([...self::TABLAS_ALMACEN, 'alm_existencias'] as $tabla) {
            if (DB::table($tabla)->whereIn('producto_id', $ids)->exists()) {
                throw new InvalidArgumentException("Grupo \"{$grupo}\": un producto suelto tiene renglones en {$tabla}; no se puede fusionar sin artículo.");
            }
        }
    }

    /**
     * Escribe la fusión de cada plan. **No abre transacción**: el llamador la
     * abre alrededor de todos los grupos, para que un error en el último
     * revierta también los primeros.
     *
     * @param  list<Plan>  $planes
     * @return array{articulos_desactivados: int, productos_desactivados: int, existencias_sumadas: int}
     */
    public function ejecutar(array $planes): array
    {
        $resumen = ['articulos_desactivados' => 0, 'productos_desactivados' => 0, 'existencias_sumadas' => 0];

        foreach ($planes as $plan) {
            if ($plan['sobreviviente'] === null) {
                $this->fusionarProductos($plan);
                $resumen['productos_desactivados'] += count($plan['productos_a_desactivar']);

                continue;
            }

            $antes = $this->totalesDe($plan);

            $resumen['existencias_sumadas'] += $this->fusionarGrupo($plan);
            $resumen['articulos_desactivados'] += $plan['sobrantes']->count();
            $resumen['productos_desactivados'] += count($plan['productos_a_desactivar']);

            $this->comprobar($plan, $antes);
        }

        return $resumen;
    }

    /**
     * Fusión entre productos de Compras sin cara de Almacén: las partidas y el
     * histórico de precios de los que sobran pasan al que se queda, los
     * sobrantes se desactivan con su flecha y sus items también. Al final no
     * puede quedar renglón de Compras apuntando a un sobrante.
     *
     * @param  Plan  $plan
     */
    private function fusionarProductos(array $plan): void
    {
        $productoId = $plan['producto_id'];
        $sobrantes = $plan['productos_a_desactivar'];

        foreach (self::TABLAS_COSTOS as $tabla) {
            DB::table($tabla)->whereIn('producto_id', $sobrantes)->update(['producto_id' => $productoId]);
        }

        Producto::query()->whereIn('id', $sobrantes)->update([
            'activo' => false,
            'fusionado_en_id' => $productoId,
        ]);

        $itemSobreviviente = (int) Producto::query()->whereKey($productoId)->value('item_id');

        $huerfanos = Producto::query()
            ->whereIn('id', $sobrantes)
            ->pluck('item_id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $itemSobreviviente)
            ->unique()
            ->values();

        if ($huerfanos->isNotEmpty()) {
            Item::query()->whereIn('id', $huerfanos)->update(['activo' => false]);
        }

        foreach (self::TABLAS_COSTOS as $tabla) {
            $quedan = DB::table($tabla)->whereIn('producto_id', $sobrantes)->count();

            if ($quedan > 0) {
                throw new RuntimeException("Grupo \"{$plan['grupo']}\": quedaron {$quedan} renglones de {$tabla} apuntando a un producto sobrante. Se revierte todo.");
            }
        }
    }

    /**
     * @param  Plan  $plan
     * @return int cuántas existencias se sumaron con otra del mismo almacén
     */
    private function fusionarGrupo(array $plan): int
    {
        /** @var Articulo $sobreviviente */
        $sobreviviente = $plan['sobreviviente'];
        /** @var Collection<int, Articulo> $sobrantes */
        $sobrantes = $plan['sobrantes'];
        $productoId = $plan['producto_id'];

        $idsSobrantes = $sobrantes->pluck('id')->map(fn ($id): int => (int) $id)->all();

        // Primero se suelta el producto de los sobrantes: el unique parcial de
        // alm_articulos(producto_id) no deja que dos artículos lo compartan ni
        // por un instante, y el sobreviviente puede estar por adoptarlo.
        Articulo::query()->whereIn('id', $idsSobrantes)->update(['producto_id' => null]);

        if ($sobreviviente->producto_id === null && $productoId !== null) {
            $sobreviviente->forceFill(['producto_id' => $productoId])->save();
        }

        // Compras: las partidas y el histórico de precios de los productos que
        // sobran pasan al producto que se queda.
        if ($plan['productos_a_desactivar'] !== []) {
            foreach (self::TABLAS_COSTOS as $tabla) {
                DB::table($tabla)
                    ->whereIn('producto_id', $plan['productos_a_desactivar'])
                    ->update(['producto_id' => $productoId]);
            }
        }

        $existenciasSumadas = $this->fusionarExistencias($sobreviviente, $idsSobrantes, $productoId);
        $sumadas = count($existenciasSumadas);

        // El resto de Almacén: kardex, detalles de documentos y piezas. Van
        // después de las existencias porque las que se sumaron ya cambiaron
        // de `existencia_id` ahí.
        foreach (self::TABLAS_ALMACEN as $tabla) {
            $consulta = DB::table($tabla)->whereIn('articulo_id', $idsSobrantes);

            if ($plan['productos_a_desactivar'] !== []) {
                $consulta->orWhereIn('producto_id', $plan['productos_a_desactivar']);
            }

            $consulta->update(['articulo_id' => $sobreviviente->id, 'producto_id' => $productoId]);
        }

        // Lo que ya era del sobreviviente también tiene que decir el producto
        // que acaba de adoptar. El ledger sigue abriendo la existencia por
        // producto cuando la recepción viene de una orden (`bloquear()`), y
        // un renglón del sobreviviente con `producto_id` nulo no lo encontraría:
        // intentaría crear otro y chocaría con el unique por artículo. Va
        // después de las existencias porque, mientras convivían con las del
        // sobrante en el mismo almacén, ese producto ya estaba ocupado ahí.
        if ($productoId !== null) {
            foreach ([...self::TABLAS_ALMACEN, 'alm_existencias'] as $tabla) {
                DB::table($tabla)
                    ->where('articulo_id', $sobreviviente->id)
                    ->whereNull('producto_id')
                    ->update(['producto_id' => $productoId]);
            }
        }

        foreach ($existenciasSumadas as $existenciaId) {
            $this->recalcularCorrida($existenciaId);
        }

        Articulo::query()->whereIn('id', $idsSobrantes)->update([
            'activo' => false,
            'fusionado_en_id' => $sobreviviente->id,
        ]);

        if ($plan['productos_a_desactivar'] !== []) {
            Producto::query()->whereIn('id', $plan['productos_a_desactivar'])->update([
                'activo' => false,
                'fusionado_en_id' => $productoId,
            ]);
        }

        $this->fusionarItems($sobreviviente, $idsSobrantes, $productoId, $plan['productos_a_desactivar']);

        return $sumadas;
    }

    /**
     * El maestro después de la fusión: un solo item activo por grupo, el del
     * sobreviviente, con las dos caras colgando de él.
     *
     * El producto que el sobreviviente adoptó de un sobrante venía con el item
     * del sobrante; se muda al del sobreviviente (que no tenía producto, si no
     * no habría adoptado). Los items que se quedan sin cara activa —los de los
     * artículos y productos que sobraron, y el que soltó el producto adoptado—
     * se desactivan, que es lo que el índice único del maestro necesita para
     * dejar de verlos como repetidos. No se borran: las caras desactivadas
     * siguen apuntando a ellos.
     *
     * @param  list<int>  $idsSobrantes
     * @param  list<int>  $productosADesactivar
     */
    private function fusionarItems(Articulo $sobreviviente, array $idsSobrantes, ?int $productoId, array $productosADesactivar): void
    {
        $itemSobreviviente = (int) $sobreviviente->item_id;
        $itemsHuerfanos = Articulo::query()->whereIn('id', $idsSobrantes)->pluck('item_id');

        if ($productoId !== null) {
            $producto = Producto::query()->findOrFail($productoId);

            if ((int) $producto->item_id !== $itemSobreviviente) {
                $itemsHuerfanos->push($producto->item_id);
                $producto->forceFill(['item_id' => $itemSobreviviente])->saveQuietly();
            }
        }

        if ($productosADesactivar !== []) {
            $itemsHuerfanos = $itemsHuerfanos->merge(
                Producto::query()->whereIn('id', $productosADesactivar)->pluck('item_id'),
            );
        }

        $ids = $itemsHuerfanos
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $itemSobreviviente)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            Item::query()->whereIn('id', $ids)->update(['activo' => false]);
        }
    }

    /**
     * Un almacén donde sólo un sobrante tiene existencia: el renglón cambia de
     * dueño y ya. Donde el sobreviviente ya tiene la suya, se suman cantidad y
     * valor, el costo promedio se recalcula del cociente, la partición por obra
     * se consolida obra por obra y el kardex del sobrante se cuelga del renglón
     * que queda —con una nota de qué código venía— antes de borrar el suyo.
     *
     * Se escribe con `forceFill` a propósito: es la excepción documentada
     * arriba, no un atajo.
     *
     * @param  list<int>  $idsSobrantes
     * @return list<int> ids de las existencias del sobreviviente que recibieron el saldo de otra
     */
    private function fusionarExistencias(Articulo $sobreviviente, array $idsSobrantes, ?int $productoId): array
    {
        $receptoras = [];

        $existenciasSobrantes = Existencia::query()
            ->with('articulo:id,codigo')
            ->whereIn('articulo_id', $idsSobrantes)
            ->orderBy('id')
            ->get();

        foreach ($existenciasSobrantes as $existenciaSobrante) {
            $existenciaS = Existencia::query()
                ->where('almacen_id', $existenciaSobrante->almacen_id)
                ->where('articulo_id', $sobreviviente->id)
                ->first();

            if ($existenciaS === null) {
                $existenciaSobrante->forceFill([
                    'articulo_id' => $sobreviviente->id,
                    'producto_id' => $productoId,
                ])->save();

                continue;
            }

            $cantidad = (float) $existenciaS->cantidad + (float) $existenciaSobrante->cantidad;
            $valor = (float) $existenciaS->valor + (float) $existenciaSobrante->valor;

            $existenciaS->forceFill([
                'cantidad' => $cantidad,
                'valor' => $valor,
                'costo_promedio' => abs($cantidad) < self::EPSILON ? 0 : $valor / $cantidad,
                'ultimo_movimiento_at' => max($existenciaS->ultimo_movimiento_at, $existenciaSobrante->ultimo_movimiento_at),
                // Dónde está guardado no es saldo, pero tampoco se tira: si el
                // renglón que se queda no tenía acomodo, hereda el del que se va.
                'ubicacion_id' => $existenciaS->ubicacion_id ?? $existenciaSobrante->ubicacion_id,
            ])->save();

            foreach ($existenciaSobrante->asignaciones()->get() as $asignacion) {
                $destino = Asignacion::firstOrCreate([
                    'existencia_id' => $existenciaS->id,
                    'obra_id' => $asignacion->obra_id,
                ]);

                $destino->forceFill([
                    'cantidad' => (float) $destino->cantidad + (float) $asignacion->cantidad,
                ])->save();

                $asignacion->delete();
            }

            $this->colgarKardex($existenciaSobrante, $existenciaS);

            $existenciaSobrante->delete();
            $receptoras[] = (int) $existenciaS->id;
        }

        return array_values(array_unique($receptoras));
    }

    /**
     * Los movimientos del sobrante pasan al renglón que se queda, cada uno con
     * una nota de qué código venía: la corrida se recalcula después y el
     * `saldo_despues` original deja de leerse, así que la nota es lo que
     * conserva la procedencia.
     */
    private function colgarKardex(Existencia $origen, Existencia $destino): void
    {
        $nota = sprintf('Era %s (fusionado).', $origen->articulo?->codigo ?? ('existencia #'.$origen->id));

        $movimientos = DB::table('alm_movimientos')
            ->where('existencia_id', $origen->id)
            ->get(['id', 'observaciones']);

        foreach ($movimientos as $movimiento) {
            $observaciones = trim((string) $movimiento->observaciones);

            DB::table('alm_movimientos')->where('id', $movimiento->id)->update([
                'existencia_id' => $destino->id,
                'observaciones' => $observaciones === '' ? $nota : $observaciones.' · '.$nota,
            ]);
        }
    }

    /**
     * Vuelve a correr el saldo del kardex de una existencia en orden de fecha,
     * como si sus movimientos siempre hubieran sido de un solo artículo. El
     * punto de partida es el saldo que la cadena no explica (existencia menos
     * la suma de sus movimientos: cero en una cadena limpia, el saldo inicial
     * en una que nació con carga), de modo que el último `saldo_despues` cae
     * exactamente en la existencia de hoy. Sólo se tocan `saldo_antes` y
     * `saldo_despues`; el costo de cada renglón es histórico y se respeta.
     */
    private function recalcularCorrida(int $existenciaId): void
    {
        $cantidadHoy = (float) DB::table('alm_existencias')->where('id', $existenciaId)->value('cantidad');

        $movimientos = DB::table('alm_movimientos')
            ->where('existencia_id', $existenciaId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'cantidad', 'saldo_antes', 'saldo_despues']);

        if ($movimientos->isEmpty()) {
            return;
        }

        $corrida = $cantidadHoy - (float) $movimientos->sum(fn ($m): float => (float) $m->cantidad);

        foreach ($movimientos as $movimiento) {
            $antes = $corrida;
            $corrida += (float) $movimiento->cantidad;

            if (abs((float) $movimiento->saldo_antes - $antes) < self::EPSILON && abs((float) $movimiento->saldo_despues - $corrida) < self::EPSILON) {
                continue;
            }

            DB::table('alm_movimientos')->where('id', $movimiento->id)->update([
                'saldo_antes' => round($antes, 4),
                'saldo_despues' => round($corrida, 4),
            ]);
        }
    }

    /**
     * La puerta: ni una pieza ni un peso de más o de menos en el grupo, **en
     * ningún almacén ni en ninguna obra**. Es aritmética, no criterio; si no
     * da, la excepción revierte la transacción del llamador.
     *
     * Se compara almacén por almacén y asignación por asignación, no el total
     * del grupo: dos errores de signo contrario se cancelarían en la suma
     * global y dejarían la existencia mal repartida sin que nadie lo viera.
     * Además, después de fusionar no puede quedar renglón de ningún sobrante,
     * el sobreviviente tiene a lo más uno por almacén y el kardex de cada uno
     * de sus renglones termina en el saldo de hoy.
     *
     * @param  Plan  $plan
     * @param  Foto  $antes
     */
    private function comprobar(array $plan, array $antes): void
    {
        $despues = $this->totalesDe($plan);
        $grupo = $plan['grupo'];

        foreach (['almacenes' => 'almacén', 'asignaciones' => 'asignación almacén/obra'] as $nivel => $etiqueta) {
            $claves = array_unique([...array_keys($antes[$nivel]), ...array_keys($despues[$nivel])]);

            foreach ($claves as $clave) {
                $a = $antes[$nivel][$clave] ?? ['cantidad' => 0.0, 'valor' => 0.0];
                $d = $despues[$nivel][$clave] ?? ['cantidad' => 0.0, 'valor' => 0.0];

                if (abs($a['cantidad'] - $d['cantidad']) > self::EPSILON || abs($a['valor'] - $d['valor']) > self::EPSILON) {
                    throw new RuntimeException(sprintf(
                        'Grupo "%s": el saldo no cuadra tras la fusión en %s %s (cantidad %.4f → %.4f, valor %.4f → %.4f). Se revierte todo.',
                        $grupo, $etiqueta, $clave, $a['cantidad'], $d['cantidad'], $a['valor'], $d['valor'],
                    ));
                }
            }
        }

        $idsSobrantes = $plan['sobrantes']->pluck('id')->all();
        $huerfanas = DB::table('alm_existencias')->whereIn('articulo_id', $idsSobrantes)->count();

        if ($huerfanas > 0) {
            throw new RuntimeException("Grupo \"{$grupo}\": quedaron {$huerfanas} existencias apuntando a un sobrante. Se revierte todo.");
        }

        // Con `select`, no `count()`: el count envuelve el group by en una
        // subconsulta `select *` que PostgreSQL rechaza (SQLite la deja pasar).
        $repetidas = DB::table('alm_existencias')
            ->select('almacen_id')
            ->where('articulo_id', $plan['sobreviviente']->id)
            ->groupBy('almacen_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($repetidas > 0) {
            throw new RuntimeException("Grupo \"{$grupo}\": el sobreviviente quedó con más de una existencia en {$repetidas} almacén(es). Se revierte todo.");
        }

        $existencias = DB::table('alm_existencias')
            ->where('articulo_id', $plan['sobreviviente']->id)
            ->get(['id', 'cantidad']);

        foreach ($existencias as $existencia) {
            $ultimo = DB::table('alm_movimientos')
                ->where('existencia_id', $existencia->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('saldo_despues');

            if ($ultimo !== null && abs((float) $ultimo - (float) $existencia->cantidad) > self::EPSILON) {
                throw new RuntimeException(sprintf(
                    'Grupo "%s": el kardex de la existencia #%d termina en %.4f y la existencia dice %.4f. Se revierte todo.',
                    $grupo, $existencia->id, (float) $ultimo, (float) $existencia->cantidad,
                ));
            }
        }
    }

    /**
     * La foto del grupo: cantidad y valor por almacén, y cantidad asignada
     * por (almacén, obra). El valor de la asignación no existe como columna;
     * se lleva en cero para que las dos tablas se comparen con el mismo
     * código.
     *
     * @phpstan-type Foto array{almacenes: array<int, array{cantidad: float, valor: float}>, asignaciones: array<string, array{cantidad: float, valor: float}>}
     *
     * @param  Plan  $plan
     * @return Foto
     */
    private function totalesDe(array $plan): array
    {
        $ids = [$plan['sobreviviente']->id, ...$plan['sobrantes']->pluck('id')->all()];

        $almacenes = DB::table('alm_existencias')
            ->whereIn('articulo_id', $ids)
            ->groupBy('almacen_id')
            ->selectRaw('almacen_id, COALESCE(SUM(cantidad), 0) AS cantidad, COALESCE(SUM(valor), 0) AS valor')
            ->get()
            ->mapWithKeys(fn ($f): array => [(int) $f->almacen_id => ['cantidad' => (float) $f->cantidad, 'valor' => (float) $f->valor]])
            ->all();

        $asignaciones = DB::table('alm_asignaciones as s')
            ->join('alm_existencias as e', 'e.id', '=', 's.existencia_id')
            ->whereIn('e.articulo_id', $ids)
            ->groupBy('e.almacen_id', 's.obra_id')
            ->selectRaw('e.almacen_id, s.obra_id, COALESCE(SUM(s.cantidad), 0) AS cantidad')
            ->get()
            ->mapWithKeys(fn ($f): array => ["{$f->almacen_id}/{$f->obra_id}" => ['cantidad' => (float) $f->cantidad, 'valor' => 0.0]])
            ->all();

        return ['almacenes' => $almacenes, 'asignaciones' => $asignaciones];
    }

    /**
     * Dos piezas con la misma serie no pueden acabar bajo el mismo artículo:
     * el unique (articulo_id, no_serie) las rechazaría a la mitad de la
     * transacción, y es mejor decirlo antes, con las series a la vista.
     *
     * @param  Collection<int, Articulo>  $sobrantes
     */
    private function validarSeries(string $grupo, Articulo $sobreviviente, Collection $sobrantes): void
    {
        $ids = [$sobreviviente->id, ...$sobrantes->pluck('id')->all()];

        $chocan = DB::table('alm_activos')
            ->whereIn('articulo_id', $ids)
            ->select('no_serie')
            ->groupBy('no_serie')
            ->havingRaw('COUNT(DISTINCT articulo_id) > 1')
            ->pluck('no_serie');

        if ($chocan->isNotEmpty()) {
            throw new InvalidArgumentException(sprintf(
                'Grupo "%s": la serie %s existe en más de un artículo del grupo y chocaría al fusionar.',
                $grupo,
                $chocan->implode(', '),
            ));
        }
    }

    /**
     * Qué hay en cada almacén antes, código por código, y cuánto quedaría.
     * Es lo que el dry-run enseña para que quien revise vea que el stock no se
     * pierde, sólo cambia de nombre.
     *
     * @param  Collection<int, Articulo>  $sobrantes
     * @return list<Almacen>
     */
    private function almacenesDe(Articulo $sobreviviente, Collection $sobrantes): array
    {
        $articulos = collect([$sobreviviente])->merge($sobrantes)->keyBy('id');

        $existencias = Existencia::query()
            ->with(['almacen:id,clave,nombre', 'ubicacion'])
            ->whereIn('articulo_id', $articulos->keys()->all())
            ->orderBy('almacen_id')
            ->orderBy('id')
            ->get();

        return $existencias
            ->groupBy('almacen_id')
            ->map(function (Collection $grupo, int $almacenId) use ($articulos, $sobreviviente): array {
                $antes = [];
                $ubicaciones = [];

                foreach ($grupo as $existencia) {
                    $codigo = (string) $articulos->get($existencia->articulo_id)?->codigo;
                    $antes[$codigo] = ($antes[$codigo] ?? 0) + (float) $existencia->cantidad;
                    $ubicaciones[$codigo] = $existencia->ubicacion?->ruta();
                }

                $almacen = $grupo->first()->almacen;
                $fusiona = $grupo->count() > 1;

                // Un solo renglón por artículo y almacén: al sumar, sólo queda
                // una ubicación. Se avisa cuando las dos existían y difieren,
                // porque eso es material en dos lugares que el sistema ya no va
                // a poder decir y alguien tiene que acomodar.
                $distintas = collect($ubicaciones)->filter()->unique()->count() > 1;
                $ubicacionQueQueda = $ubicaciones[(string) $sobreviviente->codigo] ?? collect($ubicaciones)->filter()->first();

                return [
                    'almacen_id' => $almacenId,
                    'nombre' => $almacen?->nombre ?? (string) $almacenId,
                    'antes' => $antes,
                    'ubicaciones' => $ubicaciones,
                    'ubicacion_queda' => $ubicacionQueQueda,
                    'ubicaciones_distintas' => $fusiona && $distintas,
                    'cantidad' => (float) $grupo->sum('cantidad'),
                    'valor' => (float) $grupo->sum('valor'),
                    'fusiona' => $fusiona,
                ];
            })
            ->values()
            ->all();
    }
}
