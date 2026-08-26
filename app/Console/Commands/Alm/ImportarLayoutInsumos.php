<?php

namespace App\Console\Commands\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\ClasificacionAbc;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use App\Services\Alm\GeneradorCodigoArticulo;
use App\Services\Alm\RegistradorAjuste;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Carga inicial de un almacén desde el layout que se entregó a las áreas
 * (`LAYOUT-ALMACEN-*.xlsx`, hoja `Insumos`).
 *
 * Corre en dos tiempos a propósito: sin `--commit` sólo lee, valida contra los
 * catálogos y reporta cuántos artículos nacerían, con cuánto saldo y con qué
 * valuación. Es lo que se le enseña al área antes de tocar la base, porque
 * después ya no hay marcha atrás barata: los documentos de almacén son
 * inmutables y un saldo mal cargado se corrige con otro ajuste, no borrando.
 *
 * El saldo entra por `RegistradorAjuste` con motivo `carga_inicial` —un ajuste
 * por almacén, con su folio— y de ahí al kardex por el ledger. Nadie escribe la
 * existencia a mano.
 */
class ImportarLayoutInsumos extends Command
{
    protected $signature = 'alm:importar-insumos
        {archivo* : ruta de uno o mas LAYOUT-ALMACEN-*.xlsx}
        {--commit : escribe; sin esta bandera solo valida y reporta}
        {--usuario= : correo de quien autoriza el ajuste de carga inicial}
        {--prorratear-envase : cuando la unidad es a granel y la descripcion declara el envase, divide el costo entre el envase}';

    protected $description = 'Carga el catálogo y el saldo inicial de un almacén desde el layout de insumos';

    private const HOJA = 'Insumos';

    /** El encabezado es el contrato de la carga: ni se cambia ni se reordena. */
    private const COLUMNAS = [
        'DESCRIPCION', 'UNIDAD', 'AREA', 'IDSTEELEX', 'CODIGO_BARRAS',
        'CLASIFICACION_ABC', 'REQUIERE_VERIFICACION', 'STOCK_MINIMO',
        'ALMACEN', 'OBRA', 'UBICACION', 'EXISTENCIA_INICIAL', 'COSTO_UNITARIO',
    ];

    /** Cuantos renglones se nombran por problema antes de resumir el resto. */
    private const MUESTRA = 5;

    /** Unidades que se miden a granel: las únicas que pueden traer costo de envase. */
    private const GRANEL = ['KG', 'LTS', 'MTS'];

    /**
     * Problemas y avisos agrupados por texto: el mismo almacen inexistente en 39
     * renglones es un problema con 39 renglones, no 39 problemas. Repetirlo
     * entierra los demas y quien lee el reporte se rinde antes de llegar al
     * ultimo.
     *
     * @var array<string, list<string>>
     */
    private array $errores = [];

    /** @var array<string, list<string>> */
    private array $avisos = [];

    public function handle(GeneradorCodigoArticulo $generador, RegistradorAjuste $ajustes): int
    {
        try {
            $renglones = $this->leerArchivos();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($renglones->isEmpty()) {
            $this->components->error('No hay renglones que cargar.');

            return self::FAILURE;
        }

        $plan = $this->planear($renglones);

        $this->reportar($plan);

        if ($this->errores !== []) {
            $this->newLine();
            $this->components->error(count($this->errores).' problema(s) que impiden la carga:');
            $this->detallar($this->errores);

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->newLine();
            $this->components->info('Ensayo: no se escribió nada. Corre otra vez con --commit para cargar.');

            return self::SUCCESS;
        }

        $autoriza = $this->autorizador();

        if ($autoriza === null) {
            return self::FAILURE;
        }

        return $this->cargar($plan, $autoriza, $generador, $ajustes);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function leerArchivos(): Collection
    {
        $renglones = collect();

        foreach ($this->argument('archivo') as $ruta) {
            if (! is_file($ruta)) {
                throw new RuntimeException("No encuentro el archivo: {$ruta}");
            }

            $renglones = $renglones->concat($this->leer($ruta));
        }

        return $renglones;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leer(string $ruta): array
    {
        $hoja = IOFactory::createReaderForFile($ruta)
            ->setReadDataOnly(true)
            ->load($ruta)
            ->getSheetByName(self::HOJA);

        if ($hoja === null) {
            throw new RuntimeException(basename($ruta).": no tiene la hoja '".self::HOJA."'.");
        }

        $filas = $hoja->toArray(null, true, false, false);
        $encabezado = array_map(
            fn ($celda): string => strtoupper(trim((string) $celda)),
            array_shift($filas) ?? [],
        );

        $faltantes = array_diff(self::COLUMNAS, $encabezado);

        if ($faltantes !== []) {
            throw new RuntimeException(
                basename($ruta).': al encabezado le faltan columnas ('.implode(', ', $faltantes).').',
            );
        }

        $indice = array_flip($encabezado);
        $renglones = [];

        foreach ($filas as $n => $fila) {
            $valores = [];

            foreach (self::COLUMNAS as $columna) {
                $valores[$columna] = trim((string) ($fila[$indice[$columna]] ?? ''));
            }

            // Los renglones en blanco no son un error: la hoja se entregó con
            // espacio de sobra para agregar.
            if (implode('', $valores) === '') {
                continue;
            }

            $valores['_archivo'] = basename($ruta);
            $valores['_fila'] = $n + 2;
            $renglones[] = $valores;
        }

        return $renglones;
    }

    /**
     * Resuelve cada renglón contra los catálogos y lo deja listo para escribir.
     * Nada se escribe aquí: es lo que permite enseñar el resultado antes.
     *
     * @param  Collection<int, array<string, mixed>>  $renglones
     * @return list<array<string, mixed>>
     */
    private function planear(Collection $renglones): array
    {
        $areas = Area::query()->get(['id', 'descripcion'])
            ->keyBy(fn (Area $a): string => $this->normalizar($a->descripcion));

        $almacenes = Almacen::query()->get(['id', 'clave', 'nombre'])
            ->groupBy(fn (Almacen $a): string => strtoupper($a->clave));

        $ubicaciones = Ubicacion::query()->get(['id', 'almacen_id', 'codigo']);

        $existentes = Producto::query()->pluck('descripcion')
            ->map(fn (string $descripcion): string => $this->normalizar($descripcion))
            ->flip();

        $vistos = [];
        $plan = [];

        foreach ($renglones as $renglon) {
            $donde = "{$renglon['_archivo']}:{$renglon['_fila']}";
            $descripcion = $renglon['DESCRIPCION'];

            if ($descripcion === '') {
                $this->anotarProblema($donde, 'Renglón sin DESCRIPCION.');

                continue;
            }

            $clave = $this->normalizar($descripcion);

            if (isset($vistos[$clave])) {
                $this->anotarProblema($donde, "Repite la descripción de {$vistos[$clave]}: serían dos artículos distintos con el mismo nombre.");

                continue;
            }

            $vistos[$clave] = $donde;

            if ($existentes->has($clave)) {
                $this->anotarAviso($donde, "\"{$descripcion}\" ya existe en el catálogo de productos: nacería un segundo artículo con el mismo nombre.");
            }

            $unidad = strtoupper($renglon['UNIDAD']);

            if (! in_array($unidad, Producto::UNIDADES, true)) {
                $this->anotarProblema($donde, "Unidad \"{$renglon['UNIDAD']}\" fuera del catálogo (".implode(', ', Producto::UNIDADES).').');
            }

            $areaId = null;

            if ($renglon['AREA'] !== '') {
                $area = $areas->get($this->normalizar($renglon['AREA']));

                if ($area === null) {
                    $this->anotarProblema($donde, "El área \"{$renglon['AREA']}\" no existe en el catálogo de áreas.");
                } else {
                    $areaId = $area->id;
                }
            }

            $clase = strtoupper($renglon['CLASIFICACION_ABC']);

            if (ClasificacionAbc::tryFrom($clase) === null) {
                $this->anotarProblema($donde, "Clasificación ABC \"{$renglon['CLASIFICACION_ABC']}\" inválida (A, B o C).");
            }

            $stockMinimo = $renglon['STOCK_MINIMO'] === '' ? null : $this->numero($renglon['STOCK_MINIMO'], $donde, 'STOCK_MINIMO');
            $cantidad = $renglon['EXISTENCIA_INICIAL'] === '' ? null : $this->numero($renglon['EXISTENCIA_INICIAL'], $donde, 'EXISTENCIA_INICIAL');
            $costo = $renglon['COSTO_UNITARIO'] === '' ? null : $this->numero($renglon['COSTO_UNITARIO'], $donde, 'COSTO_UNITARIO');

            [$almacenId, $ubicacionId] = $this->resolverUbicacionFisica($renglon, $donde, $almacenes, $ubicaciones, $cantidad);

            [$costo, $nota] = $this->revisarEnvase($renglon, $unidad, $costo, $donde);

            if (($cantidad ?? 0) > 0 && ($costo === null || $costo <= 0)) {
                $this->anotarAviso($donde, "\"{$descripcion}\" carga {$cantidad} sin costo: el inventario arranca valuado en cero y el primer consumo sale gratis.");
            }

            $plan[] = [
                'donde' => $donde,
                'producto' => [
                    'descripcion' => $descripcion,
                    'unidad' => $unidad,
                    'idsteelex' => $renglon['IDSTEELEX'] ?: null,
                    'codigo_barras' => $renglon['CODIGO_BARRAS'] ?: null,
                    'area_id' => $areaId,
                    'clasificacion_abc' => $clase,
                    'requiere_verificacion' => $this->esSi($renglon['REQUIERE_VERIFICACION']),
                    'stock_minimo' => $stockMinimo,
                ],
                'almacen_id' => $almacenId,
                'ubicacion_id' => $ubicacionId,
                'cantidad' => $cantidad,
                'costo' => $costo,
                // La columna OBRA es a qué trabajo está asignado el insumo, no
                // dónde vive: la existencia es almacén+artículo y no tiene esa
                // dimensión. No mueve saldo; se guarda en el renglón del ajuste
                // para no perder el dato.
                'nota' => trim(implode(' ', array_filter([
                    $renglon['OBRA'] !== '' ? "Asignado a: {$renglon['OBRA']}." : null,
                    $nota,
                ]))) ?: null,
            ];
        }

        return $plan;
    }

    /**
     * Dónde está hoy el material: el almacén y, dentro de él, el lugar.
     *
     * @param  Collection<string, Collection<int, Almacen>>  $almacenes
     * @param  Collection<int, Ubicacion>  $ubicaciones
     * @return array{0: int|null, 1: int|null}
     */
    private function resolverUbicacionFisica(
        array $renglon,
        string $donde,
        Collection $almacenes,
        Collection $ubicaciones,
        ?float $cantidad,
    ): array {
        if ($renglon['ALMACEN'] === '') {
            if (($cantidad ?? 0) > 0) {
                $this->anotarProblema($donde, 'Trae existencia pero no dice en qué almacén está.');
            }

            return [null, null];
        }

        $candidatos = $almacenes->get(strtoupper($renglon['ALMACEN']), collect());

        if ($candidatos->isEmpty()) {
            $this->anotarProblema($donde, "El almacén \"{$renglon['ALMACEN']}\" no existe. Hay que darlo de alta antes de cargarle saldo.");

            return [null, null];
        }

        // La clave sola no basta cuando se repite entre obras, y la columna OBRA
        // viene usada para otra cosa: mejor detenerse que adivinar el almacén.
        if ($candidatos->count() > 1) {
            $this->anotarProblema($donde, "La clave \"{$renglon['ALMACEN']}\" la usan ".$candidatos->count().' almacenes: no se puede saber a cuál cargar.');

            return [null, null];
        }

        $almacenId = (int) $candidatos->first()->id;

        if ($renglon['UBICACION'] === '') {
            return [$almacenId, null];
        }

        $ubicacion = $ubicaciones->first(
            fn (Ubicacion $u): bool => (int) $u->almacen_id === $almacenId
                && strtoupper($u->codigo) === strtoupper($renglon['UBICACION']),
        );

        if ($ubicacion === null) {
            $this->anotarProblema($donde, "La ubicación \"{$renglon['UBICACION']}\" no existe en ese almacén.");

            return [$almacenId, null];
        }

        return [$almacenId, (int) $ubicacion->id];
    }

    /**
     * El costo que viene del envase y no de la unidad.
     *
     * Pasa cuando la cantidad se contó a granel —1650 litros— y el precio se
     * anotó por tambor: el promedio nace multiplicado por el envase y cada
     * salida le carga eso de más a la obra. El kardex sella el costo al
     * registrar el movimiento, así que corregirlo después no repara las salidas
     * ya hechas; de ahí que la carga se detenga aquí en vez de avisar.
     *
     * @param  array<string, mixed>  $renglon
     * @return array{0: float|null, 1: string|null}
     */
    private function revisarEnvase(array $renglon, string $unidad, ?float $costo, string $donde): array
    {
        if ($costo === null || $costo <= 0 || ! in_array($unidad, self::GRANEL, true)) {
            return [$costo, null];
        }

        if (preg_match('/\(\s*([\d.]+)\s*(lts?|kgs?|mts?)\s*\.?\s*\)/i', (string) $renglon['DESCRIPCION'], $coincidencia) !== 1) {
            return [$costo, null];
        }

        $envase = (float) $coincidencia[1];

        if ($envase <= 1) {
            return [$costo, null];
        }

        $porUnidad = round($costo / $envase, 4);

        if (! $this->option('prorratear-envase')) {
            $this->anotarProblema($donde, "Se cuenta en {$unidad} pero el costo ({$costo}) parece del envase de {$envase}: por unidad daría {$porUnidad}. Corrige el archivo o corre con --prorratear-envase.");

            return [$costo, null];
        }

        $this->anotarAviso($donde, "Costo prorrateado: {$costo} / {$envase} = {$porUnidad} por {$unidad}.");

        return [$porUnidad, "Costo prorrateado del envase de {$envase} {$unidad}: {$costo} / {$envase}."];
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     */
    private function reportar(array $plan): void
    {
        $conSaldo = array_filter($plan, fn (array $entrada): bool => $entrada['almacen_id'] !== null);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Artículos que nacerían</>', (string) count($plan));
        $this->components->twoColumnDetail('<fg=gray>Con saldo inicial</>', (string) count($conSaldo));
        $this->components->twoColumnDetail('<fg=gray>Valuación total</>', '$'.number_format($this->valuacion($conSaldo), 2));

        $porAlmacen = collect($conSaldo)->groupBy('almacen_id');

        if ($porAlmacen->isNotEmpty()) {
            $claves = Almacen::query()->whereKey($porAlmacen->keys())->pluck('clave', 'id');

            $this->newLine();
            $this->table(
                ['Almacén', 'Artículos', 'Valuación'],
                $porAlmacen->map(fn (Collection $filas, int|string $id): array => [
                    $claves[$id] ?? $id,
                    $filas->count(),
                    '$'.number_format($this->valuacion($filas->all()), 2),
                ])->values()->all(),
            );
        }

        if ($this->avisos !== []) {
            $this->newLine();
            $this->components->warn(count($this->avisos).' aviso(s), que no impiden la carga:');
            $this->detallar($this->avisos);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entradas
     */
    private function valuacion(array $entradas): float
    {
        return array_sum(array_map(
            fn (array $entrada): float => ($entrada['cantidad'] ?? 0) * ($entrada['costo'] ?? 0),
            $entradas,
        ));
    }

    /**
     * Todo en una transacción: si un renglón revienta a la mitad, no queda medio
     * almacén cargado con la mitad de los artículos dados de alta.
     *
     * @param  list<array<string, mixed>>  $plan
     */
    private function cargar(array $plan, Usuario $autoriza, GeneradorCodigoArticulo $generador, RegistradorAjuste $ajustes): int
    {
        $folios = DB::transaction(function () use ($plan, $autoriza, $generador, $ajustes): array {
            $renglonesPorAlmacen = [];

            foreach ($plan as $entrada) {
                $codigo = $generador->siguiente();

                $producto = Producto::create([
                    ...$entrada['producto'],
                    'codigo' => $codigo,
                    // La etiqueta que se imprime es la nuestra salvo que la caja
                    // ya venga con una de fábrica.
                    'codigo_barras' => $entrada['producto']['codigo_barras'] ?? $codigo,
                    'tipo' => ProductoTipo::Insumo,
                    'controla_inventario' => true,
                    'se_controla_por_pieza' => false,
                    'activo' => true,
                    'creado_por' => $autoriza->getKey(),
                ]);

                if ($entrada['almacen_id'] === null) {
                    continue;
                }

                $renglonesPorAlmacen[$entrada['almacen_id']][] = [
                    'producto_id' => $producto->id,
                    'cantidad_contada' => $entrada['cantidad'] ?? 0,
                    'costo_unitario' => $entrada['costo'],
                    'observaciones' => $entrada['nota'],
                    'ubicacion_id' => $entrada['ubicacion_id'],
                ];
            }

            $folios = [];

            foreach ($renglonesPorAlmacen as $almacenId => $renglones) {
                $ajuste = $ajustes->registrar(
                    cabecera: [
                        'almacen_id' => $almacenId,
                        'motivo' => AjusteMotivo::CargaInicial->value,
                        'fecha' => now()->toDateString(),
                        'observaciones' => 'Carga inicial desde el layout de insumos.',
                        'autorizado_por' => $autoriza->getKey(),
                    ],
                    renglones: array_map(
                        fn (array $renglon): array => Arr::except($renglon, 'ubicacion_id'),
                        $renglones,
                    ),
                    userId: $autoriza->getKey(),
                );

                $this->acomodar((int) $almacenId, $renglones);

                $folios[] = $ajuste->folio;
            }

            return $folios;
        });

        $this->newLine();
        $this->components->info('Cargado: '.count($plan).' artículo(s) dados de alta.');

        foreach ($folios as $folio) {
            $this->line("   - Ajuste de carga inicial {$folio}");
        }

        return self::SUCCESS;
    }

    /**
     * Acomodar no es mover saldo: la ubicación vive en la existencia y se
     * escribe aparte del ledger, igual que desde la pantalla de artículos.
     *
     * @param  list<array<string, mixed>>  $renglones
     */
    private function acomodar(int $almacenId, array $renglones): void
    {
        foreach ($renglones as $renglon) {
            if ($renglon['ubicacion_id'] === null) {
                continue;
            }

            Existencia::query()
                ->where('almacen_id', $almacenId)
                ->where('producto_id', $renglon['producto_id'])
                ->update(['ubicacion_id' => $renglon['ubicacion_id']]);
        }
    }

    private function autorizador(): ?Usuario
    {
        $correo = $this->option('usuario');

        if ($correo === null) {
            $this->components->error('El ajuste de carga inicial necesita quién lo autoriza: pasa --usuario=correo@dominio.');

            return null;
        }

        $usuario = Usuario::query()->where('email', $correo)->first();

        if ($usuario === null) {
            $this->components->error("No hay ningún usuario con el correo {$correo}.");
        }

        return $usuario;
    }

    private function numero(string $valor, string $donde, string $columna): ?float
    {
        $limpio = str_replace([',', ' ', '$'], '', $valor);

        if (! is_numeric($limpio)) {
            $this->anotarProblema($donde, "{$columna} \"{$valor}\" no es un número.");

            return null;
        }

        if ((float) $limpio < 0) {
            $this->anotarProblema($donde, "{$columna} no puede ser negativo.");

            return null;
        }

        return (float) $limpio;
    }

    private function esSi(string $valor): bool
    {
        return in_array(strtoupper(trim($valor)), ['SI', 'SÍ', 'S', '1', 'X'], true);
    }

    private function anotarProblema(string $donde, string $mensaje): void
    {
        $this->errores[$mensaje][] = $donde;
    }

    private function anotarAviso(string $donde, string $mensaje): void
    {
        $this->avisos[$mensaje][] = $donde;
    }

    /**
     * Un renglon por problema, con cuantos lo tienen y donde empiezan. Se listan
     * los primeros y se dice cuantos faltan: para corregir el archivo basta con
     * saber que columna esta mal y tener por donde entrar.
     *
     * @param  array<string, list<string>>  $agrupados
     */
    private function detallar(array $agrupados): void
    {
        foreach ($agrupados as $mensaje => $dondes) {
            $muestra = array_slice($dondes, 0, self::MUESTRA);
            $restantes = count($dondes) - count($muestra);

            $this->line(sprintf(
                '   - %s (%d: %s%s)',
                $mensaje,
                count($dondes),
                implode(', ', $muestra),
                $restantes > 0 ? " y {$restantes} mas" : '',
            ));
        }
    }

    private function normalizar(string $texto): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_strtolower(trim($texto)));
    }
}
