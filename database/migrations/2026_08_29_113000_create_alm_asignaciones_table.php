<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuánto del saldo de una existencia está comprometido con una obra.
     *
     * No es un inventario aparte: es la **partición** del renglón de
     * `alm_existencias`, igual que `alm_activos` es su desglose por pieza. El
     * saldo lo sigue llevando la existencia; esto responde *de quién es*.
     *
     *   PIN · Primario gris  120 LTS
     *     ├── 30  Azvindi
     *     ├── 20  T4
     *     └── 70  libre        ← no tiene renglón: es lo que sobra
     *
     * `libre` no se guarda, se deduce: `existencia.cantidad − SUM(asignaciones)`.
     * Guardarlo sería una tercera verdad que mantener, y la primera vez que se
     * desincronizara nadie sabría cuál de las tres creer.
     *
     * Cuelga de la existencia y no del artículo a propósito: el mismo artículo
     * puede estar todo comprometido en un almacén y libre en otro, y son dos
     * hechos distintos.
     *
     * `cantidad` la escribe **sólo** `App\Services\Alm\AlmacenLedger`, dentro de
     * la misma transacción que mueve el saldo. Por eso está fuera del
     * `$fillable` del modelo: la partición y el saldo no pueden confirmarse por
     * separado sin que el invariante `SUM(asignaciones) <= cantidad` deje de
     * valer.
     *
     * `restrictOnDelete` en la obra: una obra con material comprometido no se
     * borra. `cascadeOnDelete` en la existencia porque sin renglón de saldo la
     * partición no significa nada — pero la existencia tampoco se borra nunca,
     * sus propias llaves son `restrict`.
     */
    public function up(): void
    {
        Schema::create('alm_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('existencia_id')->constrained('alm_existencias')->cascadeOnDelete();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->decimal('cantidad', 16, 4)->default(0);
            $table->timestamps();

            // Un solo renglón por obra: la partición se agrega, no se apila. El
            // rastro de cómo llegó a ese número vive en el kardex, que ya guarda
            // la obra de cada movimiento.
            $table->unique(['existencia_id', 'obra_id'], 'alm_asignaciones_existencia_obra_unique');
            // «Qué tiene comprometido esta obra», que es la otra mitad de la
            // pregunta y la que no puede resolver el unique.
            $table->index('obra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_asignaciones');
    }
};
