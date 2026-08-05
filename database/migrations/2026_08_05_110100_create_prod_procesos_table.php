<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procesos que se pagan como destajo: soldadura, pintura y los que se
     * agreguen después.
     *
     * Cada proceso declara los eventos del export de planta que disparan su
     * pago. El evento es unique a propósito: si un mismo número pudiera colgar
     * de dos procesos, un movimiento se cargaría a los dos y la pieza se
     * pagaría dos veces sin que nadie lo note. Un proceso sin eventos existe y
     * es válido — simplemente sólo se captura a mano.
     */
    public function up(): void
    {
        Schema::create('prod_procesos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('prod_proceso_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->constrained('prod_procesos')->cascadeOnDelete();
            $table->string('evento', 20);
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique('evento');
        });

        $procesos = [
            ['nombre' => 'Soldadura', 'orden' => 1, 'evento' => '75'],
            ['nombre' => 'Pintura', 'orden' => 2, 'evento' => '85'],
        ];

        foreach ($procesos as $proceso) {
            $id = DB::table('prod_procesos')->insertGetId([
                'nombre' => $proceso['nombre'],
                'orden' => $proceso['orden'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('prod_proceso_eventos')->insert([
                'proceso_id' => $id,
                'evento' => $proceso['evento'],
                'descripcion' => $proceso['nombre'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_proceso_eventos');
        Schema::dropIfExists('prod_procesos');
    }
};
