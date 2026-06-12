<?php

namespace App\Services\Cotiz;

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraCuadrillaGlobal;
use App\Models\Cotiz\ObraFactorOverride;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\ObraInsumoOverride;
use App\Models\Cotiz\ObraResumenCeldaOverride;
use App\Models\Cotiz\ObraResumenCoeficiente;
use App\Models\Cotiz\ObraVersion;
use App\Models\Cotiz\ResumenColumna;
use App\Models\Cotiz\ResumenColumnaTarjeta;
use App\Models\Cotiz\SeccionFaseRendimiento;
use App\Models\Cotiz\SeccionMontaje;
use App\Models\Cotiz\SeccionPersonal;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaFactor;
use App\Models\Cotiz\TarjetaGeneradora;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use App\Models\Cotiz\TarjetaKilosReal;
use App\Models\Cotiz\TarjetaRegistro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Versionado de obras de Cotización (Fase 5.5, etapa B1). Modelo LINEAL: snapshots
 * continuos del árbol de INPUTS, sin ramas. Como el cálculo es derivado en PHP, una
 * versión solo captura inputs; restaurar = sobrescribir inputs + recalcular al leer.
 *
 * Restaurar NO ramifica: guarda primero un respaldo automático del estado actual y luego
 * sobrescribe los inputs de la obra con los del snapshot elegido (remapeando IDs).
 */
class VersionManager
{
    /** Campos que nunca se versionan (derivados/cache, locks, timestamps). */
    private const DROP = ['created_at', 'updated_at', 'importe_materiales', 'kilos_reales', 'locked_by', 'locked_at'];

    /**
     * Crea un snapshot nombrado de la obra.
     */
    public function crear(Obra $obra, string $nombre, ?string $nota = null, ?string $creadoPor = null, bool $auto = false): ObraVersion
    {
        return ObraVersion::query()->create([
            'obra_id' => $obra->id,
            'nombre' => $nombre,
            'nota' => $nota,
            'auto' => $auto,
            'creado_por' => $creadoPor,
            'snapshot' => $this->capturar($obra),
        ]);
    }

    /**
     * Restaura una versión: respalda el estado actual (auto-snapshot), sobrescribe los inputs
     * de la obra y recalcula. Lineal — no crea ramas.
     */
    public function restaurar(ObraVersion $version, ?string $creadoPor = null): ObraVersion
    {
        $obra = $version->obra;

        return DB::transaction(function () use ($version, $obra, $creadoPor) {
            $respaldo = $this->crear($obra, 'Antes de restaurar «'.$version->nombre.'»', null, $creadoPor, auto: true);

            $this->borrarInputs($obra);
            $this->reinsertar($obra, $version->snapshot);

            return $respaldo;
        });
    }

    /**
     * Captura el árbol de inputs de la obra como un arreglo serializable.
     *
     * @return array<string, mixed>
     */
    public function capturar(Obra $obra): array
    {
        $genIds = $obra->generadoras()->pluck('id');
        $tarjetaIds = $obra->tarjetas()->pluck('id');
        $seccionIds = $obra->seccionesMontaje()->pluck('id');
        $columnaIds = $obra->resumenColumnas()->pluck('id');

        return [
            'obra' => [
                'factor_contratista' => $obra->getRawOriginal('factor_contratista'),
                'num_grupos' => $obra->num_grupos,
            ],
            'generadoras' => $this->filas($obra->generadoras()),
            'generadora_registros' => $this->filas(GeneradoraRegistro::query()->whereIn('generadora_id', $genIds)),
            'tarjetas' => $this->filas($obra->tarjetas()),
            'tarjeta_generadoras' => $this->filas(TarjetaGeneradora::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_estructuras' => $this->filas(TarjetaEstructura::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_registros' => $this->filas(TarjetaRegistro::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_factores' => $this->filas(TarjetaFactor::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_categorias_kilos' => $this->filas(TarjetaCategoriaKilos::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_kilos_reales' => $this->filas(TarjetaKilosReal::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'tarjeta_insumo_precio' => $this->filas(TarjetaInsumoPrecio::query()->whereIn('tarjeta_id', $tarjetaIds)),
            'obra_insumo_override' => $this->filas($obra->insumoOverrides()),
            'obra_factor_override' => $this->filas($obra->factorOverrides()),
            'secciones_montaje' => $this->filas($obra->seccionesMontaje()),
            'seccion_personal' => $this->filas(SeccionPersonal::query()->whereIn('seccion_id', $seccionIds)),
            'seccion_fase_rendimiento' => $this->filas(SeccionFaseRendimiento::query()->whereIn('seccion_id', $seccionIds)),
            'obra_cuadrilla_global' => $this->filas($obra->cuadrillaGlobal()),
            'obra_flete_estandar' => $this->filas($obra->fletesEstandar()),
            'obra_fletes_viaticos' => $this->filas($obra->fletesViaticos()),
            'resumen_columnas' => $this->filas($obra->resumenColumnas()),
            'resumen_columna_tarjetas' => $this->filas(ResumenColumnaTarjeta::query()->whereIn('columna_id', $columnaIds)),
            'obra_resumen_coeficientes' => $this->filas($obra->resumenCoeficientes()),
            'obra_resumen_celda_override' => $this->filas($obra->resumenCeldaOverrides()),
        ];
    }

    /**
     * Diferencia entre dos snapshots: por grupo, conteo de filas y si cambió el contenido.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array{obra: array<string, array{de: mixed, a: mixed}>, grupos: array<string, array{de: int, a: int, cambio: bool}>}
     */
    public function comparar(array $a, array $b): array
    {
        $obraDiff = [];
        foreach (['factor_contratista', 'num_grupos'] as $campo) {
            $va = $a['obra'][$campo] ?? null;
            $vb = $b['obra'][$campo] ?? null;
            if ((string) $va !== (string) $vb) {
                $obraDiff[$campo] = ['de' => $va, 'a' => $vb];
            }
        }

        $grupos = [];
        $claves = array_unique([...array_keys($a), ...array_keys($b)]);
        foreach ($claves as $grupo) {
            if ($grupo === 'obra') {
                continue;
            }
            $filasA = $a[$grupo] ?? [];
            $filasB = $b[$grupo] ?? [];
            $grupos[$grupo] = [
                'de' => count($filasA),
                'a' => count($filasB),
                'cambio' => $this->normalizar($filasA) !== $this->normalizar($filasB),
            ];
        }

        return ['obra' => $obraDiff, 'grupos' => $grupos];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function filas($query): array
    {
        return $query->get()->map(function (Model $m): array {
            $attrs = $m->getAttributes();
            foreach (self::DROP as $k) {
                unset($attrs[$k]);
            }

            return $attrs;
        })->all();
    }

    /**
     * Normaliza un conjunto de filas para comparar contenido ignorando los IDs y el orden.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return list<string>
     */
    private function normalizar(array $filas): array
    {
        $sin = array_map(function (array $fila): string {
            unset($fila['id']);
            ksort($fila);

            return json_encode($fila) ?: '';
        }, $filas);
        sort($sin);

        return $sin;
    }

    private function borrarInputs(Obra $obra): void
    {
        $obra->resumenCeldaOverrides()->delete();
        $obra->resumenCoeficientes()->delete();
        $obra->resumenColumnas()->delete(); // cascade: columna_tarjetas
        $obra->fletesEstandar()->delete();
        $obra->fletesViaticos()->delete();
        $obra->cuadrillaGlobal()->delete();
        $obra->seccionesMontaje()->delete(); // cascade: seccion_personal, rendimientos
        $obra->insumoOverrides()->delete();
        $obra->factorOverrides()->delete();
        $obra->tarjetas()->delete();    // cascade: registros/factores/estructuras/kilos/precios/pivote
        $obra->generadoras()->delete(); // cascade: generadora_registros
    }

    /**
     * Reinserta el árbol de inputs remapeando los IDs internos.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function reinsertar(Obra $obra, array $snapshot): void
    {
        $obra->update([
            'factor_contratista' => $snapshot['obra']['factor_contratista'] ?? $obra->factor_contratista,
            'num_grupos' => $snapshot['obra']['num_grupos'] ?? $obra->num_grupos,
        ]);

        $genMap = $this->insertarGrupo(Generadora::class, $snapshot['generadoras'] ?? [], ['obra_id' => $obra->id]);
        $genRegMap = $this->insertarGrupo(GeneradoraRegistro::class, $snapshot['generadora_registros'] ?? [], [], ['generadora_id' => $genMap]);
        $tarjetaMap = $this->insertarGrupo(Tarjeta::class, $snapshot['tarjetas'] ?? [], ['obra_id' => $obra->id]);

        $this->insertarGrupo(TarjetaGeneradora::class, $snapshot['tarjeta_generadoras'] ?? [], [], ['tarjeta_id' => $tarjetaMap, 'generadora_id' => $genMap]);
        $estMap = $this->insertarGrupo(TarjetaEstructura::class, $snapshot['tarjeta_estructuras'] ?? [], [], ['tarjeta_id' => $tarjetaMap]);
        $this->insertarGrupo(TarjetaRegistro::class, $snapshot['tarjeta_registros'] ?? [], [], ['tarjeta_id' => $tarjetaMap, 'generadora_registro_id' => $genRegMap]);
        $this->insertarGrupo(TarjetaFactor::class, $snapshot['tarjeta_factores'] ?? [], [], ['tarjeta_id' => $tarjetaMap]);
        $this->insertarGrupo(TarjetaCategoriaKilos::class, $snapshot['tarjeta_categorias_kilos'] ?? [], [], ['tarjeta_id' => $tarjetaMap]);
        $this->insertarGrupo(TarjetaKilosReal::class, $snapshot['tarjeta_kilos_reales'] ?? [], [], ['tarjeta_id' => $tarjetaMap, 'estructura_id' => $estMap]);
        $this->insertarGrupo(TarjetaInsumoPrecio::class, $snapshot['tarjeta_insumo_precio'] ?? [], [], ['tarjeta_id' => $tarjetaMap]);

        $this->insertarGrupo(ObraInsumoOverride::class, $snapshot['obra_insumo_override'] ?? [], ['obra_id' => $obra->id]);
        $this->insertarGrupo(ObraFactorOverride::class, $snapshot['obra_factor_override'] ?? [], ['obra_id' => $obra->id]);

        $seccionMap = $this->insertarGrupo(SeccionMontaje::class, $snapshot['secciones_montaje'] ?? [], ['obra_id' => $obra->id]);
        $this->insertarGrupo(SeccionPersonal::class, $snapshot['seccion_personal'] ?? [], [], ['seccion_id' => $seccionMap]);
        $this->insertarGrupo(SeccionFaseRendimiento::class, $snapshot['seccion_fase_rendimiento'] ?? [], [], ['seccion_id' => $seccionMap]);

        $this->insertarGrupo(ObraCuadrillaGlobal::class, $snapshot['obra_cuadrilla_global'] ?? [], ['obra_id' => $obra->id]);
        $this->insertarGrupo(ObraFleteEstandar::class, $snapshot['obra_flete_estandar'] ?? [], ['obra_id' => $obra->id], ['tarjeta_id' => $tarjetaMap]);
        $this->insertarGrupo(ObraFleteViatico::class, $snapshot['obra_fletes_viaticos'] ?? [], ['obra_id' => $obra->id]);

        $columnaMap = $this->insertarGrupo(ResumenColumna::class, $snapshot['resumen_columnas'] ?? [], ['obra_id' => $obra->id]);
        $this->insertarGrupo(ResumenColumnaTarjeta::class, $snapshot['resumen_columna_tarjetas'] ?? [], [], ['columna_id' => $columnaMap, 'tarjeta_id' => $tarjetaMap]);
        $this->insertarGrupo(ObraResumenCoeficiente::class, $snapshot['obra_resumen_coeficientes'] ?? [], ['obra_id' => $obra->id]);
        $this->insertarGrupo(ObraResumenCeldaOverride::class, $snapshot['obra_resumen_celda_override'] ?? [], ['obra_id' => $obra->id], ['columna_id' => $columnaMap]);
    }

    /**
     * Inserta un grupo de filas con `forceFill` (ignora $fillable), aplicando overrides fijos
     * y remapeo de FKs internas. Devuelve el mapa id_viejo → id_nuevo.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $fijos  columnas con valor fijo (p.ej. obra_id)
     * @param  array<string, array<int, int>>  $remap  columna → mapa de IDs de un grupo previo
     * @return array<int, int> id_viejo → id_nuevo
     */
    private function insertarGrupo(string $modelClass, array $filas, array $fijos = [], array $remap = []): array
    {
        $mapa = [];
        foreach ($filas as $fila) {
            $viejoId = $fila['id'] ?? null;
            unset($fila['id']);

            foreach ($fijos as $col => $valor) {
                $fila[$col] = $valor;
            }
            foreach ($remap as $col => $idMap) {
                $valorPrevio = $fila[$col] ?? null;
                $fila[$col] = $valorPrevio !== null ? ($idMap[$valorPrevio] ?? null) : null;
            }

            $modelo = new $modelClass;
            $modelo->forceFill($fila);
            $modelo->save();

            if ($viejoId !== null) {
                $mapa[$viejoId] = $modelo->id;
            }
        }

        return $mapa;
    }
}
