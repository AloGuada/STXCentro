<?php

namespace App\Exports\Costos;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta la bandeja "Por confirmar" tal como se ve en pantalla: un renglón por
 * documento pendiente, con las mismas columnas. Recibe las filas ya resueltas
 * por `PuntosDeControl` para el usuario, de modo que el archivo respeta sus
 * permisos y no puede enseñar de más.
 */
class ConfirmacionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    public function __construct(private Collection $filas, private string $paso) {}

    public function title(): string
    {
        return $this->paso === 'costos' ? 'Costos' : 'Contabilidad';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tipo',
            'Folio',
            'Proveedor',
            'Nombre comercial',
            'Concepto',
            'Monto',
            'Moneda',
            'Fecha',
        ];
    }

    public function collection(): Collection
    {
        return $this->filas->map(fn (array $fila): array => [
            'tipo' => $fila['tipo'] === 'solicitud_pago' ? 'Solicitud de pago' : 'Factura',
            'folio' => $fila['folio'] ?? '—',
            'proveedor' => $fila['proveedor'] ?? '—',
            'proveedor_comercial' => $fila['proveedor_comercial'] ?? '—',
            'concepto' => $fila['concepto'] ?? '—',
            'monto' => (float) $fila['monto'],
            'moneda' => strtoupper($fila['moneda'] ?? 'mxn'),
            'fecha' => $fila['fecha'] ? date('d/m/Y', strtotime($fila['fecha'])) : '—',
        ])->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }
}
