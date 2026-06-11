<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Models\Cotiz\PinturaFormula;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaRegistro;
use App\Services\Cotiz\Variables\ContextoEval;
use App\Services\Cotiz\Variables\Dominios\ResolvedorCuadrilla;
use App\Services\Cotiz\Variables\Dominios\ResolvedorGeneradora;
use App\Services\Cotiz\Variables\Dominios\ResolvedorTarjeta;
use App\Services\Cotiz\Variables\Registry;
use Closure;
use Normalizer;

/**
 * Motor de cálculo de una tarjeta. Port de `src/lib/tarjetaTotales.ts`.
 *
 * Fase 3b: usa el namespace PLANO de variables (kg_fab, area_pintura, kg_<corte>,
 * importe_<slug> + referencias entre factores por código). El direccionamiento
 * semántico cross-dominio (M046) se enchufa en 3c vía el hook `$expandir` del
 * FactorResolver — aquí se invoca SIN expandir.
 *
 * Todo el cálculo es autoritativo en PHP; los importes/kg no se persisten como fuente
 * de verdad (solo cache M039, refrescado por `refrescarCache`).
 */
class TarjetaCalculator
{
    public function __construct(
        private readonly OverrideResolver $overrides,
        private readonly MermaCalculator $merma,
        private readonly PinturaCalculator $pintura,
        private readonly KilosRealesCalculator $kilosReales,
        private readonly FactorResolver $factores,
    ) {}

    /**
     * Calcula los totales y el desglose de una tarjeta.
     *
     * @return array{
     *     registros: list<array<string, mixed>>,
     *     factores: list<array<string, mixed>>,
     *     importe_por_categoria: array<string, float>,
     *     kg_fab: float,
     *     area_pintura: float,
     *     kg_por_tipo_corte: array{TIRAS: float, RAZ: float, KG: float, CNX: float},
     *     kg_reales_total: float,
     *     total_registros: float,
     *     total_factores: float,
     *     total_importe: float,
     *     num_registros: int
     * }
     */
    public function calcular(Tarjeta $tarjeta): array
    {
        $tarjeta->loadMissing([
            'registros.generadoraRegistro.materialOrigen.unidad',
            'registros.generadoraRegistro.materialOrigen.categoriaTarjeta',
            'registros.generadoraRegistro.merma',
            'registros.generadoraRegistro.generadora:id,titulo',
            'registros.insumo.unidad',
            'registros.insumo.categoriaTarjeta',
            'factores.factor.insumo',
            'factores.factor.categoriaTarjeta',
            'categoriasKilos.categoria',
            'kilosReales',
            'insumoPrecios',
        ]);

        $insumoOverrides = ObraInsumoOverride::query()
            ->where('obra_id', $tarjeta->obra_id)
            ->get()
            ->keyBy('insumo_id');
        $factorOverrides = ObraFactorOverride::query()
            ->where('obra_id', $tarjeta->obra_id)
            ->with('insumo.unidad')
            ->get()
            ->keyBy('factor_id');
        $preciosTarjeta = $tarjeta->insumoPrecios->keyBy('insumo_id');
        $pinturaFormulas = PinturaFormula::query()->pluck('formula', 'clave')->all();

        $registros = $tarjeta->registros
            ->map(fn (TarjetaRegistro $registro) => $this->resolverRegistro(
                $registro,
                $insumoOverrides,
                $preciosTarjeta,
            ))
            ->all();

        $importePorCategoria = $this->importePorCategoria($registros);
        $kgFab = $this->kgFab($registros);
        $areaPintura = $this->areaPintura($registros, $pinturaFormulas);
        $kgPorTipoCorte = $this->kilosReales->porTipoCorte(
            $tarjeta->categoriasKilos->map(fn ($c) => [
                'categoria_id' => $c->categoria_id,
                // tipo_corte está casteado al enum TipoCorte; el calculador trabaja con su valor string.
                'tipo_corte' => $c->categoria?->tipo_corte?->value ?? '',
                'porcentual' => $c->porcentual,
            ])->all(),
            $tarjeta->kilosReales->map(fn ($k) => [
                'categoria_id' => $k->categoria_id,
                'kilos' => $k->kilos,
            ])->all(),
        );
        $kgRealesTotal = $this->kilosReales->total($kgPorTipoCorte);

        // Direccionamiento semántico (M046): el factor puede referenciar total.tarjeta.kg,
        // total.tarjeta.kg_real[corte=X], total.tarjeta.importe[cc=Y], tarjeta.factor[cod=Z], etc.
        $expandir = $this->construirExpandir($tarjeta, [
            'dominio' => 'tarjeta',
            'registros' => $registros,
            'kg_por_tipo_corte' => $kgPorTipoCorte,
            'pintura_formulas' => $pinturaFormulas,
        ]);

        $factores = $this->resolverFactores(
            $tarjeta->factores,
            $factorOverrides,
            $insumoOverrides,
            $preciosTarjeta,
            $kgFab,
            $areaPintura,
            $kgPorTipoCorte,
            $importePorCategoria,
            $expandir,
        );

        $totalRegistros = array_sum(array_column($registros, 'importe'));
        $totalFactores = array_sum(array_column($factores, 'importe'));

        return [
            'registros' => $registros,
            'factores' => $factores,
            'importe_por_categoria' => $importePorCategoria,
            'kg_fab' => $kgFab,
            'area_pintura' => $areaPintura,
            'kg_por_tipo_corte' => $kgPorTipoCorte,
            'kg_reales_total' => $kgRealesTotal,
            'total_registros' => $totalRegistros,
            'total_factores' => $totalFactores,
            'total_importe' => $totalRegistros + $totalFactores,
            'num_registros' => count($registros),
        ];
    }

    /**
     * Recalcula y, si difiere, refresca el cache (importe_materiales / kilos_reales).
     *
     * @return array<string, mixed> el resultado de calcular()
     */
    public function refrescarCache(Tarjeta $tarjeta): array
    {
        $resultado = $this->calcular($tarjeta);

        $importe = round($resultado['total_importe'], 4);
        $kilos = round($resultado['kg_reales_total'], 4);

        $difImporte = $tarjeta->importe_materiales === null
            || abs((float) $tarjeta->importe_materiales - $importe) >= 0.005;
        $difKilos = $tarjeta->kilos_reales === null
            || abs((float) $tarjeta->kilos_reales - $kilos) >= 0.005;

        if ($difImporte || $difKilos) {
            $tarjeta->forceFill([
                'importe_materiales' => $importe,
                'kilos_reales' => $kilos,
            ])->saveQuietly();
        }

        return $resultado;
    }

    /**
     * Slug canónico de una categoría — fuente única usada por las variables `importe_<slug>`.
     */
    public static function slugCategoria(?string $valor): string
    {
        $s = ($valor === null || $valor === '') ? 'sin_clasificar' : $valor;
        $s = mb_strtolower($s);

        if (class_exists(Normalizer::class)) {
            $descompuesto = Normalizer::normalize($s, Normalizer::FORM_D);
            if ($descompuesto !== false) {
                $s = preg_replace('/\p{Mn}/u', '', $descompuesto) ?? $s;
            }
        }

        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? $s;

        return trim($s, '_');
    }

    /**
     * Construye el hook `$expandir` para el FactorResolver: arma el registro de resolvedores
     * (tarjeta self con precargados, generadora, cuadrilla) y el contexto de la tarjeta actual.
     *
     * @param  array<string, mixed>  $precargados
     * @return Closure(string, array<string, float>): array{formula: string, vars: array<string, float>}
     */
    private function construirExpandir(Tarjeta $tarjeta, array $precargados): Closure
    {
        // Instancia concreta `tarjeta#nombre`: carga otra tarjeta de la misma obra y sus datos.
        $cargarDatos = function (string $nombre, ?int $obraId): ?array {
            $otra = Tarjeta::query()
                ->where('descripcion', $nombre)
                ->where('obra_id', $obraId)
                ->first();

            return $otra !== null ? $this->datosTarjeta($otra) : null;
        };

        $registry = new Registry(
            new ResolvedorTarjeta($this->pintura, $cargarDatos),
            new ResolvedorGeneradora($this->merma),
            new ResolvedorCuadrilla,
        );

        $contexto = new ContextoEval(
            obraId: $tarjeta->obra_id,
            self: ['dominio' => 'tarjeta', 'instancia' => $tarjeta->id],
            precargados: $precargados,
        );

        return $registry->expandirConContexto($contexto);
    }

    /**
     * Datos de variables (registros resueltos + kg por corte + fórmulas de pintura) de una
     * tarjeta — para resolver direcciones `tarjeta#nombre.<col>` de otra instancia.
     *
     * @return array<string, mixed>
     */
    private function datosTarjeta(Tarjeta $tarjeta): array
    {
        $tarjeta->loadMissing([
            'registros.generadoraRegistro.materialOrigen.unidad',
            'registros.generadoraRegistro.materialOrigen.categoriaTarjeta',
            'registros.generadoraRegistro.merma',
            'registros.insumo.unidad',
            'registros.insumo.categoriaTarjeta',
            'categoriasKilos.categoria',
            'kilosReales',
            'insumoPrecios',
        ]);

        $insumoOverrides = ObraInsumoOverride::query()
            ->where('obra_id', $tarjeta->obra_id)
            ->get()
            ->keyBy('insumo_id');
        $preciosTarjeta = $tarjeta->insumoPrecios->keyBy('insumo_id');

        $registros = $tarjeta->registros
            ->map(fn (TarjetaRegistro $registro) => $this->resolverRegistro($registro, $insumoOverrides, $preciosTarjeta))
            ->all();

        $kgPorTipoCorte = $this->kilosReales->porTipoCorte(
            $tarjeta->categoriasKilos->map(fn ($c) => [
                'categoria_id' => $c->categoria_id,
                'tipo_corte' => $c->categoria?->tipo_corte?->value ?? '',
                'porcentual' => $c->porcentual,
            ])->all(),
            $tarjeta->kilosReales->map(fn ($k) => [
                'categoria_id' => $k->categoria_id,
                'kilos' => $k->kilos,
            ])->all(),
        );

        return [
            'dominio' => 'tarjeta',
            'registros' => $registros,
            'kg_por_tipo_corte' => $kgPorTipoCorte,
            'pintura_formulas' => PinturaFormula::query()->pluck('formula', 'clave')->all(),
        ];
    }

    /**
     * Resuelve un registro a sus valores efectivos (insumo, cantidad con merma, P.U., categoría).
     *
     * @param  \Illuminate\Support\Collection<int, ObraInsumoOverride>  $insumoOverrides
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cotiz\TarjetaInsumoPrecio>  $preciosTarjeta
     * @return array<string, mixed>
     */
    private function resolverRegistro(TarjetaRegistro $registro, $insumoOverrides, $preciosTarjeta): array
    {
        $esManual = $registro->esManual();
        $gen = $registro->generadoraRegistro;
        $insumo = $esManual ? $registro->insumo : $gen?->materialOrigen;

        $override = $insumo !== null ? $insumoOverrides->get($insumo->id) : null;
        $datos = $insumo !== null
            ? $this->overrides->resolverInsumo($insumo, $override)
            : ['descripcion' => '', 'codigo_stumis' => null, 'unidad_id' => null, 'precio_unitario' => 0.0, 'peso_lineal' => null, 'peso_default' => null, 'centro_costo_id' => null];

        $precioTarjeta = ($insumo !== null && $preciosTarjeta->has($insumo->id))
            ? (float) $preciosTarjeta->get($insumo->id)->precio_unitario
            : null;
        $precio = $insumo !== null
            ? $this->overrides->precioInsumo($insumo, $override, $precioTarjeta)
            : 0.0;

        $cantidad = $this->cantidadConMerma($registro, $datos['peso_lineal'], $datos['peso_default']);

        $unidad = $insumo?->unidad?->descripcion;
        $categoria = $insumo?->categoriaTarjeta;

        return [
            'id' => $registro->id,
            'es_manual' => $esManual,
            'generadora_titulo' => $gen?->generadora?->titulo,
            'insumo_id' => $insumo?->id,
            'descripcion' => $datos['descripcion'],
            'codigo_stumis' => $datos['codigo_stumis'],
            'unidad' => $unidad,
            'categoria' => $categoria?->descripcion,
            'categoria_orden' => $categoria?->orden ?? 99999,
            'peso_lineal' => $datos['peso_lineal'],
            'tipo_pintura' => $registro->tipo_pintura,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'importe' => $cantidad * $precio,
            'validado' => $esManual ? $registro->validado : (bool) ($gen?->validado ?? false),
        ];
    }

    /**
     * Cantidad efectiva (kilos con merma) de un registro de tarjeta. Port de
     * `calcularCantidadConMerma`.
     */
    private function cantidadConMerma(TarjetaRegistro $registro, ?float $pesoLineal, ?float $pesoDefault): float
    {
        if ($registro->esManual()) {
            return (float) ($registro->cantidad ?? 0);
        }

        $gen = $registro->generadoraRegistro;
        if ($gen === null) {
            return 0.0;
        }

        $tMlM2 = $gen->t_ml_m2;
        if ($tMlM2 === null && $gen->kilos_totales !== null) {
            return (float) $gen->kilos_totales;
        }

        // kilos reales efectivos = t_ml_m2 × peso_lineal efectivo (obra override > global).
        $kilosReales = ($tMlM2 !== null && $pesoLineal !== null) ? $tMlM2 * $pesoLineal : 0.0;

        $formula = $gen->merma?->formula;
        if ($formula === null || trim($formula) === '') {
            return $kilosReales;
        }

        return $this->merma->evaluar($formula, [
            't_ml_m2' => $tMlM2,
            'kilos_reales' => $kilosReales,
            'ancho' => $gen->ancho,
            'largo' => $gen->largo,
            'cantidad' => ($pesoLineal !== null && $pesoLineal != 0.0) ? $kilosReales / $pesoLineal : null,
            'cant_pzas' => null,
            'peso_lineal' => $pesoLineal,
            'peso_default' => $pesoDefault,
        ]);
    }

    /**
     * Importe por categoría (variables `importe_<slug>`). Solo insumos, no factores.
     *
     * @param  list<array<string, mixed>>  $registros
     * @return array<string, float>
     */
    private function importePorCategoria(array $registros): array
    {
        $mapa = [];
        foreach ($registros as $registro) {
            $importe = $registro['importe'];
            if ($importe == 0.0) {
                continue;
            }
            $clave = 'importe_'.self::slugCategoria($registro['categoria']);
            $mapa[$clave] = ($mapa[$clave] ?? 0.0) + $importe;
        }

        return $mapa;
    }

    /**
     * kg_fab = Σ cantidad de registros cuya unidad sea "kg".
     *
     * @param  list<array<string, mixed>>  $registros
     */
    private function kgFab(array $registros): float
    {
        $total = 0.0;
        foreach ($registros as $registro) {
            if (strtolower((string) ($registro['unidad'] ?? '')) === 'kg') {
                $total += $registro['cantidad'];
            }
        }

        return $total;
    }

    /**
     * area_pintura = Σ área pintable de cada registro.
     *
     * @param  list<array<string, mixed>>  $registros
     * @param  array<string, string>  $pinturaFormulas
     */
    private function areaPintura(array $registros, array $pinturaFormulas): float
    {
        $total = 0.0;
        foreach ($registros as $registro) {
            $total += $this->pintura->area(
                $registro['tipo_pintura'],
                $registro['cantidad'],
                $registro['peso_lineal'] !== null ? (float) $registro['peso_lineal'] : null,
                $registro['descripcion'],
                $pinturaFormulas,
            );
        }

        return $total;
    }

    /**
     * Resuelve las cantidades e importes de los factores de la tarjeta.
     *
     * @param  \Illuminate\Support\Collection<int, TarjetaFactor>  $tarjetaFactores
     * @param  \Illuminate\Support\Collection<int, ObraFactorOverride>  $factorOverrides
     * @param  \Illuminate\Support\Collection<int, ObraInsumoOverride>  $insumoOverrides
     * @param  \Illuminate\Support\Collection<int, \App\Models\Cotiz\TarjetaInsumoPrecio>  $preciosTarjeta
     * @param  array{TIRAS: float, RAZ: float, KG: float, CNX: float}  $kgPorTipoCorte
     * @param  array<string, float>  $importePorCategoria
     * @param  Closure(string, array<string, float>): array{formula: string, vars: array<string, float>}  $expandir
     * @return list<array<string, mixed>>
     */
    private function resolverFactores(
        $tarjetaFactores,
        $factorOverrides,
        $insumoOverrides,
        $preciosTarjeta,
        float $kgFab,
        float $areaPintura,
        array $kgPorTipoCorte,
        array $importePorCategoria,
        Closure $expandir,
    ): array {
        if ($tarjetaFactores->isEmpty()) {
            return [];
        }

        $variablesBase = [
            'kg_fab' => $kgFab,
            'area_pintura' => $areaPintura,
            'kg_tiras' => $kgPorTipoCorte['TIRAS'],
            'kg_raz' => $kgPorTipoCorte['RAZ'],
            'kg_kg' => $kgPorTipoCorte['KG'],
            'kg_cnx' => $kgPorTipoCorte['CNX'],
            ...$importePorCategoria,
        ];

        $resueltos = [];
        $paraResolver = [];
        $cantidadesManuales = [];

        foreach ($tarjetaFactores as $tf) {
            /** @var Factor $factor */
            $factor = $tf->factor;
            $override = $factorOverrides->get($factor->id);
            $datos = $this->overrides->resolverFactor($factor, $override, $tf->formula_override);

            $insumoEf = $override?->insumo ?? $factor->insumo;
            $precio = 0.0;
            if ($insumoEf !== null) {
                $insumoOverride = $insumoOverrides->get($insumoEf->id);
                $precioTarjeta = $preciosTarjeta->has($insumoEf->id)
                    ? (float) $preciosTarjeta->get($insumoEf->id)->precio_unitario
                    : null;
                $precio = $this->overrides->precioInsumo($insumoEf, $insumoOverride, $precioTarjeta);
            }

            $paraResolver[] = ['codigo' => $factor->codigo, 'formula' => $datos['formula']];
            if ($datos['formula'] === null) {
                $cantidadesManuales[$factor->codigo] = (float) ($tf->cantidad_manual ?? 0);
            }

            $resueltos[] = [
                'tf' => $tf,
                'factor' => $factor,
                'datos' => $datos,
                'precio' => $precio,
            ];
        }

        $cantidades = $this->factores->resolver($paraResolver, $variablesBase, $cantidadesManuales, $expandir);

        $salida = [];
        foreach ($resueltos as $r) {
            /** @var TarjetaFactor $tf */
            $tf = $r['tf'];
            /** @var Factor $factor */
            $factor = $r['factor'];
            $cantidad = $cantidades[$factor->codigo] ?? 0.0;
            $importe = $tf->importe !== null
                ? (float) $tf->importe
                : $cantidad * $r['precio'];

            $salida[] = [
                'id' => $tf->id,
                'factor_id' => $factor->id,
                'codigo' => $factor->codigo,
                'nombre' => $r['datos']['nombre'],
                'formula' => $r['datos']['formula'],
                'categoria' => $factor->categoriaTarjeta?->descripcion,
                'categoria_orden' => $factor->categoriaTarjeta?->orden ?? 99999,
                'cantidad' => $cantidad,
                'precio_unitario' => $r['precio'],
                'importe' => $importe,
                'validado' => (bool) $tf->validado,
            ];
        }

        return $salida;
    }
}
