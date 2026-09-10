<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $permisos = [
        'alm.almacenes.ver',
        'alm.almacenes.crear',
        'alm.almacenes.editar',
        'alm.almacenes.eliminar',
    ];

    /**
     * Permisos del catálogo de almacenes. Se dan a super-admin porque el menú se
     * pinta con los permisos del usuario y el módulo nace invisible para todos.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();

        $superAdmin?->givePermissionTo($this->permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();
    }
};
