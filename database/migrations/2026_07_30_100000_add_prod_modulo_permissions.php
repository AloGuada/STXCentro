<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permisos = [
        'prod.catalogos.ver',
        'prod.catalogos.crear',
        'prod.catalogos.editar',
        'prod.catalogos.eliminar',
        'prod.ubicaciones.ver',
        'prod.ubicaciones.crear',
        'prod.ubicaciones.editar',
        'prod.ubicaciones.eliminar',
        'prod.categorias-empleado.ver',
        'prod.categorias-empleado.crear',
        'prod.categorias-empleado.editar',
        'prod.categorias-empleado.eliminar',
        'prod.asistencia.ver',
        'prod.asistencia.registrar',
        'prod.pagos-extra.ver',
        'prod.pagos-extra.crear',
        'prod.pagos-extra.eliminar',
        'prod.liquidaciones.ver',
        'prod.configuracion.ver',
        'prod.configuracion.editar',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminProduccion = Role::where('name', 'admin-produccion')->where('guard_name', 'web')->first();

        if ($adminProduccion) {
            $adminProduccion->givePermissionTo($this->permisos);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();
    }
};
