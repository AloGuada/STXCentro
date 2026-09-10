<?php

namespace Database\Seeders;

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Enums\Qal\TipoDatoPunto;
use App\Models\Qal\PuntoInspeccion;
use Illuminate\Database\Seeder;

/**
 * Los puntos que revisa el inspector en cada formulario.
 *
 * No son datos de ejemplo: son los puntos de los formatos oficiales, con la
 * clave que tenían en la aplicación anterior (`p1_defl`, `p2_bisel`, `m_poros`)
 * y las respuestas que el inspector ya conoce. Cada respuesta dice lo que
 * significa: cumple, no cumple o no aplica. Las que sólo describen —el tipo de
 * desviación, su tamaño— no significan nada por sí solas y van en nulo.
 *
 * El código es la fuente de verdad de etiquetas, secciones y orden: correrlo
 * de nuevo los actualiza. No toca `activo`, así que un punto que alguien
 * desactivó se queda desactivado.
 *
 * Correr con: php artisan db:seed --class=QalPuntosInspeccionSeeder
 */
class QalPuntosInspeccionSeeder extends Seeder
{
    private const OK_DEFECTO = ['OK' => 'ok', 'Con defecto' => 'no_ok', 'n/a' => 'no_aplica'];

    private const OK_DEFECTO_SIN_NA = ['OK' => 'ok', 'Con defecto' => 'no_ok'];

    private const OK_TOLERANCIA = ['OK' => 'ok', 'Fuera de tol.' => 'no_ok'];

    private const OK_NO_OK = ['OK' => 'ok', 'No OK' => 'no_ok'];

    private const OK_FALTA = ['OK' => 'ok', 'Falta' => 'no_ok'];

    private const JUNTA = ['OK' => 'ok', 'Defecto' => 'no_ok', 'n/a' => 'no_aplica'];

    public function run(): void
    {
        $ahora = now();
        $filas = [];

        foreach ($this->secciones() as [$contexto, $seccion, $puntos]) {
            foreach ($puntos as $clave => $punto) {
                $filas[] = [
                    'clave' => $clave,
                    'ambito' => ($contexto['ambito'] ?? AmbitoPunto::Pieza)->value,
                    'fase' => $contexto['fase']->value,
                    'subetapa' => ($contexto['subetapa'] ?? null)?->value,
                    'subtipo' => ($contexto['subtipo'] ?? null)?->value,
                    'seccion' => $seccion,
                    'etiqueta' => $punto[0],
                    'tipo_dato' => ($punto['tipo'] ?? TipoDatoPunto::Seleccion)->value,
                    'opciones' => isset($punto[1]) ? json_encode(self::opciones($punto[1]), JSON_UNESCAPED_UNICODE) : null,
                    'unidad' => $punto['unidad'] ?? null,
                    'calculado' => $punto['calculado'] ?? false,
                    'obligatorio' => $punto['obligatorio'] ?? false,
                    'orden' => count($filas) + 1,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        PuntoInspeccion::query()->upsert($filas, ['clave'], [
            'ambito', 'fase', 'subetapa', 'subtipo', 'seccion', 'etiqueta', 'tipo_dato',
            'opciones', 'unidad', 'calculado', 'obligatorio', 'orden', 'updated_at',
        ]);
    }

    /**
     * @param  array<string, string|null>  $respuestas
     * @return list<array{valor: string, resultado: string|null}>
     */
    private static function opciones(array $respuestas): array
    {
        return array_map(
            fn (string|int $valor, ?string $resultado): array => ['valor' => (string) $valor, 'resultado' => $resultado],
            array_keys($respuestas),
            array_values($respuestas),
        );
    }

    /**
     * Cada sección con su contexto (fase, sub-etapa o subtipo, ámbito) y sus
     * puntos en el orden en que aparecen en el formulario.
     *
     * @return list<array{0: array<string, mixed>, 1: string, 2: array<string, array<int|string, mixed>>}>
     */
    private function secciones(): array
    {
        $primera = ['fase' => FaseTransformacion::Primera];
        $perfil = $primera + ['subtipo' => SubtipoPrimera::Perfil];
        $placa = $primera + ['subtipo' => SubtipoPrimera::Placa];
        $segunda = ['fase' => FaseTransformacion::Segunda];
        $armado = $segunda + ['subetapa' => Subetapa::ArmadoVestido];
        $soldado = $segunda + ['subetapa' => Subetapa::Soldado];
        $tercera = ['fase' => FaseTransformacion::Tercera];
        $junta = $soldado + ['ambito' => AmbitoPunto::Junta];

        $desviaciones = ['Deflexión', 'Torsión', 'Flecha', 'Contraflecha', 'Hi-Low', 'Alabeo en patín', 'Pandeo de alma', 'Otro'];

        return [
            [$primera, 'Inspección visual y dimensional', [
                'p1_dim' => ['Dimensión de la pieza', ['OK' => 'ok', 'Fuera de tol.' => 'no_ok', 'n/a' => 'no_aplica']],
                'p1_corte' => ['Defectos de corte', self::OK_DEFECTO],
                'p1_bisel' => ['Preparación junta (bisel)', self::OK_DEFECTO],
                'p1_posbar' => ['Posición de barrenos', ['OK' => 'ok', 'Incorrecta' => 'no_ok', 'n/a' => 'no_aplica']],
                'p1_diam' => ['Diámetro de barrenos', self::OK_DEFECTO],
                'p1_bar' => ['Barrenos (automático)', self::OK_DEFECTO_SIN_NA, 'calculado' => true],
            ]],
            [$perfil, 'Perfil — dimensional', [
                'p1_empates' => ['No. de empates / juntas', 'tipo' => TipoDatoPunto::Numero],
                'p1_long' => ['Longitud', self::OK_TOLERANCIA],
                'p1_defl' => ['Deflexión', self::OK_TOLERANCIA],
                'p1_tors' => ['Torsión', self::OK_TOLERANCIA],
                'p1_patin' => ['Descuadre de patín', self::OK_TOLERANCIA],
            ]],
            [$placa, 'Placa — inspección (F-STX-CA-03)', [
                'p1_limpieza' => ['Limpieza', self::OK_DEFECTO],
                'p1_edoinsp' => ['Estado de inspección', ['Aceptado' => 'ok', 'Rechazado' => 'no_ok']],
            ]],
            [$segunda, 'Punto de inspección', [
                'p2_diaf' => ['Giro o caída de placas o accesorios', 'tipo' => TipoDatoPunto::Contador],
                'p2_desv' => ['¿Desviación dimensional por soldadura?', ['OK (sin desviación)' => 'ok', 'Con desviación' => 'no_ok']],
                'p2_desvtipo' => ['Tipo de desviación', array_fill_keys($desviaciones, null)],
                'p2_desvsize' => ['Tamaño de desviación', ['Leve' => null, 'Moderada' => null, 'Severa' => null]],
            ]],
            [$segunda, 'Inspección dimensional', [
                'p2_long' => ['Longitud total', self::OK_NO_OK],
                'p2_placas' => ['Dist. entre placas', self::OK_NO_OK + ['n/a' => 'no_aplica']],
                'p2_dimok' => ['¿Dimensiones OK?', self::OK_NO_OK, 'calculado' => true],
            ]],
            [$segunda, 'Barrenos habilitados', [
                'p2_bardiam' => ['Diámetro', self::OK_DEFECTO],
                'p2_bardist' => ['Posición / distancia', self::OK_DEFECTO],
                'p2_bartot' => ['N.º habilitados', 'tipo' => TipoDatoPunto::Numero],
                'p2_bardef' => ['N.º defectuosos', 'tipo' => TipoDatoPunto::Numero],
            ]],
            [$soldado, 'Soldadura', [
                'p2_elem' => ['Nº de elementos de la pieza (del plano)', 'tipo' => TipoDatoPunto::Numero, 'obligatorio' => true],
            ]],
            [$soldado, 'Control de proceso · durante la soldadura', [
                'p2_precal' => ['Precalentamiento', self::OK_DEFECTO],
                'p2_limppasadas' => ['Limpieza entre pasadas', self::OK_DEFECTO],
            ]],
            [$soldado, 'Limpieza mecánica y etiqueta', [
                'p2_limpieza' => ['Limpieza mecánica', self::OK_FALTA],
                'p2_etiqueta' => ['Etiqueta', self::OK_FALTA],
            ]],
            [$armado, 'Preparación de juntas', [
                'p2_bisel' => ['Ángulo de bisel', self::OK_DEFECTO],
                'p2_pulido' => ['Pulido (oxicorte)', self::OK_DEFECTO],
                'p2_raiz' => ['Separación de raíz', self::OK_DEFECTO],
                'p2_respaldo' => ['Placa de respaldo', self::OK_DEFECTO],
                'p2_hombro' => ['Hombro', self::OK_DEFECTO],
                'p2_acceso' => ['Acceso de soldadura', self::OK_DEFECTO],
                'p2_corte' => ['Corte (destajo)', self::OK_DEFECTO],
                'p2_faltavest' => ['Falta de vestido — elementos faltantes', 'tipo' => TipoDatoPunto::Contador],
            ]],
            [$tercera, 'Inspección de pintura', [
                'p3_esp' => ['Espesor', ['OK' => 'ok', 'Espesor bajo' => 'no_ok']],
                'p3_vis' => ['Visual', self::OK_DEFECTO_SIN_NA],
                'p3_adh' => ['Adherencia', ['OK' => 'ok', 'Falla adherencia' => 'no_ok']],
            ]],
            [$junta, 'Mapeo de soldaduras', [
                'm_material' => ['Material correcto', self::JUNTA],
                'm_prepfilete' => ['Prep. junta filete', self::JUNTA],
                'm_prepranura' => ['Prep. junta ranura', self::JUNTA],
                'm_respaldo' => ['Placa de respaldo', self::JUNTA],
                'm_acceso' => ['Radios de acceso', self::JUNTA],
                'm_corte' => ['Corte sin muescas', self::JUNTA],
                'm_precal' => ['Precalentamiento', self::JUNTA],
                'm_limpieza' => ['Limpieza entre pasadas', self::JUNTA],
                'm_grieta' => ['Grieta', self::JUNTA],
                'm_fusion' => ['Falta de fusión', self::JUNTA],
                'm_traslape' => ['Traslape', self::JUNTA],
                'm_insuf' => ['Soldadura insuficiente', self::JUNTA],
                'm_poros' => ['Porosidad', self::JUNTA],
                'm_socav' => ['Socavado', self::JUNTA],
                'm_perfil' => ['Perfil de soldadura', self::JUNTA],
                'm_crater' => ['Cráter', self::JUNTA],
                'm_retiro' => ['Retiro de puntos', self::JUNTA],
                'm_matbase' => ['Material base dañado', self::JUNTA],
            ]],
        ];
    }
}
