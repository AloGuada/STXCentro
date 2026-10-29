<?php

namespace App\Exports\Qal;

use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las inspecciones de sublote de Registro 2.
 *
 * Mismas columnas que las de Registros —por eso hereda de ellas—, todas las
 * inspecciones y no sólo la vigente, pero filtradas por periodo en vez de por
 * un día.
 */
class Registro2SublotesExport extends SublotesExport
{
    /**
     * @param  array<string, mixed>  $filtrosConPeriodo  los de la pantalla de Registro 2
     */
    public function __construct(private readonly array $filtrosConPeriodo)
    {
        parent::__construct($filtrosConPeriodo);
    }

    public function collection(): Collection
    {
        $vigentes = $this->filtrados(Sublote::query()->ultimas())->pluck('id')->flip();

        return $this->filtrados(Sublote::query())
            ->with(['lote.obra:id,no', 'inspector.usuario:id,name', 'defectos.defecto'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (Sublote $sublote): array => [
                $sublote->fecha->toDateString(),
                $sublote->anio,
                $sublote->semana,
                $sublote->fase->value,
                $sublote->lote->obra?->no,
                $sublote->lote->marca,
                $sublote->lote->descripcion,
                $sublote->lote->total_unidades,
                $sublote->unidades,
                $sublote->numero_inspeccion,
                $vigentes->has($sublote->id) ? 'Sí' : 'No',
                $sublote->nivel->value,
                $sublote->muestra,
                $sublote->aceptacion,
                $sublote->rechazo,
                $sublote->conformes,
                $sublote->rechazadas,
                $sublote->veredicto ? mb_strtoupper($sublote->veredicto->value) : 'EN CURSO',
                $sublote->disposicion,
                $sublote->liberado() ? 'Sí' : 'No',
                $sublote->defectos
                    ->map(fn (SubloteDefecto $defecto): string => "#{$defecto->unidad} {$defecto->defecto->ambito->etiqueta()}: {$defecto->defecto->nombre}")
                    ->implode('; '),
                $sublote->linea,
                $sublote->modulo,
                $sublote->inspector?->usuario?->name,
                $sublote->observaciones,
            ]);
    }

    /**
     * @param  Builder<Sublote>  $consulta
     * @return Builder<Sublote>
     */
    private function filtrados(Builder $consulta): Builder
    {
        return $consulta
            ->filtrado($this->filtrosConPeriodo)
            ->when($this->filtrosConPeriodo['desde'] ?? null, fn (Builder $sublotes, string $desde) => $sublotes->whereDate('fecha', '>=', $desde))
            ->when($this->filtrosConPeriodo['hasta'] ?? null, fn (Builder $sublotes, string $hasta) => $sublotes->whereDate('fecha', '<=', $hasta));
    }
}
