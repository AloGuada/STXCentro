<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('factura_id')->nullable()->after('orden_compra_id')
                ->constrained('costos_facturas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_id');
        });
    }
};
