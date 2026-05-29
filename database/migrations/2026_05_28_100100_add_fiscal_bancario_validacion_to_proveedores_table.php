<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            // Datos fiscales
            $table->string('tipo_persona')->nullable()->after('rfc');
            $table->foreignId('regimen_fiscal_id')->nullable()->after('tipo_persona')
                ->constrained('regimenes_fiscales')->nullOnDelete();
            $table->string('codigo_postal', 10)->nullable()->after('regimen_fiscal_id');
            $table->text('domicilio_fiscal')->nullable()->after('codigo_postal');
            $table->text('domicilio_compra')->nullable()->after('domicilio_fiscal');
            $table->string('giro')->nullable()->after('domicilio_compra');

            // Cuenta bancaria (una sola por proveedor)
            $table->string('banco')->nullable()->after('giro');
            $table->string('titular_cuenta')->nullable()->after('banco');
            $table->string('numero_cuenta')->nullable()->after('titular_cuenta');
            $table->string('clabe', 18)->nullable()->after('numero_cuenta');
            $table->string('moneda_cuenta', 3)->default('MXN')->after('clabe');

            // Validación documental
            $table->string('estatus')->default('activo')->after('activo');
            $table->foreignUuid('validado_por')->nullable()->after('estatus')
                ->constrained('usuarios')->nullOnDelete();
            $table->timestamp('validado_at')->nullable()->after('validado_por');
            $table->text('observacion_validacion')->nullable()->after('validado_at');
            $table->foreignUuid('creado_por')->nullable()->after('observacion_validacion')
                ->constrained('usuarios')->nullOnDelete();
        });

        // Backfill: proveedores existentes ya operativos quedan en estatus activo.
        DB::table('proveedores')->where('activo', true)->update(['estatus' => 'activo']);
        DB::table('proveedores')->where('activo', false)->update(['estatus' => 'rechazado']);
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regimen_fiscal_id');
            $table->dropConstrainedForeignId('validado_por');
            $table->dropConstrainedForeignId('creado_por');
            $table->dropColumn([
                'tipo_persona',
                'codigo_postal',
                'domicilio_fiscal',
                'domicilio_compra',
                'giro',
                'banco',
                'titular_cuenta',
                'numero_cuenta',
                'clabe',
                'moneda_cuenta',
                'estatus',
                'validado_at',
                'observacion_validacion',
            ]);
        });
    }
};
