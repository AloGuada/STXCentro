<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use App\Models\Prod\ProcesoEvento;
use App\Models\Prod\Ubicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resuelve las filas de un CSV de producción contra el catálogo y devuelve el
 * plan de lo que se escribiría, sin escribir nada.
 *
 * Lo consumen los dos extremos del import: la revisión previa lo enseña y el
 * guardado recorre sus renglones aplicables. Sale del mismo cálculo a propósito.
 *
 * Todo lo que consulta la base se precarga antes del bucle. Un export de planta
 * trae decenas de miles de renglones y el plan se arma dos veces por import
 * (revisar y aplicar): preguntar por renglón no es viable.
 */
class PlanDeImportacionCsv
{
    /** Los `whereIn` se trocean para no rebasar el límite de parámetros del motor. */
    private const LOTE_CONSULTA = 1000;

    private const EPSILON = 0.0001;

    public function __construct(
        private readonly LectorCsvDeProduccion $lector,
        private readonly AvanceDePiezas $avance,
        private readonly ProcesosPagadosPorObra $procesos,
    ) {}

    /**
     * @param  list<array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}>  $filas
     */
    public function armar(array $filas, string $formato): PlanDeImportacion
    {
        $procesosPorEvento = ProcesoEvento::with('proceso')->get()->keyBy('evento');
        $procesosPorNombre = Proceso::activos()->get()->keyBy(
            fn (Proceso $proceso): string => $this->lector->normalizar($proceso->nombre)
        );
        $ubicaciones = $this->mapaDeUbicaciones();
        $grupos = GrupoTrabajo::query()->get()->keyBy('descripcion');
        $piezas = $this->mapaDePiezas($filas);

        $renglones = [];
        $ignorados = [];
        /** @var array<string, float> $topes */
        $topes = [];
        /** @var array<string, int> $asignadosPorModelo */
        $asignadosPorModelo = [];

        foreach ($filas as $fila) {
            $proceso = $fila['evento'] !== null
                ? $procesosPorEvento->get($fila['evento'])?->proceso
                : $procesosPorNombre->get($this->lector->normalizar((string) $fila['proceso']));

            // Los eventos que no pagan destajo (corte, inspección, embarque) son
            // la mayoría del archivo: se agregan por evento, no se listan uno a
            // uno, o el modal sería ilegible.
            if ($proceso === null && $fila['evento'] !== null) {
                $evento = (string) $fila['evento'];
                $ignorados[$evento] ??= ['evento' => $evento, 'muestra' => $fila['referencia'], 'renglones' => 0];
                $ignorados[$evento]['renglones']++;

                continue;
            }

            if ($proceso === null) {
                $renglones[] = $this->renglon($fila, 'error', 'proceso_no_encontrado', "El proceso \"{$fila['proceso']}\" no existe o está inactivo.");

                continue;
            }

            // El porcentaje se valida antes de elegir pieza porque es la entrada
            // de esa elección: sin consumo válido no hay a qué comparar el tope.
            if ($fila['porcentaje'] <= 0 || $fila['porcentaje'] > 100) {
                $renglones[] = $this->renglon($fila, 'error', 'porcentaje_invalido', 'El porcentaje debe ir de 1 a 100.', $proceso);

                continue;
            }

            [$grupo, $errorGrupo, $codigoGrupo] = $this->resolverGrupo($fila, $ubicaciones, $grupos);

            if ($grupo === null) {
                $renglones[] = $this->renglon($fila, 'error', $codigoGrupo, $errorGrupo, $proceso);

                continue;
            }

            $renglones[] = $this->resolverPieza($fila, $proceso, $grupo, $piezas, $topes, $asignadosPorModelo);
        }

        return new PlanDeImportacion(
            $renglones,
            $this->resumen($renglones, $ignorados, $formato, count($filas)),
            array_values($ignorados),
        );
    }

    /**
     * Elige la pieza del renglón y decide si cabe.
     *
     * @param  array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}  $fila
     * @param  array{qr: array<string, Collection<int, Pieza>>, qs: array<string, Collection<int, Pieza>>, marca: array<string, Collection<int, Pieza>>}  $piezas
     * @param  array<string, float>  $topes
     * @param  array<string, int>  $asignadosPorModelo
     * @return array<string, mixed>
     */
    private function resolverPieza(array $fila, Proceso $proceso, GrupoTrabajo $grupo, array $piezas, array &$topes, array &$asignadosPorModelo): array
    {
        $modo = $this->modo($fila);
        $porQr = $modo === 'qr';

        $identificador = match ($modo) {
            'qr' => "QR {$fila['qr']}",
            'qs' => "QS {$fila['qs']}",
            default => "la marca {$fila['marca']}",
        };

        $candidatas = match ($modo) {
            'qr' => $piezas['qr'][$fila['qr']] ?? collect(),
            'qs' => $piezas['qs'][$fila['qs']] ?? collect(),
            default => $piezas['marca'][$this->lector->normalizar((string) $fila['marca'])] ?? collect(),
        };

        if ($candidatas->isEmpty()) {
            return $this->renglon($fila, 'error', 'pieza_no_encontrada', "No hay ninguna pieza con {$identificador} en un catálogo vigente.", $proceso, $grupo);
        }

        $obras = $candidatas->map(fn (Pieza $pieza): int => (int) $pieza->catalogo?->obra_id)->unique();

        // Elegir "el QR más chico" entre obras distintas cargaría producción a la
        // obra equivocada, que es dinero mal repartido. Eso sigue siendo un alto.
        if ($obras->count() > 1) {
            return $this->renglon($fila, 'error', 'ambiguo_entre_obras', "Hay piezas con {$identificador} en {$obras->count()} obras; el archivo tiene que traer el QR.", $proceso, $grupo, $candidatas->count());
        }

        if ($porQr && $candidatas->count() > 1) {
            return $this->renglon($fila, 'error', 'ambiguo_entre_obras', "Hay varias piezas con {$identificador} en el mismo catálogo.", $proceso, $grupo, $candidatas->count());
        }

        $obraId = (int) $obras->first();

        if (! $this->procesos->paga($obraId, $proceso->id)) {
            return $this->renglon($fila, 'error', 'obra_no_paga_proceso', "La obra no paga el proceso \"{$proceso->nombre}\"; configúralo en la obra antes de capturar.", $proceso, $grupo, $candidatas->count());
        }

        $consumo = round($fila['porcentaje'] / 100, 4);

        // Las candidatas vienen ordenadas por QR ascendente: se llena la de QR
        // más chico antes de pasar a la siguiente, así el segundo movimiento del
        // mismo modelo cae en la pieza siguiente y no se pisan. Es lo que hace
        // que cinco renglones de la misma marca tomen los cinco QR más chicos
        // que sigan sin pagarse.
        foreach ($candidatas as $pieza) {
            $clave = $pieza->id.'|'.$proceso->id;
            $disponible = $topes[$clave] ??= $this->avance->disponible($pieza, $proceso->id);

            if ($consumo > $disponible + self::EPSILON) {
                continue;
            }

            $modeloProceso = $this->claveDeModelo($fila).'|'.$proceso->id;

            $topes[$clave] -= $consumo;
            $asignadosPorModelo[$modeloProceso] = ($asignadosPorModelo[$modeloProceso] ?? 0) + 1;

            return $this->renglon(
                $fila,
                'aplicable',
                $porQr ? 'ok' : "asignado_por_{$modo}",
                null,
                $proceso,
                $grupo,
                $candidatas->count(),
                $pieza,
                ! $porQr,
            );
        }

        return $this->sinCupo($fila, $proceso, $grupo, $candidatas, $topes, $asignadosPorModelo, $modo, $identificador);
    }

    /**
     * Con qué precisión dice el renglón de qué pieza habla. El QR señala una, el
     * QS a sus hermanas y la marca al modelo entero; se usa el más fino que
     * traiga el archivo.
     *
     * @param  array{qr: ?string, qs: string, marca: ?string}  $fila
     * @return 'qr'|'qs'|'marca'
     */
    private function modo(array $fila): string
    {
        return match (true) {
            $fila['qr'] !== null && $fila['qr'] !== '' => 'qr',
            $fila['qs'] !== '' => 'qs',
            default => 'marca',
        };
    }

    /**
     * Qué conjunto de piezas hermanas toca este renglón. Es la clave con la que
     * se cuenta cuántas van asignadas en el archivo, para distinguir un escaneo
     * repetido de un movimiento nuevo.
     *
     * @param  array{qr: ?string, qs: string, marca: ?string}  $fila
     */
    private function claveDeModelo(array $fila): string
    {
        return $this->modo($fila) === 'marca'
            ? 'marca:'.$this->lector->normalizar((string) $fila['marca'])
            : 'qs:'.$fila['qs'];
    }

    /**
     * Ninguna candidata admite el renglón. Si en este mismo archivo ya se
     * asignaron sus hermanas, lo más probable es que la pieza se escaneó dos
     * veces: se omite en vez de tratarse como error.
     *
     * @param  array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}  $fila
     * @param  Collection<int, Pieza>  $candidatas
     * @param  array<string, float>  $topes
     * @param  array<string, int>  $asignadosPorModelo
     * @param  'qr'|'qs'|'marca'  $modo
     * @return array<string, mixed>
     */
    private function sinCupo(array $fila, Proceso $proceso, GrupoTrabajo $grupo, Collection $candidatas, array $topes, array $asignadosPorModelo, string $modo, string $identificador): array
    {
        $yaAsignadas = $asignadosPorModelo[$this->claveDeModelo($fila).'|'.$proceso->id] ?? 0;

        // Sólo en el export de planta, que es el que genera la máquina y donde
        // dejamos de deduplicar. En el CSV capturado a mano los renglones los
        // escribió alguien a propósito: uno que no cabe es un error que atender.
        if ($modo !== 'qr' && $yaAsignadas > 0 && $fila['evento'] !== null) {
            return $this->renglon(
                $fila,
                'omitida',
                'duplicado',
                "Ya se asignaron {$yaAsignadas} pieza(s) con {$identificador} en este archivo y ninguna otra tiene cupo; este movimiento parece el mismo escaneo repetido.",
                $proceso,
                $grupo,
                $candidatas->count(),
            );
        }

        // Se explica con la candidata que más margen tiene: "sólo le falta 40%"
        // es accionable, "ya está al 100%" cuando otra tenía cupo, no.
        $conMasMargen = $candidatas->sortByDesc(
            fn (Pieza $pieza): float => $topes[$pieza->id.'|'.$proceso->id] ?? $this->avance->disponible($pieza, $proceso->id)
        )->first();

        $disponible = $topes[$conMasMargen->id.'|'.$proceso->id] ?? $this->avance->disponible($conMasMargen, $proceso->id);
        $motivo = $this->avance->mensajeDeTope($conMasMargen, $proceso, $disponible);

        if ($candidatas->count() > 1) {
            $motivo = "Ninguna de las {$candidatas->count()} piezas con {$identificador} tiene cupo: ".$motivo;
        }

        return $this->renglon($fila, 'error', 'sin_tope', $motivo, $proceso, $grupo, $candidatas->count());
    }

    /**
     * @param  array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}  $fila
     * @param  array<string, GrupoTrabajo|string>  $ubicaciones
     * @param  Collection<string, GrupoTrabajo>  $grupos
     * @return array{0: GrupoTrabajo|null, 1: string|null, 2: string}
     */
    private function resolverGrupo(array $fila, array $ubicaciones, Collection $grupos): array
    {
        if ($fila['ubicacion'] === null) {
            $grupo = $grupos->get((string) $fila['grupo']);

            return $grupo !== null
                ? [$grupo, null, 'ok']
                : [null, "El grupo \"{$fila['grupo']}\" no existe.", 'grupo_no_encontrado'];
        }

        $resuelta = $ubicaciones[$this->lector->normalizar($fila['ubicacion'])] ?? null;

        if ($resuelta === null) {
            return [null, "La ubicación \"{$fila['ubicacion']}\" no está en el catálogo de módulos.", 'ubicacion_desconocida'];
        }

        if (is_string($resuelta)) {
            return [null, $resuelta, str_contains($resuelta, 'varios grupos') ? 'ubicacion_multi_grupo' : 'ubicacion_sin_grupo'];
        }

        return [$resuelta, null, 'ok'];
    }

    /**
     * Ubicación normalizada => su grupo, o el motivo por el que no sirve. Una
     * sola consulta: antes se traía el catálogo entero por cada renglón.
     *
     * @return array<string, GrupoTrabajo|string>
     */
    private function mapaDeUbicaciones(): array
    {
        $mapa = [];

        foreach (Ubicacion::with('gruposTrabajo:id,descripcion')->get(['id', 'nombre']) as $ubicacion) {
            $grupos = $ubicacion->gruposTrabajo;

            $mapa[$this->lector->normalizar($ubicacion->nombre)] = match (true) {
                $grupos->isEmpty() => "La ubicación \"{$ubicacion->nombre}\" no tiene ningún grupo de trabajo asignado.",
                $grupos->count() > 1 => "La ubicación \"{$ubicacion->nombre}\" la trabajan varios grupos ({$grupos->pluck('descripcion')->implode(', ')}).",
                default => $grupos->first(),
            };
        }

        return $mapa;
    }

    /**
     * Todas las piezas que el archivo menciona, en una consulta, agrupadas por
     * QR, por QS y por marca. Cada grupo se ordena aquí una sola vez: es el
     * orden en el que se van a ir asignando.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{qr: array<string, Collection<int, Pieza>>, qs: array<string, Collection<int, Pieza>>, marca: array<string, Collection<int, Pieza>>}
     */
    private function mapaDePiezas(array $filas): array
    {
        $qrs = array_values(array_unique(array_filter(array_column($filas, 'qr'))));
        $qss = array_values(array_unique(array_filter(array_column($filas, 'qs'))));
        $marcas = array_values(array_unique(array_filter(array_column($filas, 'marca'))));

        $piezas = collect();

        foreach (array_chunk($qrs, self::LOTE_CONSULTA) as $lote) {
            $piezas = $piezas->concat($this->consultarPiezas('qr', $lote));
        }

        foreach (array_chunk($qss, self::LOTE_CONSULTA) as $lote) {
            $piezas = $piezas->concat($this->consultarPiezas('qs', $lote));
        }

        foreach (array_chunk($marcas, self::LOTE_CONSULTA) as $lote) {
            $piezas = $piezas->concat($this->piezasDeMarcas($lote));
        }

        $piezas = $piezas->unique('id');

        return [
            'qr' => $piezas->groupBy('qr')->map(fn (Collection $grupo): Collection => $this->porQrAscendente($grupo))->all(),
            'qs' => $piezas->filter(fn (Pieza $pieza): bool => $pieza->qs !== null && $pieza->qs !== '')
                ->groupBy('qs')->map(fn (Collection $grupo): Collection => $this->porQrAscendente($grupo))->all(),
            // Todos los lotes de la marca caen en el mismo saco a propósito: el
            // archivo no dice lote, y el orden por QR ya reparte de menor a
            // mayor sin importar en qué lote quedó cada pieza.
            'marca' => $piezas->filter(fn (Pieza $pieza): bool => $pieza->marca !== null)
                ->groupBy(fn (Pieza $pieza): string => $this->lector->normalizar((string) $pieza->marca->marca))
                ->map(fn (Collection $grupo): Collection => $this->porQrAscendente($grupo))->all(),
        ];
    }

    /**
     * @param  list<string>  $valores
     * @return Collection<int, Pieza>
     */
    private function consultarPiezas(string $columna, array $valores): Collection
    {
        return Pieza::query()
            ->with(['marca:id,marca,lote', 'catalogo:id,obra_id,vigente'])
            ->deCatalogoVigente()
            ->where('activo', true)
            ->whereIn($columna, $valores)
            ->get();
    }

    /**
     * Las piezas de esos modelos. La marca vive en el concepto, no en la pieza,
     * así que se filtra por la relación.
     *
     * @param  list<string>  $marcas
     * @return Collection<int, Pieza>
     */
    private function piezasDeMarcas(array $marcas): Collection
    {
        return Pieza::query()
            ->with(['marca:id,marca,lote', 'catalogo:id,obra_id,vigente'])
            ->deCatalogoVigente()
            ->where('activo', true)
            ->whereHas('marca', fn (Builder $query) => $query->whereIn('marca', $marcas))
            ->get();
    }

    /**
     * Orden natural, no alfabético: el QR es texto, y alfabéticamente "10" va
     * antes que "9".
     *
     * @param  Collection<int, Pieza>  $piezas
     * @return Collection<int, Pieza>
     */
    private function porQrAscendente(Collection $piezas): Collection
    {
        return $piezas
            ->sort(fn (Pieza $a, Pieza $b): int => strnatcasecmp((string) $a->qr, (string) $b->qr))
            ->values();
    }

    /**
     * @param  array{referencia: string, linea: int, ubicacion: ?string, grupo: ?string, qr: ?string, qs: string, marca: ?string, evento: ?string, proceso: ?string, porcentaje: float}  $fila
     * @return array<string, mixed>
     */
    private function renglon(
        array $fila,
        string $estado,
        string $codigo,
        ?string $motivo,
        ?Proceso $proceso = null,
        ?GrupoTrabajo $grupo = null,
        ?int $candidatas = null,
        ?Pieza $pieza = null,
        bool $porQs = false,
    ): array {
        return [
            'referencia' => $fila['referencia'],
            'linea' => $fila['linea'] ?? null,
            'estado' => $estado,
            'codigo' => $codigo,
            'motivo' => $motivo,
            'pieza_id' => $pieza?->id,
            'qr' => $pieza?->qr ?? $fila['qr'],
            'qs' => $fila['qs'] !== '' ? $fila['qs'] : null,
            // Si no se resolvió pieza se enseña la marca que traía el archivo:
            // sin eso, el renglón con problema sale sin nada que lo identifique.
            'marca' => $pieza?->marca !== null
                ? Concepto::etiquetaDeModelo($pieza->marca->marca, $pieza->marca->lote)
                : $fila['marca'],
            'proceso' => $proceso?->nombre,
            'proceso_id' => $proceso?->id,
            'grupo' => $grupo?->descripcion,
            'grupo_trabajo_id' => $grupo?->id,
            'porcentaje' => $fila['porcentaje'],
            'por_qs' => $porQs,
            'asignado_por' => $this->modo($fila),
            'candidatas' => $candidatas,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $renglones
     * @param  array<string, array{evento: string, muestra: string, renglones: int}>  $ignorados
     * @return array<string, int|string>
     */
    private function resumen(array $renglones, array $ignorados, string $formato, int $filasLeidas): array
    {
        $estados = array_count_values(array_column($renglones, 'estado'));

        return [
            'formato' => $formato,
            'filas_leidas' => $filasLeidas,
            'aplicables' => $estados['aplicable'] ?? 0,
            'omitidas' => $estados['omitida'] ?? 0,
            'errores' => $estados['error'] ?? 0,
            // Los que el archivo no numeró y eligió el sistema, sea porque sólo
            // traía QS o porque sólo traía marca. Son los que hay que revisar.
            'asignadas_por_sistema' => count(array_filter($renglones, fn (array $r): bool => $r['por_qs'] && $r['estado'] === 'aplicable')),
            'ignorados_por_evento' => array_sum(array_column($ignorados, 'renglones')),
        ];
    }
}
