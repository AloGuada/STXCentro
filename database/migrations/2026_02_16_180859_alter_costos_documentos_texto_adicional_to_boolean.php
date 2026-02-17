<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_documentos', function (Blueprint $table) {
            $table->dropColumn('texto_adicional');
        });

        Schema::table('costos_documentos', function (Blueprint $table) {
            $table->boolean('texto_adicional')->default(false)->after('texto');
        });
    }

    public function down(): void
    {
        Schema::table('costos_documentos', function (Blueprint $table) {
            $table->dropColumn('texto_adicional');
        });

        Schema::table('costos_documentos', function (Blueprint $table) {
            $table->text('texto_adicional')->nullable()->after('texto');
        });
    }
};
