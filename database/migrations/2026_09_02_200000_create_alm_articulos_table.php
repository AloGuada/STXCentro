<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El catálogo de Almacén, que hasta hoy vivía prestado dentro de
     * `costos_productos`.
     *
     * Esa tabla nació el 2026-06-29 con ocho columnas —código, descripción,
     * unidad, activo, quién la creó y los timestamps—, que es todo lo que
     * Compras necesita para cotizar. Las otras diez llegaron en agosto, en dos
     * migraciones que se llaman solas: `add_inventario` y `add_catalogo_almacen`.
     * O sea que hoy, por número de columnas, la tabla es más de Almacén que de
     * Compras, y eso obligaba a que un seeder de almacén escribiera en la tabla
     * con la que Compras cotiza.
     *
     * Ahora cada lado guarda lo suyo y el vínculo es explícito:
     * `producto_id` **anulable**, porque un artículo puede existir sin identidad
     * de compra. Eso es lo que hace que la carga inicial de un almacén no tenga
     * que emparejar nada contra Compras para poder abrir: levanta lo suyo, y
     * ligar es después, a mano y sobre una columna que se puede volver a poner
     * en null.
     *
     * `restrictOnDelete` copia lo que ya hacen las siete tablas de Almacén: un
     * producto con artículo no se borra, se desactiva. `alm_areas` sigue con
     * `nullOnDelete` porque el área no gobierna ningún flujo, sólo filtra.
     *
     * Descripción y unidad son **propias y no leídas del producto**: un artículo
     * sin ligar tiene que sostenerse solo, y cuando esté ligado, que difieran no
     * es un defecto — es el dato que la pantalla de ligado necesita enseñar.
     */
    public function up(): void
    {
        Schema::create('alm_articulos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->nullable()
                ->constrained('costos_productos')->restrictOnDelete();

            // Propias. `codigo` anulable como en costos_productos, donde también
            // lo es: existen los que Compras teclea al vuelo sin clasificar.
            $table->string('codigo')->nullable()->unique();
            $table->string('descripcion');
            $table->string('unidad', 20);

            // Las que emigran. `controla_inventario` NO viene: aquí el renglón
            // es la respuesta. Existir en esta tabla es llevar kardex, y no
            // existir es no llevarlo —sea porque es un servicio o porque nadie
            // lo ha clasificado todavía—. Como booleano se podía desincronizar
            // del hecho que describía, y se desincronizó: quedaron 59 productos
            // con código, clasificados y fuera del inventario, imposibles de
            // recibir porque los selectores filtran por esa bandera.
            $table->string('codigo_barras')->nullable();
            $table->string('idsteelex')->nullable();
            $table->foreignId('area_id')->nullable()
                ->constrained('alm_areas')->nullOnDelete();
            $table->string('tipo', 30);
            $table->boolean('se_controla_por_pieza')->default(false);
            $table->boolean('requiere_verificacion')->default(false);
            $table->decimal('stock_minimo', 16, 3)->nullable();
            $table->string('clasificacion_abc', 1)->nullable();

            $table->string('imagen')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignUuid('creado_por')->nullable()
                ->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index('codigo_barras');
            $table->index('area_id');
        });

        // Un producto, a lo más un artículo. Sin esto, dos artículos apuntando
        // al mismo producto parten la existencia de un mismo insumo en dos
        // renglones y el kardex deja de cuadrar contra Compras.
        //
        // Va como índice parcial y no como `unique()` de Laravel porque la
        // columna es anulable y los artículos sin ligar son muchos: en MySQL
        // varios NULL conviven en un unique, pero el índice parcial lo deja
        // dicho explícitamente y no depende del motor.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX alm_articulos_producto_unique
                           ON alm_articulos (producto_id) WHERE producto_id IS NOT NULL');
        } else {
            Schema::table('alm_articulos', function (Blueprint $table) {
                $table->unique('producto_id', 'alm_articulos_producto_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_articulos');
    }
};
