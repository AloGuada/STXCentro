<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento ICSOE de un proyecto: la meta de mano de obra que el IMSS espera
 * comprobar contra lo realmente cotizado, y el riesgo en cuotas de la diferencia.
 *
 * La fila de `badge_configs` se crea AQUÍ, en la misma migración que la tabla:
 * `badge_configs` referencia tabla/columna por dato, así que un badge apuntando a
 * una tabla inexistente tira 500 en toda página que dibuje el sidebar. Estando
 * juntas es imposible que exista una sin la otra, ni en un rollback parcial.
 */
return new class extends Migration
{
    private const BADGE = 'ICSOE pendientes de verificación';

    public function up(): void
    {
        Schema::create('cob_icsoe_seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->unique()->constrained('proyectos')->cascadeOnDelete();
            $table->string('metodo', 20);
            $table->string('estatus', 30)->default('vigente')->index();

            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            $table->decimal('superficie_m2', 12, 2)->nullable();
            $table->decimal('costo_m2', 12, 2)->nullable();
            $table->decimal('porcentaje_mo', 5, 2)->default(30);
            $table->decimal('prima_riesgo', 8, 5)->default(0);

            /** Valor a ejecutar del proyecto con el que se calculó (snapshot). */
            $table->decimal('monto_base', 15, 2)->default(0);
            /** El "$X" del aviso "$X → $Y"; null cuando no hay cambio por verificar. */
            $table->decimal('monto_base_anterior', 15, 2)->nullable();

            $table->decimal('mo_estimada_total', 15, 2)->default(0);
            $table->decimal('mo_estimada_total_anterior', 15, 2)->nullable();
            $table->decimal('mo_estimada_diaria', 15, 4)->default(0);
            $table->unsignedInteger('total_dias')->default(0);

            /** Denormalizados: el index ordena y filtra sin cargar los meses. */
            $table->decimal('mo_real_total', 15, 2)->default(0);
            $table->decimal('diferencia_mo', 15, 2)->default(0);
            $table->decimal('monto_riesgo', 15, 2)->default(0);

            $table->string('motivo_cambio')->nullable();
            $table->timestamp('recalculado_at')->nullable();
            $table->timestamp('verificado_at')->nullable();
            $table->foreignUuid('verificado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['estatus', 'recalculado_at']);
        });

        DB::table('badge_configs')->updateOrInsert(
            ['nombre' => self::BADGE],
            [
                'tabla' => 'cob_icsoe_seguimientos',
                'campo_estatus' => 'estatus',
                'operador' => '=',
                'valor_estatus' => 'pendiente_verificacion',
                'condiciones_extra' => null,
                'rol' => 'admin-cobranza',
                'nav_href' => '/admin/cob/icsoe',
                'filter_href' => '/admin/cob/icsoe?estatus=pendiente_verificacion',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        // El badge primero: si quedara apuntando a una tabla dropeada, el
        // sidebar entero devolvería 500.
        DB::table('badge_configs')->where('nombre', self::BADGE)->delete();

        Schema::dropIfExists('cob_icsoe_seguimientos');
    }
};
