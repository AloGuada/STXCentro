<?php

namespace App\Exports\Alm;

use App\Models\Alm\Existencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * El inventario filtrado, a Excel: un renglón por existencia con lo que pide
 * quien lo concilia afuera del sistema.
 *
 * Recibe la misma consulta que arma la pantalla, sin paginar, para que el
 * archivo traiga exactamente lo que se ve y no una versión distinta del
 * filtro. El precio es el costo promedio del renglón: lo que vale hoy cada
 * unidad de lo que hay en esa bodega.
 */
class ExistenciasExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<Existencia>  $consulta
     */
    public function __construct(private readonly Builder $consulta) {}

    public function title(): string
    {
        return 'Existencias';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Código de item',
            'Descripción',
            'Stock',
            'Nombre unidad',
            'Precio',
            'Nombre área',
        ];
    }

    public function collection(): Collection
    {
        return $this->consulta
            ->with(['articulo:id,codigo,descripcion,unidad,area_id', 'articulo.area:id,descripcion'])
            ->join('alm_articulos', 'alm_articulos.id', '=', 'alm_existencias.articulo_id')
            ->orderBy('alm_articulos.descripcion')
            ->select('alm_existencias.*')
            ->get()
            ->map(fn (Existencia $e): array => [
                'codigo' => $e->articulo?->codigo,
                'descripcion' => $e->articulo?->descripcion,
                'stock' => (float) $e->cantidad,
                'unidad' => $e->articulo?->unidad,
                'precio' => (float) $e->costo_promedio,
                'area' => $e->articulo?->area?->descripcion,
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }
}
