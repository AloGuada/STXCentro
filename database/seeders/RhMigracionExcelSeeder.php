<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Rh\DatosExtra;
use App\Models\Rh\PeriodoLaboral;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RhMigracionExcelSeeder extends Seeder
{
    private const EXCEL_PATH = 'Copia de migracion_rh_pocketbase.xlsx';

    private array $departamentoMap = [];

    private array $puestoMap = [];

    /** @var array<string, int> Mapa numero_empleado → persona_id */
    private array $empleadoPersonaMap = [];

    private int $personasCreadas = 0;

    private int $personasActualizadas = 0;

    private int $periodosCreados = 0;

    private int $periodosActualizados = 0;

    private int $historicosCreados = 0;

    private array $curpsInvalidos = [];

    private array $departamentosNoMapeados = [];

    private array $puestosNoMapeados = [];

    public function run(): void
    {
        $path = base_path(self::EXCEL_PATH);

        if (! file_exists($path)) {
            $this->command->warn('Archivo Excel no encontrado: '.self::EXCEL_PATH);
            $this->command->info('Coloca el archivo en la raíz del proyecto para ejecutar la migración de datos.');

            return;
        }

        $this->command->info('Cargando archivo Excel...');
        $spreadsheet = IOFactory::load($path);

        $this->buildDepartamentoMap();
        $this->buildPuestoMap();

        $this->command->info('Importando empleados ACTIVOS...');
        $this->processPersonasSheet($spreadsheet->getSheet(1), 'activo');

        $this->command->info('Importando empleados BAJAS...');
        $this->processPersonasSheet($spreadsheet->getSheet(2), 'baja');

        $this->command->info('Importando periodos laborales históricos...');
        $this->processHistoricosSheet($spreadsheet->getSheet(3));

        $this->printSummary();
    }

    private function buildDepartamentoMap(): void
    {
        $departamentos = Departamento::all();

        foreach ($departamentos as $dep) {
            $normalized = $this->normalize($dep->descripcion);
            $this->departamentoMap[$normalized] = $dep->id;
        }

        $aliases = [
            'contabilidad' => 'administracion',
            'nomina' => 'administracion',
            't1' => 'produccion',
            't2' => 'produccion',
            't1-habilitado' => 'produccion',
            't1-placas' => 'produccion',
            't1-perfiles' => 'produccion',
            't1-maquinado' => 'produccion',
            'produccion-administracion' => 'produccion',
            'pintura' => 'produccion',
            'montaje' => 'produccion',
            'escuela de soldadura' => 'produccion',
            'produccionn' => 'produccion',
            'sistemas' => 'administracion',
            'proyectos' => 'ingenieria',
        ];

        foreach ($aliases as $alias => $target) {
            if (isset($this->departamentoMap[$target])) {
                $this->departamentoMap[$alias] = $this->departamentoMap[$target];
            }
        }
    }

    private function buildPuestoMap(): void
    {
        $puestos = Puesto::all();

        foreach ($puestos as $puesto) {
            $normalized = $this->normalize($puesto->nombre);
            $this->puestoMap[$normalized] = $puesto->id;
        }
    }

    private function processPersonasSheet(Worksheet $sheet, string $estado): void
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $noEmpleado = trim((string) $sheet->getCell('A'.$row)->getValue());
            if (! $noEmpleado) {
                continue;
            }

            $nombre = $this->cleanString($sheet->getCell('B'.$row)->getValue());
            $apellido = $this->cleanString($sheet->getCell('C'.$row)->getValue());

            if (! $nombre && ! $apellido) {
                continue;
            }

            $persona = Persona::where('nombre', $nombre)
                ->where('apellido', $apellido)
                ->first();

            $personaData = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $this->cleanString($sheet->getCell('D'.$row)->getValue()) ?: null,
                'telefono' => $this->cleanString($sheet->getCell('E'.$row)->getValue()) ?: null,
                'fecha_nacimiento' => $this->parseDate($sheet->getCell('F'.$row)->getValue()),
            ];

            if ($persona) {
                $persona->update(array_filter($personaData, fn ($v) => $v !== null));
                $this->personasActualizadas++;
            } else {
                $persona = Persona::create($personaData);
                $this->personasCreadas++;
            }

            $this->empleadoPersonaMap[$noEmpleado] = $persona->id;

            $this->upsertDatosExtra($sheet, $row, $persona->id);

            $this->upsertPeriodoActual($sheet, $row, $persona->id, $noEmpleado, $estado);
        }
    }

    private function upsertDatosExtra(Worksheet $sheet, int $row, int $personaId): void
    {
        $curp = $this->cleanString($sheet->getCell('U'.$row)->getValue());
        if ($curp && str_contains(strtoupper($curp), 'UNDEFINED')) {
            $this->curpsInvalidos[] = $curp;
            $curp = str_replace(['UNDEFINED', 'undefined'], '', $curp) ?: null;
        }

        $hijos = $sheet->getCell('H'.$row)->getValue();
        $hijosInt = null;
        if ($hijos !== null && $hijos !== '') {
            $hijosInt = (strtolower(trim((string) $hijos)) === 'no' || $hijos === '0') ? 0 : (is_numeric($hijos) ? (int) $hijos : 1);
        }

        $cInfonavit = $sheet->getCell('P'.$row)->getValue();
        $pagoInfonavit = $sheet->getCell('Q'.$row)->getValue();
        $infonavitVal = null;
        if ($cInfonavit !== null && strtolower(trim((string) $cInfonavit)) !== 'no' && $cInfonavit !== '0') {
            $infonavitVal = $pagoInfonavit ? (string) $pagoInfonavit : 'Si';
        }

        $cFonacot = $sheet->getCell('R'.$row)->getValue();
        $pagoFonacot = $sheet->getCell('S'.$row)->getValue();
        $fonacotVal = null;
        if ($cFonacot !== null && strtolower(trim((string) $cFonacot)) !== 'no' && $cFonacot !== '0') {
            $fonacotVal = $pagoFonacot ? (string) $pagoFonacot : 'Si';
        }

        $estadoCivil = $this->cleanString($sheet->getCell('G'.$row)->getValue());

        DatosExtra::updateOrCreate(
            ['persona_id' => $personaId],
            [
                'estado_civil' => $estadoCivil ? strtolower($estadoCivil) : null,
                'hijos' => $hijosInt,
                'localidad' => $this->cleanString($sheet->getCell('I'.$row)->getValue()) ?: null,
                'domicilio' => $this->cleanString($sheet->getCell('J'.$row)->getValue()) ?: null,
                'cp' => $this->cleanString($sheet->getCell('K'.$row)->getValue()) ?: null,
                'nombre_padre' => $this->cleanString($sheet->getCell('L'.$row)->getValue()) ?: null,
                'nombre_madre' => $this->cleanString($sheet->getCell('M'.$row)->getValue()) ?: null,
                'cuenta_banco' => $this->cleanString($sheet->getCell('N'.$row)->getValue()) ?: null,
                'banco_op' => $this->cleanString($sheet->getCell('O'.$row)->getValue()) ?: null,
                'c_infonavit' => $infonavitVal,
                'c_fonacot' => $fonacotVal,
                'imss' => $this->cleanString($sheet->getCell('T'.$row)->getValue()) ?: null,
                'curp' => $curp ?: null,
                'rfc' => $this->cleanString($sheet->getCell('V'.$row)->getValue()) ?: null,
            ]
        );
    }

    private function upsertPeriodoActual(Worksheet $sheet, int $row, int $personaId, string $noEmpleado, string $estado): void
    {
        $departamento = $this->cleanString($sheet->getCell('W'.$row)->getValue());
        $puestoExcel = $this->cleanString($sheet->getCell('X'.$row)->getValue());

        $departamentoId = null;
        if ($departamento) {
            $normalized = $this->normalize($departamento);
            $departamentoId = $this->departamentoMap[$normalized] ?? null;
            if (! $departamentoId && ! in_array($normalized, $this->departamentosNoMapeados)) {
                $this->departamentosNoMapeados[] = $normalized;
            }
        }

        $puestoId = null;
        if ($puestoExcel) {
            $normalized = $this->normalize($puestoExcel);
            $puestoId = $this->puestoMap[$normalized] ?? null;
            if (! $puestoId && ! in_array($normalized, $this->puestosNoMapeados)) {
                $this->puestosNoMapeados[] = $normalized;
            }
        }

        $sueldo = $sheet->getCell('AC'.$row)->getValue();
        $sueldoDecimal = null;
        if ($sueldo !== null && is_numeric($sueldo) && (float) $sueldo > 0) {
            $sueldoDecimal = (float) $sueldo;
        }

        $fechaBaja = $this->parseDate($sheet->getCell('AF'.$row)->getValue());

        $periodo = PeriodoLaboral::where('numero_empleado', $noEmpleado)
            ->where('persona_id', $personaId)
            ->first();

        $periodoData = [
            'persona_id' => $personaId,
            'puesto_id' => $puestoId,
            'estado' => $estado,
            'salario' => $sueldoDecimal,
            'tipo_contrato' => $this->cleanString($sheet->getCell('AA'.$row)->getValue()) ?: null,
            'numero_empleado' => $noEmpleado,
            'fecha_fin' => $estado === 'baja' ? $fechaBaja : null,
        ];

        if ($periodo) {
            $periodo->update($periodoData);
            $this->periodosActualizados++;
        } else {
            $periodoData['fecha_inicio'] = $fechaBaja ?? Carbon::now()->format('Y-m-d');
            PeriodoLaboral::create($periodoData);
            $this->periodosCreados++;
        }
    }

    private function processHistoricosSheet(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $noEmpleadoPB = trim((string) $sheet->getCell('A'.$row)->getValue());
            $fechaIngreso = $this->parseDate($sheet->getCell('D'.$row)->getValue());
            $fechaFin = $this->parseDate($sheet->getCell('E'.$row)->getValue());
            $nombres = $this->cleanString($sheet->getCell('F'.$row)->getValue());
            $apellidos = $this->cleanString($sheet->getCell('G'.$row)->getValue());

            if (! $noEmpleadoPB || ! $fechaIngreso) {
                continue;
            }

            $personaId = $this->empleadoPersonaMap[$noEmpleadoPB] ?? null;

            if (! $personaId && $nombres && $apellidos) {
                $persona = Persona::where('nombre', $nombres)
                    ->where('apellido', $apellidos)
                    ->first();
                if ($persona) {
                    $personaId = $persona->id;
                }
            }

            if (! $personaId) {
                continue;
            }

            if (! $fechaFin) {
                $periodoActual = PeriodoLaboral::where('persona_id', $personaId)
                    ->where(function ($q) use ($noEmpleadoPB) {
                        $q->where('numero_empleado', $noEmpleadoPB)
                            ->orWhere('estado', 'activo');
                    })
                    ->whereNull('fecha_fin')
                    ->first();

                if ($periodoActual) {
                    $periodoActual->update(['fecha_inicio' => $fechaIngreso]);
                    $this->periodosActualizados++;

                    continue;
                }
            }

            $existe = PeriodoLaboral::where('persona_id', $personaId)
                ->where('fecha_inicio', $fechaIngreso)
                ->when($fechaFin, fn ($q) => $q->where('fecha_fin', $fechaFin))
                ->exists();

            if ($existe) {
                continue;
            }

            PeriodoLaboral::create([
                'persona_id' => $personaId,
                'fecha_inicio' => $fechaIngreso,
                'fecha_fin' => $fechaFin,
                'estado' => $fechaFin ? 'terminado' : 'activo',
                'numero_empleado' => $noEmpleadoPB,
            ]);

            $this->historicosCreados++;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $value
        );

        return preg_replace('/\s+/', ' ', $value);
    }

    private function printSummary(): void
    {
        $this->command->newLine();
        $this->command->info('=== Resumen de importación ===');
        $this->command->info("Personas creadas: {$this->personasCreadas}");
        $this->command->info("Personas actualizadas: {$this->personasActualizadas}");
        $this->command->info("Periodos actuales creados: {$this->periodosCreados}");
        $this->command->info("Periodos actualizados: {$this->periodosActualizados}");
        $this->command->info("Periodos históricos creados: {$this->historicosCreados}");

        if (count($this->curpsInvalidos) > 0) {
            $this->command->warn('CURPs con UNDEFINED: '.count($this->curpsInvalidos));
        }

        if (count($this->departamentosNoMapeados) > 0) {
            $this->command->warn('Departamentos sin mapeo ('.count($this->departamentosNoMapeados).'): '.implode(', ', $this->departamentosNoMapeados));
        }

        if (count($this->puestosNoMapeados) > 0) {
            $this->command->warn('Puestos sin mapeo ('.count($this->puestosNoMapeados).'): '.implode(', ', array_slice($this->puestosNoMapeados, 0, 15)).(count($this->puestosNoMapeados) > 15 ? '...' : ''));
        }
    }
}
