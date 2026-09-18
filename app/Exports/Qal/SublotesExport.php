<?php

namespace App\Exports\Qal;

use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Las inspecciones de sublote, en crudo.
 *
 * La pantalla enseña sólo la última de cada sublote; aquí salen todas, con una
 * columna que dice cuál es la vigente. Quien exporta viene a auditar, y la
 * reinspección que se ocultó en pantalla es justo lo que quiere ver.
 */
class SublotesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filtros  los de la pantalla de Registros
     */
    public function __construct(private readonly array $filtros) {}

    public function title(): string
    {
        return 'Sublotes';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Fecha', 'Año', 'Semana', 'Transformación', 'Obra', 'Marca del lote', 'Descripción', 'Unidades del plano',
            'Unidades de la entrega', '# Inspección', 'Vigente', 'Nivel', 'Muestra', 'Aceptación', 'Rechazo',
            'Conformes', 'Rechazadas', 'Veredicto', 'Disposición', 'Liberado', 'Defectos', 'Línea', 'Módulo',
            'Inspector', 'Observaciones',
        ];
    }

    public function collection(): Collection
    {
        $vigentes = Sublote::query()->ultimas()->filtrado($this->filtros)->pluck('id')->flip();

        return Sublote::query()
            ->filtrado($this->filtros)
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
                // Con la familia delante: «Porosidad» existe en soldadura y en
                // pintura, y sin ella las dos columnas se leen igual.
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
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:X1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:X1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }
}
