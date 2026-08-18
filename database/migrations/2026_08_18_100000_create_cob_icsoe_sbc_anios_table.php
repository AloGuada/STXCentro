<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de parámetros del IMSS por año: salario base de cotización diario,
 * costo de construcción por m² publicado en el DOF y prima de riesgo de la
 * empresa. Reemplaza la tabla hardcodeada del bosquejo para que capturar un año
 * nuevo no requiera un deploy.
 */
return new class extends Migration
{
    /** @var list<array{anio: int, sbc: float, costo_m2: float, prima_riesgo: float}> */
    private array $semilla = [
        ['anio' => 2023, 'sbc' => 217.67, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2024, 'sbc' => 248.93, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2025, 'sbc' => 275.00, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
        ['anio' => 2026, 'sbc' => 290.00, 'costo_m2' => 1154, 'prima_riesgo' => 7.58875],
    ];

    public function up(): void
    {
        Schema::create('cob_icsoe_sbc_anios', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio')->unique();
            $table->decimal('sbc', 10, 2);
            $table->decimal('costo_m2', 12, 2)->default(0);
            $table->decimal('prima_riesgo', 8, 5)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        // La semilla va en la migración, no solo en el seeder: producción no
        // corre seeders y sin catálogo el módulo no puede calcular nada.
        $ahora = now();

        foreach ($this->semilla as $fila) {
            DB::table('cob_icsoe_sbc_anios')->insert([
                ...$fila,
                'notas' => 'Valor inicial de referencia; verificar contra el DOF.',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_icsoe_sbc_anios');
    }
};
