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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * La tabla de Existencias tal como se ve, a Excel: una sola hoja con las
 * mismas columnas de la pantalla y todo lo filtrado, sin paginar.
 *
 * Es el botón «Excel» a secas. El otro archivo, {@see ExistenciasExport}, es
 * el inventario por almacén con el detalle de lo prestado; éste es sólo para
 * llevarse la tabla a donde se pueda ordenar y sumar.
 *
 * Lo que anda en resguardo sigue dentro de la existencia —la pantalla lo dice
 * en la misma celda— y aquí va en su columna. «En camino» sólo se conoce con
 * un almacén elegido, igual que en pantalla: es lo que viene hacia esa bodega.
 */
class ExistenciasTablaExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Builder<Existencia>  $consulta
     * @param  array<int, float>  $enTransito  artículo => cantidad que viene en camino
     */
    public function __construct(private readonly Builder $consulta, private readonly array $enTransito = []) {}

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
            'Almacén',
            'Obra',
            'Código',
            'Descripción',
            'Tipo',
            'Área',
            'Unidad',
            'Ubicación',
            'Existencia',
            'En resguardo',
            'Asignado a',
            'En camino',
            'Costo promedio',
            'Valor',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collection(): Collection
    {
        return (clone $this->consulta)
            ->with([
                'almacen:id,clave,nombre,obra_id',
                'almacen.obra:id,no',
                'articulo:id,codigo,descripcion,unidad,tipo,area_id',
                'articulo.area:id,descripcion',
                'ubicacion.padre',
                'asignaciones.obra:id,no',
            ])
            ->join('alm_articulos', 'alm_articulos.id', '=', 'alm_existencias.articulo_id')
            ->orderBy('alm_articulos.descripcion')
            ->select('alm_existencias.*')
            ->get()
            ->map(fn (Existencia $e): array => $this->renglon($e))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function renglon(Existencia $e): array
    {
        $asignado = $e->asignaciones
            ->filter(fn ($a): bool => (float) $a->cantidad > 0)
            ->map(fn ($a): string => ($a->obra?->no ?? '—').' ('.$this->numero((float) $a->cantidad).')')
            ->implode("\n");

        return [
            'almacen' => $e->almacen?->clave,
            'obra' => $e->almacen?->obra?->no,
            'codigo' => $e->articulo?->codigo,
            'descripcion' => $e->articulo?->descripcion,
            'tipo' => $e->articulo?->tipo?->etiqueta(),
            'area' => $e->articulo?->area?->descripcion,
            'unidad' => $e->articulo?->unidad,
            'ubicacion' => $e->ubicacion?->ruta(),
            'existencia' => (float) $e->cantidad,
            'prestado' => (float) $e->prestado,
            'asignado_a' => $asignado === '' ? null : $asignado,
            'en_transito' => (float) ($this->enTransito[(int) $e->articulo_id] ?? 0),
            'costo_promedio' => (float) $e->costo_promedio,
            'valor' => (float) $e->valor,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('K:K')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A:N')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [];
    }

    /** La cantidad sin ceros de relleno: «2», no «2.0000». */
    private function numero(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 4, '.', ','), '0'), '.');
    }
}
