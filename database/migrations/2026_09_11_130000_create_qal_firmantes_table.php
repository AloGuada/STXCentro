<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién firma los formatos de Calidad y en qué orden.
     *
     * Es un solo arreglo para todos los formatos: en la hoja firman quien la
     * elaboró y la jefatura de calidad, y así nace. La jefatura queda sin
     * persona hasta que se elija en el catálogo; mientras, su raya sale en
     * blanco para firmarse a mano.
     */
    public function up(): void
    {
        Schema::create('qal_firmantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('orden');
            $table->string('etiqueta', 20);
            $table->string('cargo', 80);
            $table->string('origen', 10);
            $table->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('qal_firmantes')->insert([
            ['orden' => 1, 'etiqueta' => 'Elaboró', 'cargo' => 'Inspector de calidad', 'origen' => 'creador', 'usuario_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['orden' => 2, 'etiqueta' => 'Revisó', 'cargo' => 'Jefatura de calidad', 'origen' => 'usuario', 'usuario_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_firmantes');
    }
};
