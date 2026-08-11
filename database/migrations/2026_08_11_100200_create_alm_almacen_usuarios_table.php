<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * A qué almacenes está asignado cada quien.
     *
     * Hasta ahora `responsable_id` era informativo y quien tenía el permiso
     * veía y movía todos los almacenes: el almacenista de T4 podía sacar
     * material de MBP. Con el kardex de por medio eso deja de ser inocuo.
     *
     * Arranca permisivo a propósito: la tabla nace vacía y todo el que ya podía
     * ver el catálogo se lleva `alm.almacenes.ver-todos`, así que hoy nadie
     * pierde acceso. El día que haya que restringir a alguien basta con
     * quitarle ese permiso y asignarle sus almacenes — sin retrofitear el
     * esquema, que es la parte cara.
     */
    public function up(): void
    {
        Schema::create('alm_almacen_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('alm_almacenes')->cascadeOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['almacen_id', 'usuario_id']);
        });

        $this->conservarAccesoActual();
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_almacen_usuarios');
    }

    /**
     * Todo rol que hoy puede ver el catálogo de almacenes se lleva `ver-todos`,
     * para que la restricción nueva no le quite acceso a nadie de golpe.
     */
    private function conservarAccesoActual(): void
    {
        $verTodos = DB::table('permissions')
            ->where('name', 'alm.almacenes.ver-todos')
            ->where('guard_name', 'web')
            ->value('id');

        $ver = DB::table('permissions')
            ->where('name', 'alm.almacenes.ver')
            ->where('guard_name', 'web')
            ->value('id');

        if ($verTodos === null || $ver === null) {
            return;
        }

        $roles = DB::table('role_has_permissions')
            ->where('permission_id', $ver)
            ->pluck('role_id');

        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $verTodos,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
