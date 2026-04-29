<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garantiza que no haya folios duplicados en cal_reportes. Los reportes
 * `es_plantilla=true` tienen folio NULL por diseno y NULL no cuenta como
 * duplicado en SQLite/PostgreSQL/MySQL — el constraint unique permite N
 * NULLs sin colisionar.
 *
 * IMPORTANTE: si la base productiva ya tiene folios duplicados (ej. por
 * el bug de copiar() que replicaba folio del original), esta migracion
 * fallara hasta que ejecutes:
 *
 *   php artisan cal:backfill-folios --regenerar-duplicados
 *
 * Ese comando dedupla regenerando con la formula oficial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cal_reportes', function (Blueprint $table) {
            $table->unique('folio', 'cal_reportes_folio_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cal_reportes', function (Blueprint $table) {
            $table->dropUnique('cal_reportes_folio_unique');
        });
    }
};
