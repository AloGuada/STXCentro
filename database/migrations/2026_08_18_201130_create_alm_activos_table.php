<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las piezas de los artículos marcados «por pieza»: una fila por número de
     * serie.
     *
     * No es un inventario aparte. Cada pieza suma 1 a la existencia de su
     * artículo, y el saldo lo sigue llevando `alm_existencias` — esto responde
     * *cuáles* son y en qué anda cada una, que es lo que el kardex por cantidad
     * no puede decir.
     *
     * `marca`, `modelo` e `id_mantenimiento` viven aquí y no en el catálogo:
     * dos altas del mismo artículo pueden traer marcas distintas —la reposición
     * se compró Makita aunque las diez primeras eran DeWalt— y la refacción se
     * pide por el modelo de *esa* pieza.
     *
     * `costo` también es de la pieza: dos pulidoras del mismo modelo compradas
     * con dos años de diferencia no valen lo mismo, y es lo que permite que la
     * baja descargue al costo real en vez de al promedio.
     */
    public function up(): void
    {
        Schema::create('alm_activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->string('no_serie', 120);
            $table->string('codigo_barras')->nullable()->index();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('id_mantenimiento', 150)->nullable();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('alm_ubicaciones')->nullOnDelete();
            $table->decimal('costo', 16, 4)->default(0);
            $table->string('estatus', 20)->default('disponible');
            $table->string('condicion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // La serie identifica la pieza dentro de su artículo, no en toda la
            // empresa: dos fabricantes distintos pueden repetir un número.
            $table->unique(['producto_id', 'no_serie']);
            $table->index(['almacen_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_activos');
    }
};
