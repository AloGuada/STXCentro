<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            // Modo "dedazo": un solo proveedor (sin comparativa de 3) y, tras la
            // verificación gerencial, se convierte directo a OC sin cadena de
            // aprobación.
            $table->boolean('modo_dedazo')->default(false)->after('control_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropColumn('modo_dedazo');
        });
    }
};
