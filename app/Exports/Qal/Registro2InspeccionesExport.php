<?php

namespace App\Exports\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\PuntoInspeccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las inspecciones de pieza de Registro 2.
 *
 * Mismas columnas y mismo formato que las de Registros —por eso hereda de
 * ellas—; lo único distinto es que filtra por periodo en vez de por un día.
 */
class Registro2InspeccionesExport extends InspeccionesExport
{
    /** @var Collection<int, PuntoInspeccion> */
    private Collection $puntosDelCatalogo;

    /**
     * @param  array<string, mixed>  $filtrosConPeriodo  los de la pantalla de Registro 2
     */
    public function __construct(private readonly array $filtrosConPeriodo)
    {
        parent::__construct($filtrosConPeriodo);

        $this->puntosDelCatalogo = PuntoInspeccion::query()
            ->where('ambito', AmbitoPunto::Pieza->value)
            ->orderBy('orden')
            ->get();
    }

    public function collection(): Collection
    {
        return Inspeccion::query()
            ->filtrada($this->filtrosConPeriodo)
            ->when($this->filtrosConPeriodo['desde'] ?? null, fn (Builder $consulta, string $desde) => $consulta->whereDate('fecha', '>=', $desde))
            ->when($this->filtrosConPeriodo['hasta'] ?? null, fn (Builder $consulta, string $hasta) => $consulta->whereDate('fecha', '<=', $hasta))
            ->with(['obra:id,no', 'inspector.usuario:id,name', 'tipoPieza:id,prefijo', 'puntos'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(function (Inspeccion $inspeccion): array {
                $respuestas = $inspeccion->puntos->keyBy('punto_id');

                return [
                    $inspeccion->folio,
                    $inspeccion->fecha->toDateString(),
                    $inspeccion->anio,
                    $inspeccion->semana,
                    $inspeccion->fase->value,
                    $inspeccion->subetapa?->etiqueta() ?? $inspeccion->subtipo?->etiqueta(),
                    $inspeccion->obra?->no,
                    $inspeccion->marca,
                    $inspeccion->lote,
                    $inspeccion->qr,
                    $inspeccion->consecutivo,
                    $inspeccion->cantidad_lote,
                    $inspeccion->numero_inspeccion,
                    (float) $inspeccion->kg,
                    $inspeccion->folio_strumis,
                    $inspeccion->tipoPieza?->prefijo,
                    $inspeccion->linea,
                    $inspeccion->modulo,
                    $inspeccion->inspector?->usuario?->name,
                    $inspeccion->estatus->etiqueta(),
                    $inspeccion->observaciones,
                    ...$this->puntosDelCatalogo->map(fn (PuntoInspeccion $punto) => $this->valorDe($respuestas->get($punto->id)))->all(),
                ];
            });
    }

    private function valorDe(?InspeccionPunto $respuesta): string|float|null
    {
        if ($respuesta === null) {
            return null;
        }

        return $respuesta->valor_texto ?? (float) $respuesta->valor_numerico;
    }
}
