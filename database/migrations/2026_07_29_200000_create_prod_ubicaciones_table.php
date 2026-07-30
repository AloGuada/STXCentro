<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las ubicaciones sustituyen a `linea` y `modulo`: eran dos enteros sueltos
     * y un grupo puede trabajar en varias, así que pasan a ser un catálogo de
     * texto con relación muchos a muchos.
     *
     * Lo que ya estaba capturado no se tira: cada combinación linea/modulo
     * existente se convierte en una ubicación con ese nombre.
     */
    public function up(): void
    {
        Schema::create('prod_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('prod_grupo_trabajo_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_trabajo_id')->constrained('prod_grupos_trabajo')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('prod_ubicaciones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grupo_trabajo_id', 'ubicacion_id']);
        });

        $this->migrarLineaYModulo();

        Schema::table('prod_grupos_trabajo', function (Blueprint $table) {
            $table->dropColumn(['linea', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::table('prod_grupos_trabajo', function (Blueprint $table) {
            $table->integer('linea')->default(0);
            $table->integer('modulo')->default(0);
        });

        Schema::dropIfExists('prod_grupo_trabajo_ubicaciones');
        Schema::dropIfExists('prod_ubicaciones');
    }

    private function migrarLineaYModulo(): void
    {
        $ahora = now();
        $cache = [];

        foreach (DB::table('prod_grupos_trabajo')->get(['id', 'linea', 'modulo']) as $grupo) {
            $partes = [];

            if (! empty($grupo->linea)) {
                $partes[] = 'Línea '.$grupo->linea;
            }
            if (! empty($grupo->modulo)) {
                $partes[] = 'Módulo '.$grupo->modulo;
            }

            if ($partes === []) {
                continue;
            }

            $nombre = implode(' · ', $partes);

            $ubicacionId = $cache[$nombre] ??= DB::table('prod_ubicaciones')->insertGetId([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('prod_grupo_trabajo_ubicaciones')->insert([
                'grupo_trabajo_id' => $grupo->id,
                'ubicacion_id' => $ubicacionId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }
};
