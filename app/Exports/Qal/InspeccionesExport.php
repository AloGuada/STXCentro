<?php

namespace App\Exports\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\PuntoInspeccion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * La base de inspecciones de pieza, en crudo: un renglón por inspección y una
 * columna por punto del catálogo.
 *
 * Es lo que se exporta para revisar en Excel, así que lleva todos los puntos y
 * no sólo los que caben en la pantalla. La columna de cada punto lleva su clave
 * entre paréntesis porque es la del formato y la de la aplicación anterior: con
 * ella se cruza contra lo que ya estaba en hojas de cálculo.
 */
class InspeccionesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private const FIJAS = [
        'Folio', 'Fecha', 'Año', 'Semana', 'Fase', 'Sub-etapa / tipo', 'Obra', 'Marca', 'Lote', 'QR',
        '# de pieza', 'Piezas en el lote', '# Inspección', 'Kg', 'Folio Strumis', 'Tipo de pieza', 'Línea',
        'Módulo', 'Inspector', 'Estatus', 'Observaciones',
    ];

    /** @var Collection<int, PuntoInspeccion> */
    private Collection $puntos;

    /**
     * @param  array<string, mixed>  $filtros  los de la pantalla de Registros
     */
    public function __construct(private readonly array $filtros)
    {
        $this->puntos = PuntoInspeccion::query()
            ->where('ambito', AmbitoPunto::Pieza->value)
            ->orderBy('orden')
            ->get();
    }

    public function title(): string
    {
        return 'Inspecciones';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            ...self::FIJAS,
            ...$this->puntos->map(fn (PuntoInspeccion $punto): string => "{$punto->etiqueta} ({$punto->clave})")->all(),
        ];
    }

    public function collection(): Collection
    {
        return Inspeccion::query()
            ->filtrada($this->filtros)
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
                    ...$this->puntos->map(fn (PuntoInspeccion $punto) => $this->respuesta($respuestas->get($punto->id)))->all(),
                ];
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        $rango = 'A1:'.Coordinate::stringFromColumnIndex(count($this->headings())).'1';
        $sheet->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        return [];
    }

    private function respuesta(?InspeccionPunto $respuesta): string|float|null
    {
        if ($respuesta === null) {
            return null;
        }

        return $respuesta->valor_texto ?? (float) $respuesta->valor_numerico;
    }
}
