<?php

namespace App\Services\Rh\Puestos;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee UN archivo Excel de descriptivo de puesto y retorna 1+ bloques crudos
 * (un archivo puede contener varios puestos lado a lado, ej. SOLDADOR 1T/2T/3T).
 *
 * Cada bloque tiene la siguiente forma:
 *
 * @phpstan-type PuestoBlock array{
 *   nombre: string|null,
 *   ubicacion: string|null,
 *   horario_texto: string|null,
 *   departamento_nombre: string|null,
 *   jefe_inmediato_nombre: string|null,
 *   objetivo: string|null,
 *   requisitos: array<string, string|null>,
 *   experiencia: list<string>,
 *   certificaciones: list<string>,
 *   skills_soft: list<array{nombre: string, nivel: string}>,
 *   skills_hard: list<array{nombre: string, nivel: string}>,
 *   recursos: array<string, string|null>,
 *   actividades: list<string>,
 *   archivo_origen: string,
 *   columna_inicio: string,
 * }
 */
class PuestoExcelParser
{
    /** @return list<array<string, mixed>> */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $ss->disconnectWorksheets();
        unset($ss);

        $cells = [];
        foreach ($rows as $r => $cols) {
            foreach ($cols as $c => $v) {
                if ($v !== null && trim((string) $v) !== '') {
                    $cells["$c$r"] = trim((string) $v);
                }
            }
        }
        unset($rows);

        // Detectar todos los puntos donde empieza un bloque (palabra ancla "DENOMINACION DEL PUESTO")
        $anclas = [];
        foreach ($cells as $coord => $val) {
            $up = mb_strtoupper($val);
            if (str_starts_with($up, 'DENOMINACION DEL PUESTO') || str_starts_with($up, 'DENOMINACIÓN DEL PUESTO')) {
                preg_match('/^([A-Z]+)(\d+)$/', $coord, $m);
                if ($m) {
                    $anclas[] = ['col' => $m[1], 'row' => (int) $m[2]];
                }
            }
        }

        $bloques = [];
        foreach ($anclas as $ancla) {
            $bloque = $this->parseBlock($cells, $ancla['col'], $ancla['row']);
            $bloque['archivo_origen'] = basename($path);
            $bloque['columna_inicio'] = $ancla['col'];
            $bloques[] = $bloque;
        }

        return $bloques;
    }

    /**
     * Parsea UN bloque que arranca en la celda del header "DENOMINACION DEL PUESTO".
     *
     * @param  array<string, string>  $cells
     * @return array<string, mixed>
     */
    private function parseBlock(array $cells, string $anchorCol, int $anchorRow): array
    {
        // Filtro de celdas relevantes para este bloque:
        // - Columnas: desde anchorCol hasta anchorCol + 11 (cubre el ancho típico ~12 cols)
        // - Filas: desde anchorRow - 5 hasta anchorRow + 130 (cubre todo el descriptivo)
        $startColOrd = ord($anchorCol);
        $endColOrd = $startColOrd + 11;
        $startRow = max(1, $anchorRow - 5);
        $endRow = $anchorRow + 130;

        $blockCells = [];
        foreach ($cells as $coord => $val) {
            preg_match('/^([A-Z]+)(\d+)$/', $coord, $m);
            if (! $m) {
                continue;
            }
            $col = $m[1];
            $row = (int) $m[2];
            if (strlen($col) > 1) {
                continue;
            }
            $colOrd = ord($col);
            if ($colOrd < $startColOrd || $colOrd > $endColOrd) {
                continue;
            }
            if ($row < $startRow || $row > $endRow) {
                continue;
            }
            $blockCells[$coord] = $val;
        }

        // Helpers locales sobre $blockCells
        $find = function (string $needle) use ($blockCells): ?array {
            $up = mb_strtoupper($needle);
            foreach ($blockCells as $coord => $val) {
                if (str_starts_with(mb_strtoupper($val), $up)) {
                    preg_match('/^([A-Z]+)(\d+)$/', $coord, $m);

                    return ['col' => $m[1], 'row' => (int) $m[2]];
                }
            }

            return null;
        };

        $valAfter = function (string $needle, int $maxOffsets = 6) use ($blockCells, $find): ?string {
            $loc = $find($needle);
            if (! $loc) {
                return null;
            }
            for ($i = 1; $i <= $maxOffsets; $i++) {
                $nc = chr(ord($loc['col']) + $i);
                $v = $blockCells["$nc{$loc['row']}"] ?? null;
                if ($v !== null && trim($v) !== '' && rtrim(mb_strtoupper($v), ':') !== 'TIPO') {
                    return $v;
                }
            }

            return null;
        };

        // Indexa filas que tienen un header de seccion (para parar la lectura cuando se cruza)
        $headerKeywords = ['COMPETENCIAS', 'HABILIDADES', 'CUALIDADES', 'CONOCIMIENTOS', 'SOFTWARE', 'REQUERIMIENTOS', 'REQUISITOS', 'ESTRUCTURA', 'ACTIVIDADES', 'REPORTES', 'RELACIONES', 'CERTIFICACIONES'];
        $rowsConHeader = [];
        foreach ($blockCells as $coord => $val) {
            $up = mb_strtoupper($val);
            foreach ($headerKeywords as $h) {
                if (str_starts_with($up, $h)) {
                    preg_match('/(\d+)/', $coord, $m);
                    $rowsConHeader[(int) $m[1]] = true;
                    break;
                }
            }
        }

        $bloque = [
            'nombre' => $valAfter('DENOMINACION DEL PUESTO') ?: $valAfter('DENOMINACIÓN DEL PUESTO') ?: $valAfter('TITULO DEL PUESTO') ?: $valAfter('TÍTULO DEL PUESTO'),
            'ubicacion' => $valAfter('UBICACION') ?: $valAfter('UBICACIÓN'),
            'horario_texto' => $valAfter('HORARIO DE TRABAJO'),
            'departamento_nombre' => $valAfter('DEPARTAMENTO / AREA') ?: $valAfter('DEPARTAMENTO / ÁREA') ?: $valAfter('DEPARTAMENTO'),
            'jefe_inmediato_nombre' => $valAfter('PUESTO DEL JEFE INMEDIATO') ?: $valAfter('JEFE INMEDIATO'),
            'objetivo' => null,
            'requisitos' => [
                'genero' => $valAfter('GENERO') ?: $valAfter('GÉNERO'),
                'edad' => $valAfter('EDAD'),
                'estado_civil' => $valAfter('EDO. CIVIL') ?: $valAfter('ESTADO CIVIL'),
                'nivel_escolaridad' => $valAfter('NIVEL DE ESCOLARIDAD'),
                'estudios_superiores' => $valAfter('ESTUDIOS SUPERIORES'),
            ],
            'experiencia' => [],
            'certificaciones' => [],
            'skills_soft' => [],
            'skills_hard' => [],
            'recursos' => [],
            'actividades' => [],
        ];

        // OBJETIVO (parrafo en fila siguiente al header)
        $obj = $find('OBJETIVO DEL PUESTO');
        if ($obj) {
            for ($r = $obj['row'] + 1; $r <= $obj['row'] + 3; $r++) {
                foreach (range($startColOrd, $endColOrd) as $colOrd) {
                    $v = $blockCells[chr($colOrd).$r] ?? null;
                    if ($v !== null && mb_strlen($v) > 30) {
                        $bloque['objetivo'] = $v;
                        break 2;
                    }
                }
            }
        }

        // EXPERIENCIA: bullets en la columna anchorCol+1, despues del header EXPERIENCIA
        $exp = $find('EXPERIENCIA');
        if ($exp) {
            $bcol = chr(ord($exp['col']) + 1);
            for ($r = $exp['row']; $r <= $exp['row'] + 15; $r++) {
                if (isset($rowsConHeader[$r]) && $r > $exp['row']) {
                    break;
                }
                $v = $blockCells["$bcol$r"] ?? null;
                if ($v === null) {
                    continue;
                }
                $vup = mb_strtoupper($v);
                if (in_array($vup, ['N/A', 'BASICO', 'MEDIO', 'EXPERTO', 'NIVEL'], true)) {
                    continue;
                }
                if (mb_strlen($v) < 3) {
                    continue;
                }
                $bloque['experiencia'][] = $v;
            }
        }

        // CERTIFICACIONES
        $cer = $find('CERTIFICACIONES');
        if ($cer) {
            $bcol = chr(ord($cer['col']) + 1);
            for ($r = $cer['row']; $r <= $cer['row'] + 10; $r++) {
                if (isset($rowsConHeader[$r]) && $r > $cer['row']) {
                    break;
                }
                $v = $blockCells["$bcol$r"] ?? null;
                if ($v === null) {
                    continue;
                }
                $vup = mb_strtoupper($v);
                if (in_array($vup, ['N/A', 'NIVEL'], true)) {
                    continue;
                }
                if (mb_strlen($v) < 3) {
                    continue;
                }
                $bloque['certificaciones'][] = $v;
            }
        }

        // SKILLS por seccion con nivel.
        // Identificamos secciones por prefijo + verificamos que la fila tenga
        // las columnas BASICO/MEDIO/EXPERTO (asi distinguimos un header real
        // de un item que casualmente empiece igual).
        $tipoPorPrefijo = [
            'COMPETENCIAS PERSONALIDAD' => 'soft',
            'CUALIDADES INTRINSECAS' => 'soft',
            'CUALIDADES INTRÍNSECAS' => 'soft',
            'HABILIDADES TECNICAS' => 'hard',
            'HABILIDADES TÉCNICAS' => 'hard',
            'CONOCIMIENTOS' => 'hard',
            'SOFTWARE' => 'hard',
        ];

        foreach ($blockCells as $coord => $val) {
            preg_match('/^([A-Z]+)(\d+)$/', $coord, $m);
            if (! $m) {
                continue;
            }
            $hdrCol = $m[1];
            $hdrRow = (int) $m[2];

            $up = mb_strtoupper($val);
            $tipoSeccion = null;
            foreach ($tipoPorPrefijo as $prefijo => $tipo) {
                if (str_starts_with($up, $prefijo)) {
                    $tipoSeccion = $tipo;
                    break;
                }
            }
            if ($tipoSeccion === null) {
                continue;
            }

            // Verificar que esta fila REALMENTE tiene columnas de nivel (BASICO/MEDIO/EXPERTO)
            $levelCols = [];
            for ($i = 2; $i <= 8; $i++) {
                $c = chr(ord($hdrCol) + $i);
                $v = $blockCells["$c{$hdrRow}"] ?? null;
                if ($v === null) {
                    continue;
                }
                $vup = mb_strtoupper($v);
                if ($vup === 'BASICO' || $vup === 'BÁSICO') {
                    $levelCols['basico'] = $c;
                } elseif ($vup === 'MEDIO') {
                    $levelCols['intermedio'] = $c;
                } elseif ($vup === 'EXPERTO') {
                    $levelCols['avanzado'] = $c;
                }
            }
            if ($levelCols === []) {
                continue; // no es un header real
            }

            $nameCol = chr(ord($hdrCol) + 1);
            for ($r = $hdrRow + 1; $r <= $hdrRow + 25; $r++) {
                if (isset($rowsConHeader[$r])) {
                    break;
                }
                $name = $blockCells["$nameCol$r"] ?? null;
                if ($name === null) {
                    continue;
                }
                $nup = mb_strtoupper(trim($name));
                if (in_array($nup, ['BASICO', 'BÁSICO', 'MEDIO', 'EXPERTO', 'NIVEL', 'N/A'], true)) {
                    continue;
                }
                if (mb_strlen($name) < 3 || mb_strlen($name) > 200) {
                    continue;
                }
                $nivel = 'basico';
                foreach ($levelCols as $lvl => $c) {
                    $mark = $blockCells["$c$r"] ?? null;
                    if ($mark !== null && trim($mark) !== '') {
                        $nivel = $lvl;
                        break;
                    }
                }
                $bloque["skills_{$tipoSeccion}"][] = ['nombre' => $name, 'nivel' => $nivel];
            }
        }

        // RECURSOS (key=label, value=descripcion del recurso)
        $recursoLabels = ['COMPUTADORA', 'TELEFONO', 'TELÉFONO', 'AUTOMOVIL', 'AUTOMÓVIL', 'OTROS', 'INTERNET', 'ACCESO A SISTEMAS', 'ACCESO A SISTEMA', 'EQUIPO DE PROTECCION PERSONAL', 'EQUIPO DE PROTECCIÓN PERSONAL'];
        foreach ($recursoLabels as $label) {
            $v = $valAfter($label);
            if ($v !== null) {
                $bloque['recursos'][rtrim(mb_strtoupper($label), ':')] = $v;
            }
        }

        // ACTIVIDADES DEL CARGO (numeradas)
        $act = $find('ACTIVIDADES DEL CARGO');
        if (! $act) {
            $act = $find('RESPONSABILIDADES ESPECIFICAS') ?: $find('RESPONSABILIDADES ESPECÍFICAS');
        }
        if ($act) {
            $bcol = chr(ord($act['col']) + 1);
            for ($r = $act['row'] + 1; $r <= $act['row'] + 40; $r++) {
                $num = $blockCells["{$act['col']}$r"] ?? null;
                $desc = $blockCells["$bcol$r"] ?? null;
                if ($desc === null) {
                    continue;
                }
                $dup = mb_strtoupper($desc);
                if (str_starts_with($dup, 'REPORTES') || str_starts_with($dup, 'RELACIONES')) {
                    break;
                }
                if (str_starts_with($dup, 'RESPONSABILIDADES')) {
                    continue;
                }
                if (is_numeric($num) || (is_string($num) && preg_match('/^\d+$/', $num))) {
                    $bloque['actividades'][] = $desc;
                }
            }
        }

        return $bloque;
    }
}
