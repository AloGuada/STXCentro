<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El padrón de soldadores.
     *
     * Primera tabla del prefijo `qal_`, que sustituye a `cal_`. Las `cal_*` se
     * quedan como están porque las escribe la aplicación anterior mientras siga
     * viva; el módulo nuevo no las toca y ellas mueren con esa aplicación.
     *
     * Nace ya con `clave` y `certificacion_vence_at`, que en `cal_soldadores`
     * llegaron después por alteración. La `clave` es lo que se estampa en la
     * pieza y lo que enlaza al soldador con su WPQR en el dosier: si no
     * coincide, el dosier reporta que ese soldador no tiene certificado. Es
     * nullable porque hay soldadores dados de alta antes de tenerla.
     *
     * `certificacion_vence_at` es lo que no existía en ningún lado:
     * `certificacion` es texto libre sin fecha, así que nadie podía avisar de
     * que una pieza se soldó con una certificación vencida — que es justo lo
     * que revisa el cliente en el dosier.
     */
    public function up(): void
    {
        Schema::create('qal_soldadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('clave')->nullable()->unique();
            $table->string('certificacion')->nullable();
            $table->date('certificacion_vence_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_soldadores');
    }
};
