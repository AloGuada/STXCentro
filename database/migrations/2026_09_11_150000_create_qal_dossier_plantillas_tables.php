<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOTA_DIMENSIONAL = 'Confirmado con calidad: lo que va aquí son las notas de entrega que genera Strumis, no inspecciones dimensionales. Se suben como PDF.';

    /**
     * Las plantillas del dosier: el árbol de secciones con que nace el dosier
     * de una obra.
     *
     * Nacen las cuatro que ya usa Steelex, transcritas de sus índices reales:
     * la estándar (el dosier de Tres Guerras) y los tipos A, B y C, de más a
     * menos exigente. Los números NO se guardan: se calculan del árbol por
     * posición. Los índices reales traen números repetidos —dos «2» en el tipo
     * A, tres «8.1» en el B— y guardarlos haría que el dosier los arrastrara.
     * En la transcripción el número sólo dice la profundidad.
     */
    public function up(): void
    {
        Schema::create('qal_dossier_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('qal_dossier_plantilla_secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('qal_dossier_plantillas')->cascadeOnDelete();
            $table->foreignId('padre_id')->nullable()->constrained('qal_dossier_plantilla_secciones')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('titulo', 160);
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->index(['plantilla_id', 'padre_id', 'orden']);
        });

        foreach ($this->plantillas() as $nombre => [$descripcion, $secciones]) {
            $plantilla = DB::table('qal_dossier_plantillas')->insertGetId([
                'nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            // El último id visto en cada nivel: el padre de un nivel n es el último del n-1.
            $ultimo = [];
            $orden = [];

            foreach ($secciones as $seccion) {
                [$nivel, $titulo] = $seccion;
                $padre = $nivel > 1 ? $ultimo[$nivel - 1] : null;
                $orden[$padre ?? 0] = ($orden[$padre ?? 0] ?? 0) + 1;

                $ultimo[$nivel] = DB::table('qal_dossier_plantilla_secciones')->insertGetId([
                    'plantilla_id' => $plantilla,
                    'padre_id' => $padre,
                    'orden' => $orden[$padre ?? 0],
                    'titulo' => $titulo,
                    'nota' => $seccion[2] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_dossier_plantilla_secciones');
        Schema::dropIfExists('qal_dossier_plantillas');
    }

    /**
     * @return array<string, array{0: string, 1: list<array{0: int, 1: string, 2?: string}>}>
     */
    private function plantillas(): array
    {
        return [
            'Estándar' => ['El índice del dosier real de Tres Guerras: el punto de partida de cualquier obra.', [
                [1, 'NORMATIVIDAD'],
                [2, 'PROCEDIMIENTOS DE SOLDADURA (WPS)'],
                [2, 'PROCEDIMIENTOS DE CALIDAD'],
                [1, 'REGISTRO DE CALIFICACIÓN DE SOLDADORES (WPQR)'],
                [1, 'CERTIFICADOS DE CALIBRACIÓN DE EQUIPOS'],
                [2, 'CERTIFICADOS DE MÁQUINAS DE SOLDAR'],
                [1, 'CERTIFICADOS DE CALIDAD DE MATERIALES'],
                [2, 'CERTIFICADOS DE MATERIAL BASE'],
                [2, 'CERTIFICADOS DE MATERIAL DE APORTE'],
                [2, 'CERTIFICADOS DE MATERIAL ADICIONAL'],
                [1, 'TRAZABILIDAD DE MATERIALES'],
                [1, 'REPORTES DE FABRICACIÓN'],
                [2, 'INSPECCIÓN DIMENSIONAL', self::NOTA_DIMENSIONAL],
                [2, 'INSPECCIÓN VISUAL DE SOLDADURA'],
                [1, 'REPORTES DE PRUEBAS NO DESTRUCTIVAS'],
                [2, 'REPORTES DE ULTRASONIDO'],
                [2, 'OTROS ENSAYOS (MT / PT)'],
                [1, 'REPORTES DE RECUBRIMIENTOS'],
                [2, 'INSPECCIÓN VISUAL DE RECUBRIMIENTOS'],
                [2, 'REPORTE DE PRUEBAS DE ESPESORES'],
                [2, 'REPORTE DE PRUEBAS DE ADHERENCIA'],
            ]],
            'Tipo A · Completo' => ['El más exigente: WPS con su PQR y ensayos de calificación, acreditación del laboratorio ante la EMA, planos de fabricación y los tres tipos de PND.', [
                [1, 'NORMATIVIDAD'],
                [2, 'PROCEDIMIENTOS DE SOLDADURA'],
                [3, 'ESPECIFICACIÓN DEL PROCEDIMIENTO DE SOLDADURA (WPS)'],
                [3, 'REGISTRO DE CALIFICACIÓN DEL PROCEDIMIENTO DE SOLDADURA (PQR)'],
                [3, 'RESULTADOS DE LA PRUEBA DEL PROCEDIMIENTO (PQR)'],
                [4, 'REPORTE DE INSPECCIÓN RADIOGRÁFICA'],
                [4, 'REPORTE DE PRUEBA DE TENSIÓN'],
                [4, 'REPORTE DE DOBLEZ GUIADO'],
                [2, 'PROCEDIMIENTO DE INSPECCIÓN DE PRUEBAS NO DESTRUCTIVAS'],
                [1, 'ACREDITACIÓN DEL PERSONAL DE CALIDAD'],
                [2, 'CERTIFICACIÓN DEL INSPECTOR DE CALIDAD'],
                [2, 'CURRÍCULUM EMPRESARIAL'],
                [1, 'REGISTRO DE CALIFICACIÓN DE SOLDADORES (WPQR)'],
                [1, 'REGISTROS Y CERTIFICADOS DE EQUIPOS'],
                [2, 'REGISTRO DE VERIFICACIÓN DE MÁQUINAS DE SOLDAR'],
                [2, 'CERTIFICADOS DE CALIBRACIÓN DE MÁQUINAS DE SOLDAR'],
                [1, 'TRAZABILIDAD DE MATERIALES'],
                [1, 'CERTIFICADOS DE CALIDAD'],
                [2, 'CERTIFICADOS DE MATERIAL BASE'],
                [3, 'PLACAS'],
                [3, 'PERFILES'],
                [3, 'CERTIFICADOS ADICIONALES'],
                [1, 'PLANOS DE FABRICACIÓN'],
                [2, 'PLANOS DE TALLER'],
                [2, 'PLANOS DE MONTAJE'],
                [1, 'CONTROL DE CALIDAD EN LA FABRICACIÓN'],
                [2, 'ACREDITACIÓN DEL LABORATORIO DE PND ANTE LA EMA'],
                [2, 'PROCEDIMIENTO DE INSPECCIÓN PND'],
                [2, 'CERTIFICACIÓN DEL PERSONAL DE LABORATORIO'],
                [1, 'REPORTES DE FABRICACIÓN'],
                [2, 'MAPEO E INSPECCIÓN VISUAL DE JUNTAS DE SOLDADURA'],
                [2, 'INSPECCIÓN DIMENSIONAL', self::NOTA_DIMENSIONAL],
                [1, 'REPORTES DE PRUEBAS NO DESTRUCTIVAS'],
                [2, 'REPORTE DE ULTRASONIDO'],
                [2, 'REPORTE DE PARTÍCULAS MAGNÉTICAS'],
                [2, 'REPORTE DE LÍQUIDOS PENETRANTES'],
                [1, 'REPORTE DE RECUBRIMIENTOS'],
                [2, 'PREPARACIÓN DE SUPERFICIES'],
                [2, 'NOTAS DE ENTREGA DE RECUBRIMIENTOS (INS PINT)'],
                [2, 'INSPECCIÓN VISUAL DE RECUBRIMIENTOS'],
                [2, 'ESPESORES DE PINTURA'],
                [2, 'PRUEBAS DE ADHERENCIA'],
            ]],
            'Tipo B · Estándar' => ['El índice habitual: normatividad, WPQR, calibraciones, certificados de material, trazabilidad, fabricación, ultrasonido y recubrimientos.', [
                [1, 'NORMATIVIDAD'],
                [2, 'PROCEDIMIENTOS DE SOLDADURA (WPS)'],
                [2, 'PROCEDIMIENTOS DE CALIDAD'],
                [1, 'REGISTRO DE CALIFICACIÓN DE SOLDADORES (WPQR)'],
                [1, 'CERTIFICADOS DE CALIBRACIÓN DE EQUIPOS'],
                [2, 'CERTIFICADOS DE MÁQUINAS DE SOLDAR'],
                [1, 'CERTIFICADOS DE CALIDAD DE MATERIALES'],
                [2, 'CERTIFICADOS DE MATERIAL BASE'],
                [3, 'PLACAS'],
                [3, 'PERFILES'],
                [2, 'CERTIFICADOS DE MATERIAL DE APORTE'],
                [2, 'CERTIFICADOS DE MATERIAL ADICIONAL'],
                [1, 'TRAZABILIDAD DE MATERIALES'],
                [1, 'REPORTES DE FABRICACIÓN'],
                [2, 'INSPECCIÓN DIMENSIONAL', self::NOTA_DIMENSIONAL],
                [2, 'INSPECCIÓN VISUAL DE SOLDADURA'],
                [1, 'REPORTES DE PRUEBAS NO DESTRUCTIVAS'],
                [2, 'REPORTES DE ULTRASONIDO'],
                [1, 'REPORTES DE RECUBRIMIENTOS'],
                [2, 'PREPARACIÓN DE SUPERFICIES'],
                [2, 'NOTAS DE ENTREGA DE RECUBRIMIENTOS (INS PINT)'],
                [2, 'REPORTES DE INSPECCIÓN VISUAL DE RECUBRIMIENTOS'],
                [2, 'REPORTE DE PRUEBAS DE ESPESORES'],
                [2, 'REPORTE DE PRUEBAS DE ADHERENCIAS'],
            ]],
            'Tipo C · Reducido' => ['El más ligero: materiales, trazabilidad, fabricación, PND y recubrimientos. Sin normatividad ni calificaciones.', [
                [1, 'CERTIFICADOS DE CALIDAD DE MATERIALES'],
                [2, 'CERTIFICADOS DE MATERIAL BASE'],
                [3, 'PLACAS'],
                [3, 'PERFILES'],
                [2, 'CERTIFICADOS DE MATERIAL DE APORTE'],
                [2, 'CERTIFICADOS ADICIONALES'],
                [1, 'TRAZABILIDAD DE MATERIALES'],
                [1, 'REPORTES DE FABRICACIÓN'],
                [2, 'INSPECCIÓN DIMENSIONAL', self::NOTA_DIMENSIONAL],
                [2, 'INSPECCIÓN VISUAL DE SOLDADURA'],
                [1, 'REPORTES DE PRUEBAS NO DESTRUCTIVAS'],
                [1, 'REPORTES DE RECUBRIMIENTOS'],
                [2, 'PREPARACIÓN DE SUPERFICIES'],
                [2, 'INSPECCIÓN VISUAL DE RECUBRIMIENTOS'],
                [2, 'PRUEBAS DE ESPESORES DE PINTURA'],
                [2, 'PRUEBAS DE ADHERENCIAS DE PINTURA'],
            ]],
        ];
    }
};
