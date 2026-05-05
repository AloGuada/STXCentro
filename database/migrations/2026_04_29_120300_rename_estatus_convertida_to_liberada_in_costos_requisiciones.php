<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('costos_requisiciones')
            ->where('estatus', 'convertida')
            ->update(['estatus' => 'liberada']);
    }

    public function down(): void
    {
        DB::table('costos_requisiciones')
            ->where('estatus', 'liberada')
            ->update(['estatus' => 'convertida']);
    }
};
