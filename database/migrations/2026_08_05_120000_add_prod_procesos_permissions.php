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
        'prod.procesos.ver',
        'prod.procesos.crear',
        'prod.procesos.editar',
        'prod.procesos.eliminar',
    ];

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

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();
    }
};
