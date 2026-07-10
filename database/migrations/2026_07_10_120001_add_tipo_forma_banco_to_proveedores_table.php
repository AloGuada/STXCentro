<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Libera el nombre `banco` para la relación al catálogo; el texto libre
        // histórico se conserva en `banco_nombre`.
        Schema::table('proveedores', function (Blueprint $table) {
            $table->renameColumn('banco', 'banco_nombre');
        });

        // Tercero (nombre libre) y Servicio (luz/agua) no tienen RFC.
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('rfc')->nullable()->change();
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->foreignId('banco_id')->nullable()->after('banco_nombre')
                ->constrained('bancos')->nullOnDelete();

            // Forma de pago: transferencia habilita la cuenta bancaria;
            // cheque/efectivo la omite. Aplica solo a Proveedor y Tercero.
            $table->string('forma_pago')->nullable()->after('moneda_cuenta');

            // Servicios (luz/agua) que se pagan por banca en línea con número de
            // servicio y referencia, sin cuenta bancaria propia.
            $table->string('numero_servicio')->nullable()->after('forma_pago');
            $table->string('referencia_servicio')->nullable()->after('numero_servicio');
        });

        // Backfill: los proveedores existentes quedan con transferencia como
        // default y su banco de texto ligado al catálogo por nombre cuando coincida.
        DB::table('proveedores')->whereNull('forma_pago')->update(['forma_pago' => 'transferencia']);

        foreach (DB::table('bancos')->get() as $banco) {
            DB::table('proveedores')
                ->whereNull('banco_id')
                ->whereRaw('LOWER(TRIM(banco_nombre)) = ?', [mb_strtolower(trim($banco->nombre))])
                ->update(['banco_id' => $banco->id]);
        }
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banco_id');
            $table->dropColumn(['forma_pago', 'numero_servicio', 'referencia_servicio']);
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->renameColumn('banco_nombre', 'banco');
        });
    }
};
