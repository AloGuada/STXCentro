<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La obra de Calidad deja de ser padrón propio: pasa a ser la extensión 1:1
     * de la obra del portal, la misma que usa Producción.
     *
     * Hasta aquí Calidad daba de alta sus obras aparte, con su propio número y
     * descripción, y el puente a `obras` era opcional. Desde aquí el número, la
     * descripción y si está activa se leen de `obras`; Calidad sólo guarda lo
     * suyo (responsable de calidad, nota de PND, piezas totales).
     *
     * Se conserva la tabla y su nombre porque PND, montaje e incidencias ya
     * cuelgan de `qal_obra_id`.
     *
     * Los datos se mueven con `DB::table` y no con los modelos a propósito: el
     * modelo ya describe la tabla nueva, no la que esta migración encuentra.
     *
     *  1. Las filas sin `obra_id` se ligan por número de obra. Si el número no
     *     existe en el portal se da de alta ahí: este módulo no ha llegado a
     *     producción y esas filas sólo pueden venir del seeder de desarrollo.
     *  2. Si dos filas acaban en la misma obra se detiene: juntarlas es decidir
     *     de quién es cada informe, y eso no lo decide una migración.
     *  3. Toda obra con catálogo de Producción recibe su ficha de Calidad. Va
     *     después del cambio de esquema: antes, la ficha exigía número propio.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->ligarPorNumero();
            $this->comprobarUnaFichaPorObra();
        });

        Schema::table('qal_obras', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
        });

        Schema::table('qal_obras', function (Blueprint $table) {
            $table->dropColumn(['no', 'descripcion', 'activa']);
            $table->unsignedBigInteger('obra_id')->nullable(false)->change();
            $table->unique('obra_id');
            // Restringir y no cascada: borrar la obra del portal no debe
            // llevarse en silencio los informes de laboratorio y el montaje.
            $table->foreign('obra_id')->references('id')->on('obras')->restrictOnDelete();
        });

        $this->altaDeLasObrasDeProduccion();
    }

    public function down(): void
    {
        Schema::table('qal_obras', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->dropUnique(['obra_id']);
        });

        Schema::table('qal_obras', function (Blueprint $table) {
            $table->string('no')->nullable()->after('obra_id');
            $table->string('descripcion')->nullable()->after('no');
            $table->boolean('activa')->default(true)->after('pnd_nota');
            $table->unsignedBigInteger('obra_id')->nullable()->change();
            $table->foreign('obra_id')->references('id')->on('obras')->nullOnDelete();
        });

        DB::table('qal_obras')->orderBy('id')->get(['id', 'obra_id'])->each(function (object $fila): void {
            $obra = DB::table('obras')->where('id', $fila->obra_id)->first(['no', 'descripcion', 'activa']);

            DB::table('qal_obras')->where('id', $fila->id)->update([
                'no' => $obra?->no ?? '',
                'descripcion' => $obra?->descripcion,
                'activa' => $obra?->activa ?? true,
            ]);
        });
    }

    private function ligarPorNumero(): void
    {
        DB::table('qal_obras')->whereNull('obra_id')->orderBy('id')->get()->each(function (object $fila): void {
            $obraId = DB::table('obras')->where('no', $fila->no)->orderBy('id')->value('id')
                ?? DB::table('obras')->insertGetId([
                    'no' => $fila->no,
                    'descripcion' => $fila->descripcion ?? $fila->no,
                    'activa' => $fila->activa,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('qal_obras')->where('id', $fila->id)->update(['obra_id' => $obraId]);
        });
    }

    private function comprobarUnaFichaPorObra(): void
    {
        $repetidas = DB::table('qal_obras')
            ->select('obra_id')
            ->groupBy('obra_id')
            ->havingRaw('count(*) > 1')
            ->pluck('obra_id');

        if ($repetidas->isNotEmpty()) {
            throw new RuntimeException(
                'Varias fichas de Calidad apuntan a la misma obra del portal (obras.id: '
                .$repetidas->implode(', ').'). Hay que decidir a mano cuál conserva sus informes.'
            );
        }
    }

    private function altaDeLasObrasDeProduccion(): void
    {
        DB::table('prod_catalogos')
            ->whereNotExists(fn ($consulta) => $consulta
                ->select(DB::raw(1))
                ->from('qal_obras')
                ->whereColumn('qal_obras.obra_id', 'prod_catalogos.obra_id'))
            ->distinct()
            ->orderBy('obra_id')
            ->pluck('obra_id')
            ->each(fn (int $obraId) => DB::table('qal_obras')->insert([
                'obra_id' => $obraId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }
};
