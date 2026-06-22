<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aplana la jerarquía de adicionales: una obra adicional deja de colgar de una
 * obra base y pasa a ser una obra hermana dentro del proyecto. Conserva `tipo`
 * (base/adicional) y `proyecto_id`; solo se elimina el vínculo padre-hijo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_padre_id');
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->foreignId('obra_padre_id')->nullable()->after('proyecto_id')->constrained('obras')->nullOnDelete();
        });
    }
};
