<?php

namespace App\Exports\Sti;

use App\Models\Sti\Mantenimiento;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GanttAnualExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private Collection $datos;

    public function __construct(
        private int $year,
        private ?int $equipoId = null,
        private ?int $planId = null,
    ) {
        $this->datos = $this->buildData();
    }

    public function title(): string
    {
        return "Gantt Anual {$this->year}";
    }

    public function headings(): array
    {
        return [
            'Equipo',
            'Asignado a',
            'Departamento',
            'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
            'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic',
        ];
    }

    public function collection(): Collection
    {
        return $this->datos;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->datos->count() + 1;

        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:O{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D1:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:O{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }

    private function buildData(): Collection
    {
        $mantenimientos = Mantenimiento::query()
            ->with([
                'equipo.asignaciones' => fn ($q) => $q->where('estado', 'activo')->with('departamento:id,descripcion'),
            ])
            ->whereYear('fecha_programada', $this->year)
            ->when($this->equipoId, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($this->planId, fn ($q, $id) => $q->where('plan_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        return $mantenimientos->groupBy('equipo_id')->map(function ($mants) {
            $equipo = $mants->first()->equipo;
            $asignacion = $equipo?->asignaciones?->first();

            $row = [
                'equipo' => $equipo?->descripcion ?? '-',
                'asignado_a' => $asignacion?->empleado ?? '-',
                'departamento' => $asignacion?->departamento?->descripcion ?? '-',
            ];

            for ($mes = 1; $mes <= 12; $mes++) {
                $mantsDelMes = $mants->filter(fn ($m) => Carbon::parse($m->fecha_programada)->month === $mes);

                $row["mes_{$mes}"] = $mantsDelMes->map(function ($m) {
                    $dia = Carbon::parse($m->fecha_programada)->day;
                    $status = $m->status === 'realizado' ? 'R' : 'P';

                    return "{$dia}({$status})";
                })->implode(', ');
            }

            return $row;
        })->values();
    }
}
