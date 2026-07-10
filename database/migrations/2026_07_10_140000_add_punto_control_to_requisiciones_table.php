<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            // Punto de control: gate manual (con permiso propio) que habilita el
            // envío a aprobación.
            $table->boolean('control_verificado')->default(false)->after('estatus');
            $table->foreignUuid('control_por')->nullable()->after('control_verificado')
                ->constrained('usuarios')->nullOnDelete();
            $table->timestamp('control_at')->nullable()->after('control_por');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisiciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('control_por');
            $table->dropColumn(['control_verificado', 'control_at']);
        });
    }
};
