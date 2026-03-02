<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cob_estimacion_estado_historial ALTER COLUMN usuario_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cob_estimacion_estado_historial ALTER COLUMN usuario_id SET NOT NULL');
        }
    }
};
