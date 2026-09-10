<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permisos del catálogo de áreas.
     *
     * Sin `eliminar`: aquí nada se borra, se desactiva, para no dejar artículos
     * apuntando a un área que ya no existe.
     *
     * @var list<string>
     */
    private array $permisos = [
        'alm.areas.ver',
        'alm.areas.crear',
        'alm.areas.editar',
    ];

    /**
     * Se asignan a super-admin en la misma migración: el menú se pinta con los
     * permisos del usuario, así que un permiso nuevo sin asignar deja la
     * pantalla invisible para todos.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo($this->permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
