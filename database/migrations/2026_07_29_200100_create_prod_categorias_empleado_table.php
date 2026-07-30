<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La categoría del empleado sustituye al porcentaje manual.
     *
     * Su `valor` es un peso, no dinero: el sueldo base sale de los días
     * asistidos por el salario mínimo diario, y el peso solo sirve para
     * prorratear el excedente del destajo una vez cubiertas todas las bases.
     */
    public function up(): void
    {
        Schema::create('prod_categorias_empleado', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedInteger('valor');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('prod_grupo_empleados', function (Blueprint $table) {
            $table->foreignId('categoria_empleado_id')->nullable()->after('no_empleado')
                ->constrained('prod_categorias_empleado')->nullOnDelete();
        });

        // Todos los empleados actuales entran a una categoría base.
        if (DB::table('prod_grupo_empleados')->exists()) {
            $ahora = now();

            $categoriaId = DB::table('prod_categorias_empleado')->insertGetId([
                'nombre' => 'Base',
                'valor' => 1500,
                'orden' => 1,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('prod_grupo_empleados')->update(['categoria_empleado_id' => $categoriaId]);
        }

        Schema::table('prod_grupo_empleados', function (Blueprint $table) {
            $table->dropColumn('porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('prod_grupo_empleados', function (Blueprint $table) {
            $table->decimal('porcentaje', 5, 2)->default(100);
            $table->dropConstrainedForeignId('categoria_empleado_id');
        });

        Schema::dropIfExists('prod_categorias_empleado');
    }
};
