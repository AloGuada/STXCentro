<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El morph apunta a un Usuario (UUID) cuando es interno y a un Externo (bigint)
     * cuando es externo, asi que la columna tiene que ser texto.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table drive_archivos alter column subido_por_id type varchar(255) using subido_por_id::varchar(255)');

            return;
        }

        Schema::table('drive_archivos', function (Blueprint $table) {
            $table->string('subido_por_id')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table drive_archivos alter column subido_por_id type bigint using subido_por_id::bigint');

            return;
        }

        Schema::table('drive_archivos', function (Blueprint $table) {
            $table->unsignedBigInteger('subido_por_id')->change();
        });
    }
};
