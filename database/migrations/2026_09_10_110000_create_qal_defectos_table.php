<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los defectos pasan a una sola tabla, tipada por ámbito.
     *
     * Soldadura y pintura vivían en dos tablas, y los accesorios iban a traer
     * tres más. Con el ámbito, las tablas de captura apuntan todas a la misma
     * FK y el Pareto junta defectos de cualquier etapa sin uniones.
     *
     * El defecto se referencia por id y ya no por su nombre: renombrar
     * «Socabación» a «Socavación» corrige el histórico en vez de partirlo en
     * dos defectos distintos.
     *
     * Se copian las filas de las dos tablas viejas conservando si estaban
     * activas; los ids cambian, pero todavía nada los referencia.
     */
    public function up(): void
    {
        Schema::create('qal_defectos', function (Blueprint $table) {
            $table->id();
            $table->string('ambito', 24);
            $table->string('nombre', 120);
            $table->string('clave', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['ambito', 'nombre']);
        });

        foreach (['qal_defectos_soldadura' => 'soldadura', 'qal_defectos_pintura' => 'pintura'] as $tabla => $ambito) {
            DB::table($tabla)->orderBy('id')->get()->each(fn (object $fila) => DB::table('qal_defectos')->insert([
                'ambito' => $ambito,
                'nombre' => $fila->nombre,
                'activo' => $fila->activo,
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at,
            ]));
        }

        Schema::dropIfExists('qal_defectos_soldadura');
        Schema::dropIfExists('qal_defectos_pintura');
    }

    /**
     * Vuelven las dos tablas con sus filas. Los defectos de accesorios no
     * tenían dónde vivir antes, así que se pierden.
     */
    public function down(): void
    {
        foreach (['qal_defectos_soldadura' => 'soldadura', 'qal_defectos_pintura' => 'pintura'] as $tabla => $ambito) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            DB::table('qal_defectos')->where('ambito', $ambito)->orderBy('id')->get()
                ->each(fn (object $fila) => DB::table($tabla)->insert([
                    'nombre' => $fila->nombre,
                    'activo' => $fila->activo,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ]));
        }

        Schema::dropIfExists('qal_defectos');
    }
};
