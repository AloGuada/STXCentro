<?php

namespace App\Exports\Costos;

use App\Models\Costos\SolicitudPago;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de solicitudes de pago del reporte, con las mismas columnas que la
 * primera tabla del PDF.
 */
class SolicitudesPagoSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, SolicitudPago>  $solicitudes
     */
    public function __construct(private Collection $solicitudes) {}

    public function title(): string
    {
        return 'Solicitudes de pago';
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
            'Proveedor',
            'Concepto',
            'Total',
            'Moneda',
            'Estatus',
            'Fecha',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        return $this->solicitudes->values()->map(fn (SolicitudPago $sol, int $i) => [
            'num' => $i + 1,
            'folio' => $sol->folio,
            'solicitante' => $sol->solicitante?->name ?? '-',
            'departamento' => $sol->departamento?->descripcion ?? '-',
            'proveedor' => $sol->proveedor?->razon_social ?? '-',
            'concepto' => $sol->concepto,
            'total' => (float) $sol->monto_total,
            'moneda' => strtoupper($sol->tipo_moneda ?? 'mxn'),
            'estatus' => $sol->estatus->label(),
            'fecha' => $sol->created_at?->format('d/m/Y') ?? '-',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $ultima = $this->solicitudes->count() + 1;

        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        if ($ultima > 1) {
            $sheet->getStyle("G2:G{$ultima}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        return [];
    }
}
