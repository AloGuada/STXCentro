<?php

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las piezas pasan a colgar de un catálogo versionado. Cada obra que ya
     * tenía conceptos estrena su catálogo v1 vigente con todas sus piezas.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->foreignId('catalogo_id')->nullable()->after('obra_id')->constrained('prod_catalogos')->cascadeOnDelete();
        });

        Obra::query()
            ->whereHas('conceptos')
            ->with('conceptos:id,obra_id')
            ->each(function (Obra $obra): void {
                $catalogo = Catalogo::create([
                    'obra_id' => $obra->id,
                    'nombre' => 'Catálogo '.$obra->no,
                    'version' => 1,
                    'vigente' => true,
                ]);

                Concepto::query()
                    ->where('obra_id', $obra->id)
                    ->update(['catalogo_id' => $catalogo->id]);
            });
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_id');
        });
    }
};
