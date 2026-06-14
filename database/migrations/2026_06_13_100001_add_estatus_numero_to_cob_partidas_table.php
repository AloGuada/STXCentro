<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_partidas', function (Blueprint $table) {
            $table->string('estatus')->default('abierta')->after('es_adicional');
            $table->unsignedInteger('numero_adicional')->nullable()->after('estatus');
        });
    }

    public function down(): void
    {
        Schema::table('cob_partidas', function (Blueprint $table) {
            $table->dropColumn(['estatus', 'numero_adicional']);
        });
    }
};
