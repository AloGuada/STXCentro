<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Media;
use App\Models\Qal\Adherencia;
use App\Models\Qal\AdherenciaTira;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionDefecto;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\Pintura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La consulta que comparten los formatos: las inspecciones de una etapa en una
 * obra, acotadas por los filtros, como filas planas con sus puntos por clave
 * (`p2_bisel`) y sus defectos por nombre. En 1ª traen además equipo, operador y
 * muestreo; en pintura, los espesores y la adherencia.
 *
 * Trae aparte el universo —todas las inspecciones de esas mismas piezas en la
 * etapa, estén o no dentro del periodo—, porque la hoja de revisión tiene que
 * ver el rechazo de la semana pasada de una pieza que se liberó ésta.
 */
class FilasDeReporte
{
    private const RELACIONES = [
        'pieza:id,qs', 'soldador:id,nombre,clave', 'inspector.usuario:id,name', 'puntos.punto:id,clave', 'defectos.defecto:id,nombre',
    ];

    /**
     * @return array{filas: Collection<int, array<string, mixed>>, universo: Collection<int, array<string, mixed>>, total: int}
     */
    public function de(FaseTransformacion $fase, ?Subetapa $subetapa, FiltrosDeReporte $filtros): array
    {
        $filas = $this->acotada($this->base($fase, $subetapa, $filtros->obraId), $filtros)
            ->with($this->relaciones($fase))
            ->get()
            ->map(fn (Inspeccion $inspeccion): array => $this->fila($inspeccion));

        return [
            'filas' => $this->ordenadas($filas),
            'universo' => $filtros->vista === 'historico' ? $filas : $this->universo($fase, $subetapa, $filtros->obraId, $filas),
            'total' => $this->base($fase, $subetapa, $filtros->obraId)->count(),
        ];
    }

    /**
     * Marca en orden natural, luego consecutivo y nº de inspección: así la
     * misma pieza queda junta y la hoja se lee de corrido.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    public function ordenadas(Collection $filas): Collection
    {
        return $filas->sort(fn (array $a, array $b): int => strnatcasecmp($a['marca'], $b['marca'])
            ?: ((int) $a['consecutivo'] <=> (int) $b['consecutivo'])
            ?: ($a['inspeccion'] <=> $b['inspeccion'])
            ?: ($a['id'] <=> $b['id']))->values();
    }

    /**
     * @return list<string>
     */
    private function relaciones(FaseTransformacion $fase): array
    {
        return [...self::RELACIONES, ...match ($fase) {
            FaseTransformacion::Primera => ['equipo:id,nombre', 'operador:id,nombre', 'tipoPieza:id,prefijo,descripcion', 'muestreo'],
            FaseTransformacion::Tercera => ['pintura.lecturas', 'adherencia.tiras', 'adherencia.fotos'],
            default => [],
        }];
    }

    /**
     * @return Builder<Inspeccion>
     */
    private function base(FaseTransformacion $fase, ?Subetapa $subetapa, int $obraId): Builder
    {
        return Inspeccion::query()
            ->where('obra_id', $obraId)
            ->where('fase', $fase->value)
            ->when($subetapa, fn (Builder $consulta, Subetapa $etapa) => $consulta->where('subetapa', $etapa->value));
    }

    /**
     * «Liberadas» es salir bien: en armado y vestido eso es quedar pendiente,
     * porque la pieza pasa a soldado.
     *
     * @param  Builder<Inspeccion>  $consulta
     * @return Builder<Inspeccion>
     */
    private function acotada(Builder $consulta, FiltrosDeReporte $filtros): Builder
    {
        $armado = Subetapa::ArmadoVestido->value;

        return $consulta
            ->when($filtros->periodo === 'dia' && $filtros->fecha, fn (Builder $q) => $q->whereDate('fecha', $filtros->fecha))
            ->when($filtros->anioYSemana(), fn (Builder $q, array $semana) => $q->where('anio', $semana[0])->where('semana', $semana[1]))
            ->when($filtros->inspectorId, fn (Builder $q, int $inspector) => $q->where('inspector_id', $inspector))
            ->when($filtros->estatus === 'liberadas', fn (Builder $q) => $q->where(fn (Builder $bien) => $bien
                ->where('estatus', 'liberado')
                ->orWhere(fn (Builder $enArmado) => $enArmado->where('subetapa', $armado)->where('estatus', 'pendiente'))))
            ->when($filtros->estatus === 'rechazadas', fn (Builder $q) => $q->where('estatus', 'rechazado'))
            ->when($filtros->estatus === 'pendientes', fn (Builder $q) => $q->where('estatus', 'pendiente')
                ->where(fn (Builder $fuera) => $fuera->whereNull('subetapa')->orWhere('subetapa', '!=', $armado)));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function universo(FaseTransformacion $fase, ?Subetapa $subetapa, int $obraId, Collection $filas): Collection
    {
        if ($filas->isEmpty()) {
            return $filas;
        }

        $consulta = $this->base($fase, $subetapa, $obraId);
        $consulta = $fase === FaseTransformacion::Primera
            ? $consulta->whereIn('marca', $filas->pluck('marca')->unique()->all())
            : $consulta->whereIn('qr', $filas->pluck('qr')->filter()->unique()->all());
        $piezas = $filas->pluck('pieza')->flip();

        return $consulta->with($this->relaciones($fase))->get()
            ->map(fn (Inspeccion $inspeccion): array => $this->fila($inspeccion))
            ->filter(fn (array $fila): bool => $piezas->has($fila['pieza']))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Inspeccion $inspeccion): array
    {
        $pieza = $inspeccion->fase === FaseTransformacion::Primera
            ? "{$inspeccion->marca}|{$inspeccion->lote}|{$inspeccion->consecutivo}"
            : (string) $inspeccion->qr;
        $cargada = fn (string $relacion) => $inspeccion->relationLoaded($relacion) ? $inspeccion->getRelation($relacion) : null;
        $muestreo = $cargada('muestreo');

        return [
            'id' => $inspeccion->id,
            'folio' => $inspeccion->folio,
            'fecha' => $inspeccion->fecha->toDateString(),
            'marca' => $inspeccion->marca,
            'lote' => $inspeccion->lote,
            'qr' => $inspeccion->qr,
            'consecutivo' => $inspeccion->pieza?->qs ?? $inspeccion->consecutivo,
            'pieza' => $pieza,
            'etapa' => $pieza.'|'.($inspeccion->subetapa?->value ?? ''),
            'subetapa' => $inspeccion->subetapa?->value,
            'subtipo' => $inspeccion->subtipo?->etiqueta(),
            'inspeccion' => $inspeccion->numero_inspeccion,
            'estatus' => $inspeccion->estatus->value,
            'kg' => (float) $inspeccion->kg,
            'cantidad_lote' => $inspeccion->cantidad_lote,
            'linea' => $inspeccion->linea,
            'modulo' => $inspeccion->modulo,
            'soldador' => $inspeccion->soldador?->clave ?: $inspeccion->soldador?->nombre,
            'inspector' => $inspeccion->inspector?->usuario?->name,
            'inspector_usuario_id' => $inspeccion->inspector?->usuario_id,
            'equipo' => $cargada('equipo')?->nombre,
            'operador' => $cargada('operador')?->nombre,
            'tipo' => $cargada('tipoPieza')?->prefijo,
            'observaciones' => trim((string) $inspeccion->observaciones),
            'puntos' => $inspeccion->puntos
                ->mapWithKeys(fn (InspeccionPunto $respuesta): array => [$respuesta->punto->clave => [
                    'resultado' => $respuesta->resultado?->value,
                    'valor' => $respuesta->valor_texto ?? ($respuesta->valor_numerico === null ? null : $this->numero($respuesta->valor_numerico)),
                    'numero' => $respuesta->valor_numerico === null ? null : (float) $respuesta->valor_numerico,
                ]])
                ->all(),
            'defectos' => $inspeccion->defectos
                ->mapWithKeys(fn (InspeccionDefecto $defecto): array => [$defecto->defecto->nombre => (int) $defecto->cantidad])
                ->all(),
            'muestreo' => $muestreo ? [
                'lote' => $muestreo->tamano_lote,
                'muestra' => $muestreo->muestra,
                'rechazadas' => $muestreo->rechazadas,
                'veredicto' => $muestreo->veredicto?->value,
            ] : null,
            'pintura' => ($pintura = $cargada('pintura')) ? $this->pintura($pintura) : null,
            'adherencia' => ($adherencia = $cargada('adherencia')) ? $this->adherencia($adherencia) : null,
        ];
    }

    /**
     * Cada medición es el promedio de sus lecturas del calibre (SSPC-PA2), y
     * el espesor de la pieza el promedio de sus mediciones.
     *
     * @return array{requerido: float|null, promedio: float|null, cumple: bool|null, metodo: string|null, area: float|null, mediciones: array<int, float>}
     */
    private function pintura(Pintura $pintura): array
    {
        $mediciones = $pintura->lecturas
            ->groupBy('medicion')
            ->sortKeys()
            ->map(fn (Collection $lecturas): float => round($lecturas->avg(fn ($lectura): float => (float) $lectura->valor_mils), 2))
            ->all();

        $promedio = $pintura->promedio_mils !== null
            ? (float) $pintura->promedio_mils
            : ($mediciones === [] ? null : round(array_sum($mediciones) / count($mediciones), 2));

        return [
            'requerido' => $pintura->espesor_requerido_mils === null ? null : (float) $pintura->espesor_requerido_mils,
            'promedio' => $promedio,
            'cumple' => $pintura->cumple,
            'metodo' => $pintura->metodo,
            'area' => $pintura->area_m2 === null ? null : (float) $pintura->area_m2,
            'mediciones' => $mediciones,
        ];
    }

    /**
     * @return array{resultado: string|null, tiras: array<int, array{metodo: string, clasificacion: string}>, fotos: list<array{path: string, nombre: string|null, imagen: bool}>}
     */
    private function adherencia(Adherencia $adherencia): array
    {
        return [
            'resultado' => $adherencia->resultado,
            'tiras' => $adherencia->tiras
                ->sortBy('orden')
                ->mapWithKeys(fn (AdherenciaTira $tira): array => [
                    $tira->orden => ['metodo' => $tira->metodo, 'clasificacion' => $tira->clasificacion],
                ])
                ->all(),
            'fotos' => $adherencia->fotos->map(fn (Media $foto): array => [
                'path' => $foto->path,
                'nombre' => $foto->nombre_original,
                'imagen' => str_starts_with((string) $foto->mime, 'image/'),
            ])->values()->all(),
        ];
    }

    private function numero(mixed $valor): string
    {
        $texto = (string) $valor;

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }
}
