<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rh_periodos_laborales')
            ->where('estado', 'terminado')
            ->update(['estado' => 'baja']);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE rh_periodos_laborales MODIFY COLUMN estado ENUM('activo', 'baja') NOT NULL DEFAULT 'activo'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE rh_periodos_laborales ALTER COLUMN estado TYPE VARCHAR(255)');
            DB::statement("ALTER TABLE rh_periodos_laborales ALTER COLUMN estado SET DEFAULT 'activo'");
            DB::statement('ALTER TABLE rh_periodos_laborales DROP CONSTRAINT IF EXISTS rh_periodos_laborales_estado_check');
            DB::statement("ALTER TABLE rh_periodos_laborales ADD CONSTRAINT rh_periodos_laborales_estado_check CHECK (estado IN ('activo', 'baja'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE rh_periodos_laborales MODIFY COLUMN estado ENUM('activo', 'terminado', 'baja') NOT NULL DEFAULT 'activo'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE rh_periodos_laborales DROP CONSTRAINT IF EXISTS rh_periodos_laborales_estado_check');
            DB::statement("ALTER TABLE rh_periodos_laborales ADD CONSTRAINT rh_periodos_laborales_estado_check CHECK (estado IN ('activo', 'terminado', 'baja'))");
        }
    }
};
