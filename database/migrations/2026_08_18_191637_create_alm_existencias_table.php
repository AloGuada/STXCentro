<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El saldo de un artículo en un almacén. Es caché derivada del libro de
     * movimientos y **nunca se escribe a mano**: la única puerta es
     * `App\Services\Alm\AlmacenLedger`.
     *
     * `valor` es la verdad del costeo y `costo_promedio` la derivada
     * (`valor / cantidad`). Al revés —guardando sólo el promedio y multiplicando
     * por la cantidad— cada movimiento pierde el redondeo de la cuarta cifra, y
     * a las cien recepciones el inventario ya no vale lo que dice.
     *
     * `restrictOnDelete` en las dos llaves: un almacén o un artículo con saldo
     * no se borra, se desactiva. Si se borrara, el kardex quedaría hablando de
     * algo que no existe.
     *
     * El unique va nombrado a propósito. Significa **una ubicación por artículo
     * por almacén**, que es lo que el contrato del frontend asume con su único
     * `ubicacion_id`; si algún día piden multi-ubicación, hay que poder soltarlo
     * por nombre.
     */
    public function up(): void
    {
        Schema::create('alm_existencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->decimal('costo_promedio', 16, 4)->default(0);
            $table->decimal('valor', 18, 4)->default(0);
            $table->foreignId('ubicacion_id')->nullable()->constrained('alm_ubicaciones')->nullOnDelete();
            $table->timestamp('ultimo_movimiento_at')->nullable();
            $table->timestamps();

            $table->unique(['almacen_id', 'producto_id'], 'alm_existencias_almacen_producto_unique');
            $table->index('producto_id');
            $table->index(['almacen_id', 'ubicacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_existencias');
    }
};
