<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las tablas de la aplicación de mapeo 2D: etapas y piezas propias, planos,
     * reportes sobre el plano y las flechas dibujadas encima.
     *
     * Calidad ya no tiene padrón propio —la obra, la marca y la pieza son las
     * de Producción— y las juntas se capturan sobre el cordón del modelo 3D, no
     * sobre una flecha en el PDF. Van de la hija a la madre por sus llaves.
     *
     * @var list<string>
     */
    private const TABLAS = ['qal_flechas', 'qal_reportes', 'qal_piezas_planos', 'qal_piezas', 'qal_etapas'];

    /**
     * No se borra nada con datos: si alguna tiene filas, la migración se niega
     * y dice cuáles, para que se revisen antes.
     */
    public function up(): void
    {
        $conDatos = collect(self::TABLAS)
            ->filter(fn (string $tabla): bool => Schema::hasTable($tabla))
            ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])
            ->filter();

        if ($conDatos->isNotEmpty()) {
            throw new RuntimeException(
                'No se borran tablas del mapeo 2D que todavía tienen datos: '
                .$conDatos->map(fn (int $filas, string $tabla): string => "{$tabla} ({$filas})")->implode(', ')
                .'. Revísalas antes de migrar.',
            );
        }

        foreach (self::TABLAS as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }

    /**
     * Vuelven vacías, con el esquema con que se crearon.
     */
    public function down(): void
    {
        Schema::create('qal_etapas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->foreignId('obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('qal_piezas', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->integer('cantidad')->default(1);
            $table->foreignId('etapa_id')->constrained('qal_etapas')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('qal_piezas_planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pieza_id')->constrained('qal_piezas')->cascadeOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('plano_normal')->nullable();
            $table->string('dwg_path')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('qal_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('qal_piezas_planos')->cascadeOnDelete();
            $table->string('strumis_id')->nullable();
            $table->string('consecutivo')->nullable();
            $table->foreignUuid('inspector_id')->nullable()->constrained('usuarios');
            $table->string('plantilla')->nullable();
            $table->timestamp('aprobado')->nullable();
            $table->timestamp('rechazado')->nullable();
            $table->boolean('es_plantilla')->default(false);
            $table->integer('linea')->nullable();
            $table->integer('modulo')->nullable();
            $table->text('comentario')->nullable();
            $table->string('folio')->nullable()->unique('qal_reportes_folio_unique');
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores');
            $table->timestamps();
        });

        Schema::create('qal_flechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('qal_reportes')->cascadeOnDelete();
            $table->decimal('inicio_x', 10, 4);
            $table->decimal('inicio_y', 10, 4);
            $table->decimal('fin_x', 10, 4);
            $table->decimal('fin_y', 10, 4);
            $table->boolean('esdoble')->default(false);
            $table->string('tipo')->nullable();
            $table->boolean('show_number')->default(true);
            $table->integer('pagina')->default(1);
            $table->foreignId('soldador_id')->nullable()->constrained('qal_soldadores')->nullOnDelete();
            $table->timestamps();
        });
    }
};
