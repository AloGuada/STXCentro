<?php

namespace App\Services\Rh\Onboarding;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee UN archivo Excel "Cronograma_Onboarding_*.xlsx" y extrae el puesto y sus
 * tareas de onboarding desde la hoja que contenga datos reales (típicamente
 * "Ejemplo Cronograma"). Las hojas en blanco ("Plantilla"/"Tu Cronograma") se ignoran.
 *
 * @phpstan-type CronogramaTarea array{
 *   etapa: string|null,
 *   titulo: string,
 *   responsable: string|null,
 *   duracion_estimada: string|null,
 *   dias_desde_inicio: int|null,
 *   orden: int,
 * }
 * @phpstan-type Cronograma array{
 *   puesto_nombre: string|null,
 *   area: string|null,
 *   tareas: list<CronogramaTarea>,
 *   archivo_origen: string,
 * }
 */
class CronogramaExcelParser
{
    /** @return Cronograma|null Null si el archivo no es un cronograma con datos. */
    public function parse(string $path): ?array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);

        $mejor = null;
        foreach ($ss->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            $cronograma = $this->parseHoja($rows);
            if ($cronograma === null) {
                continue;
            }
            // Preferimos la hoja con más tareas (la "Ejemplo Cronograma" llena).
            if ($mejor === null || count($cronograma['tareas']) > count($mejor['tareas'])) {
                $mejor = $cronograma;
            }
        }

        $ss->disconnectWorksheets();
        unset($ss);

        if ($mejor === null || $mejor['tareas'] === []) {
            return null;
        }

        $mejor['archivo_origen'] = basename($path);

        return $mejor;
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{puesto_nombre: string|null, area: string|null, tareas: list<array<string, mixed>>}|null
     */
    private function parseHoja(array $rows): ?array
    {
        $cell = static function (array $rows, int $r, string $col): ?string {
            $v = $rows[$r][$col] ?? null;
            $v = $v === null ? '' : trim((string) $v);

            return $v === '' ? null : $v;
        };

        $puesto = null;
        $area = null;
        $headerRow = null;
        $colEtapa = 'A';
        $colActividad = 'B';
        $colResponsable = 'C';
        $colDuracion = 'D';

        foreach ($rows as $r => $cols) {
            foreach ($cols as $c => $raw) {
                $val = $raw === null ? '' : trim((string) $raw);
                if ($val === '') {
                    continue;
                }
                $up = $this->norm($val);

                if ($up === 'PUESTO' && $puesto === null) {
                    $puesto = $cell($rows, $r, 'B');
                }
                if (($up === 'AREA / DEPARTAMENTO' || $up === 'AREA' || $up === 'DEPARTAMENTO' || $up === 'AREA DEPARTAMENTO') && $area === null) {
                    $area = $cell($rows, $r, 'B');
                }

                // Fila de encabezado de la tabla: contiene "Actividad".
                if ($headerRow === null && $up === 'ACTIVIDAD') {
                    $headerRow = (int) $r;
                    foreach ($cols as $hc => $hraw) {
                        $hup = $this->norm((string) ($hraw ?? ''));
                        match ($hup) {
                            'ETAPA' => $colEtapa = $hc,
                            'ACTIVIDAD' => $colActividad = $hc,
                            'RESPONSABLE' => $colResponsable = $hc,
                            'DURACION ESTIMADA', 'DURACION' => $colDuracion = $hc,
                            default => null,
                        };
                    }
                }
            }
        }

        if ($headerRow === null) {
            return null;
        }

        $tareas = [];
        $orden = 0;
        foreach ($rows as $r => $cols) {
            if ((int) $r <= $headerRow) {
                continue;
            }
            $titulo = $cell($rows, (int) $r, $colActividad);
            if ($titulo === null) {
                continue;
            }
            $etapa = $cell($rows, (int) $r, $colEtapa);
            $tituloNorm = $this->norm($titulo);

            // Saltar sub-encabezados del grid de semanas/días.
            if (in_array($tituloNorm, ['ACTIVIDAD', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'], true)) {
                continue;
            }
            if (str_starts_with($this->norm((string) $etapa), 'SEMANA') && $cell($rows, (int) $r, $colResponsable) === null) {
                continue;
            }

            $tareas[] = [
                'etapa' => $etapa,
                'titulo' => $titulo,
                'responsable' => $cell($rows, (int) $r, $colResponsable),
                'duracion_estimada' => $cell($rows, (int) $r, $colDuracion),
                'dias_desde_inicio' => $this->diasDesdeInicio($etapa),
                'orden' => $orden++,
            ];
        }

        return ['puesto_nombre' => $puesto, 'area' => $area, 'tareas' => $tareas];
    }

    /**
     * "DIA 1" => 0, "DIA 2" => 1, "DIA 2-6" => 1, "DIA 13" => 12. Sin número => null.
     */
    private function diasDesdeInicio(?string $etapa): ?int
    {
        if ($etapa === null) {
            return null;
        }
        if (preg_match('/(\d+)/', $etapa, $m)) {
            return max(0, (int) $m[1] - 1);
        }

        return null;
    }

    /** Uppercase, sin tildes, espacios colapsados. */
    private function norm(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
        }

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
