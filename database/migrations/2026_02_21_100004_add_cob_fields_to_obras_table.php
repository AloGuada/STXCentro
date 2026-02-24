<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('tipo_contrato')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->decimal('monto_iva', 15, 2)->nullable();
            $table->decimal('anticipo', 15, 2)->nullable();
            $table->decimal('garantia', 15, 2)->nullable();
            $table->decimal('peso', 15, 2)->nullable();
            $table->decimal('porcentaje_fabricacion', 5, 2)->nullable();
            $table->decimal('porcentaje_montaje', 5, 2)->nullable();
            $table->decimal('porcentaje_otros', 5, 2)->nullable();
            $table->string('descripcion_otros')->nullable();
            $table->boolean('activa')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn([
                'tipo_contrato', 'monto', 'monto_iva', 'anticipo', 'garantia',
                'peso', 'porcentaje_fabricacion', 'porcentaje_montaje',
                'porcentaje_otros', 'descripcion_otros', 'activa',
            ]);
        });
    }
};
