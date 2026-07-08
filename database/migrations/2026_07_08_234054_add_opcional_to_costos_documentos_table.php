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
        Schema::table('costos_documentos', function (Blueprint $table) {
            // Documento opcional: no es obligatorio adjuntarlo al crear la
            // solicitud de pago de este tipo.
            $table->boolean('opcional')->default(false)->after('multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_documentos', function (Blueprint $table) {
            $table->dropColumn('opcional');
        });
    }
};
