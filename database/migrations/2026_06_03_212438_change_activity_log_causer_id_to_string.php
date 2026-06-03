<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El causer de las actividades es siempre el Usuario autenticado, cuya llave
 * primaria es un UUID. La columna `causer_id` la creó `nullableMorphs` como
 * bigint, lo que en Postgres lanza 22P02 al insertar el UUID (en SQLite pasa
 * por el tipado laxo, por eso no se reproducía en dev). Se recrea como string.
 * `subject_id` se deja en bigint: todos los subjects con LogsActivity usan
 * llave entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('causer');
            $table->dropColumn('causer_id');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('causer_id')->nullable()->after('causer_type');
            $table->index(['causer_type', 'causer_id'], 'causer');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('causer');
            $table->dropColumn('causer_id');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->unsignedBigInteger('causer_id')->nullable()->after('causer_type');
            $table->index(['causer_type', 'causer_id'], 'causer');
        });
    }
};
