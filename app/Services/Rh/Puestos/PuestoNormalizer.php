<?php

namespace App\Services\Rh\Puestos;

/**
 * Reglas de normalización para skills, requerimientos y campos misceláneos.
 * Sincronizado con info/PROPUESTA_NORMALIZACION_RH.md.
 */
class PuestoNormalizer
{
    /** @var list<string> */
    private const SOFT_KEYWORDS = [
        'COMPROMISO', 'ACTITUD', 'LIDERAZGO', 'COMUNICACION',
        'RESPONSABILIDAD', 'MANEJO DE CRISIS', 'TOLERANCIA', 'ADAPTABILIDAD',
        'INICIATIVA', 'AUTONOMIA', 'TOMA DE DECISIONES', 'ASERTIVIDAD',
        'RELACIONES INTERPERSONALES', 'TRABAJO EN EQUIPO', 'COLABORACION',
        'EMPATIA', 'PERSEVERANCIA', 'CALIDAD Y MEJORA CONTINUA',
        'CONDUCCION DE PERSONAS', 'SERVICIO AL CLIENTE', 'SENTIDO DE URGENCIA',
        'TEMPLE Y DINAMISMO', 'GESTION Y LOGRO DE OBJETIVOS',
        'PENSAMIENTO ANALITICO', 'CAPACIDAD DE PLANIFICACION',
        'PROFUNDIDAD EN EL CONOCIMIENTO DE LOS PRODUCTOS',
        'INTEGRIDAD', 'HONESTIDAD', 'PUNTUALIDAD', 'DISCRECION',
        'ORIENTACION A RESULTADOS', 'PROACTIVIDAD', 'CREDIBILIDAD TECNICA',
    ];

    /** @var list<string> */
    private const HARD_KEYWORDS = [
        'OFFICE', 'EXCEL', 'WORD', 'AUTOCAD', 'CAD', 'SOLIDWORKS', 'TEKLA',
        'INGLES', 'SAP', 'SISTEMA', 'SOFTWARE', 'MAQUINARIA', 'MAQUINA',
        'SOLDADURA', 'SOLDADOR', 'PINTURA', 'MECANICA', 'ELECTRICIDAD',
        'CONTABILIDAD', 'FISCAL', 'ALMACEN', 'INVENTARIO', 'LOGISTICA',
        'ADMINISTRACION', 'ESTADISTICA', 'HABILIDAD NUMERICA',
        'PROGRAMACION', 'PONCHADO', 'CONFIGURACION', 'CABLES ESTRUCTURADOS',
        'MULTIMETRO', 'FORTINET', 'UBIQUITI', 'FORMATEO', 'CCTV',
        'CONMUTADOR', 'BIOMETRICO', 'ETIQUETADORA',
        'CARGA Y DESCARGA', 'TUBERIAS', 'OSMOSIS',
        'PTAR', 'COMPRESORES', 'CLIMAS', 'MONTACARGAS', 'CNC', 'TABLAS DINAMICAS',
        'PRESUPUESTOS', 'ESTIMACIONES',
    ];

    /**
     * Clave canónica para deduplicar skills/requerimientos:
     * uppercase, sin tildes, sin paréntesis, sin puntuación final, espacios colapsados.
     */
    public function dedupKey(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
        }
        $s = (string) preg_replace('/\(.*?\)/', '', $s);
        $s = (string) preg_replace('/[\.,:;]+$/', '', $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    /**
     * Decide si una skill debe clasificarse como 'hard' o 'soft', dado el tipo
     * que vino de la sección del Excel. La heurística por keyword sobrescribe
     * la sección porque en los Excels hay items mal clasificados.
     */
    public function clasificarSkill(string $nombre, string $tipoSeccion): string
    {
        $key = $this->dedupKey($nombre);
        foreach (self::HARD_KEYWORDS as $k) {
            if (str_contains($key, $k)) {
                return 'hard';
            }
        }
        foreach (self::SOFT_KEYWORDS as $k) {
            if (str_contains($key, $k)) {
                return 'soft';
            }
        }

        return $tipoSeccion;
    }

    public function esSkillBasura(string $nombre): bool
    {
        $key = $this->dedupKey($nombre);
        if (mb_strlen($key) < 3 || mb_strlen($key) > 120) {
            return true;
        }
        $stop = ['N/A', 'NIVEL', 'BASICO', 'MEDIO', 'EXPERTO', 'DIRECTOS', 'INDIRECTOS', 'TIPO', 'SI', 'NO'];

        return in_array($key, $stop, true);
    }

    /**
     * Convierte texto de horario (ej. "L-V DE 8AM A 6PM Y S DE 8AM A 12PM") a
     * [hora_entrada, hora_salida] en formato H:i. Retorna [null, null] si no se puede parsear.
     *
     * @return array{0: string|null, 1: string|null}
     */
    public function parsearHorario(?string $horario): array
    {
        if ($horario === null || trim($horario) === '') {
            return [null, null];
        }
        // Busca patrón "HH(:MM)?(AM|PM)? A HH(:MM)?(AM|PM)?"
        if (! preg_match('/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?\s*A\s*(\d{1,2})(?::(\d{2}))?\s*(AM|PM)?/i', $horario, $m)) {
            return [null, null];
        }
        $entrada = $this->normalizarHora((int) $m[1], (int) ($m[2] ?? 0), $m[3] ?? null);
        $salida = $this->normalizarHora((int) $m[4], (int) ($m[5] ?? 0), $m[6] ?? null);

        return [$entrada, $salida];
    }

    private function normalizarHora(int $h, int $min, ?string $period): string
    {
        $p = strtoupper((string) $period);
        if ($p === 'PM' && $h < 12) {
            $h += 12;
        } elseif ($p === 'AM' && $h === 12) {
            $h = 0;
        }

        return sprintf('%02d:%02d', $h % 24, $min);
    }

    /** Normaliza un valor KV (ej. "HOMBRE" -> "Masculino", "TIPO:SI" -> "Si"). */
    public function normalizarValorRequerimiento(string $valor): ?string
    {
        $v = trim($valor);
        if ($v === '' || in_array(mb_strtoupper($v), ['N/A', 'TIPO', 'NO APLICA', 'TIPO:'], true)) {
            return null;
        }
        // Quitar prefijo "TIPO:" residual del parser
        if (preg_match('/^TIPO:\s*(.+)$/i', $v, $m)) {
            $v = trim($m[1]);
        }

        return $v === '' ? null : $v;
    }

    /** Mapeo de genero (acepta variantes del Excel). */
    public function normalizarGenero(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $up = mb_strtoupper($v);
        if (str_contains($up, 'INDISTINTO')) {
            return 'Indistinto';
        }
        if (str_contains($up, 'FEMEN')) {
            return 'Femenino';
        }
        if (str_contains($up, 'HOMBRE') || str_contains($up, 'MASCULIN')) {
            return 'Masculino';
        }

        return $v;
    }

    /** Mapeo de escolaridad. */
    public function normalizarEscolaridad(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $up = mb_strtoupper($v);
        // "PRIMARIA O SECUNDARIA" -> tomamos el mínimo (Primaria)
        if (str_contains($up, 'PRIMARIA')) {
            return 'Primaria';
        }
        if (str_contains($up, 'SECUNDARIA')) {
            return 'Secundaria';
        }
        if (str_contains($up, 'BACHILLERATO') || str_contains($up, 'PREPARATORIA')) {
            return 'Bachillerato';
        }
        if (str_contains($up, 'TECNIC')) {
            return 'Técnico';
        }
        if (str_contains($up, 'LICENCIATURA') || str_contains($up, 'INGENIERIA')) {
            return 'Licenciatura';
        }

        return $v;
    }

    /** Display name "Nombre Con Tildes Si Aplica" para guardar en BD. */
    public function displayName(string $s): string
    {
        return mb_convert_case(trim($s), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Limpia un item crudo del Excel (skill, experiencia, certificación) aplicando:
     * 1. Strip prefijos de bullet (`* `, `1.- `, `1- `, `1) `, `1. `).
     * 2. Strip metadata al inicio tipo `(10 AÑOS) X` → `X`.
     * 3. Trim whitespace.
     * 4. Retorna null si tras limpiar queda < 3 chars o > 120 chars (descripciones largas).
     */
    public function limpiarItem(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        // Strip prefijos de bullet: `* `, `1.- `, `1- `, `1) `, `1. `, `01.- `, etc.
        // Acepta cualquier secuencia de [* - dígitos . )] seguida de uno o más espacios.
        $s = (string) preg_replace('/^[\*\-\d\.\)]+\s+/', '', $s);

        // Strip metadata al inicio: `(10 AÑOS) X`, `(5 años) Y`
        $s = (string) preg_replace('/^\(\s*\d+\s*A[ÑN]OS?\s*\)\s*/iu', '', $s);

        // Colapsar espacios múltiples
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        if (mb_strlen($s) < 3 || mb_strlen($s) > 120) {
            return null;
        }

        return $s;
    }
}
