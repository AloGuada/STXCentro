<?php

namespace App\Services\Qal\Formatos;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionDefecto;
use App\Models\Qal\InspeccionPunto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La consulta que comparten los formatos: las inspecciones de una etapa en una
 * obra, acotadas por los filtros, como filas planas con sus puntos por clave
 * (`p2_bisel`) y sus defectos por nombre.
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
            ->with(self::RELACIONES)
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

        return $consulta->with(self::RELACIONES)->get()
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
            'inspeccion' => $inspeccion->numero_inspeccion,
            'estatus' => $inspeccion->estatus->value,
            'kg' => (float) $inspeccion->kg,
            'linea' => $inspeccion->linea,
            'modulo' => $inspeccion->modulo,
            'soldador' => $inspeccion->soldador?->clave ?: $inspeccion->soldador?->nombre,
            'inspector' => $inspeccion->inspector?->usuario?->name,
            'inspector_usuario_id' => $inspeccion->inspector?->usuario_id,
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
        ];
    }

    private function numero(mixed $valor): string
    {
        $texto = (string) $valor;

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }
}
