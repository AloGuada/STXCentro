<?php

namespace App\Exports\Costos;

use App\Models\Costos\Requisicion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de requisiciones del reporte, con las mismas columnas que la segunda
 * tabla del PDF.
 */
class RequisicionesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, Requisicion>  $requisiciones
     */
    public function __construct(private Collection $requisiciones) {}

    public function title(): string
    {
        return 'Requisiciones';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            '#',
            'Folio',
            'Solicitante',
            'Departamento',
            'Estatus',
            'Fecha requerida',
            'Creada',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        return $this->requisiciones->values()->map(fn (Requisicion $req, int $i) => [
            'num' => $i + 1,
            'folio' => $req->folio,
            'solicitante' => $req->solicitante?->name ?? '-',
            'departamento' => $req->departamento?->descripcion ?? '-',
            'estatus' => $req->estatus->label(),
            'fecha_requerida' => $req->fecha_requerida?->format('d/m/Y') ?? '-',
            'creada' => $req->created_at?->format('d/m/Y') ?? '-',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }
}
