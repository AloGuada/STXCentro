<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // numero_empleado may already exist from a partial run
        if (! Schema::hasColumn('rh_periodos_laborales', 'numero_empleado')) {
            Schema::table('rh_periodos_laborales', function (Blueprint $table) {
                $table->string('numero_empleado')->nullable()->after('tipo_contrato');
            });
        }

        // SQLite requires table recreation to change column nullability
        DB::transaction(function () {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement('
                CREATE TABLE rh_periodos_laborales_tmp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    persona_id INTEGER NOT NULL,
                    puesto_id INTEGER NULL,
                    fecha_inicio DATE NOT NULL,
                    fecha_fin DATE NULL,
                    estado VARCHAR NOT NULL DEFAULT \'activo\',
                    salario NUMERIC NULL,
                    tipo_contrato VARCHAR NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    requisicion_id INTEGER NULL,
                    numero_empleado VARCHAR NULL,
                    FOREIGN KEY (persona_id) REFERENCES rh_personas(id) ON DELETE CASCADE,
                    FOREIGN KEY (puesto_id) REFERENCES rh_puestos(id),
                    FOREIGN KEY (requisicion_id) REFERENCES rh_requisiciones(id) ON DELETE SET NULL
                )
            ');

            DB::statement('
                INSERT INTO rh_periodos_laborales_tmp
                SELECT id, persona_id, puesto_id, fecha_inicio, fecha_fin, estado, salario, tipo_contrato, created_at, updated_at, requisicion_id, numero_empleado
                FROM rh_periodos_laborales
            ');

            DB::statement('DROP TABLE rh_periodos_laborales');
            DB::statement('ALTER TABLE rh_periodos_laborales_tmp RENAME TO rh_periodos_laborales');

            DB::statement('PRAGMA foreign_keys = ON');
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->dropColumn('numero_empleado');
        });
    }
};
