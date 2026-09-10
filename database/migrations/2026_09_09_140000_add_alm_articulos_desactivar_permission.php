<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Desactivar un artículo va aparte de editarlo: corregir un nombre es
     * mantenimiento, sacarlo de circulación es una decisión sobre el catálogo.
     *
     * Nace en manos de quien hoy puede editar, para que el botón no desaparezca
     * de golpe; quitárselo a un rol es después una decisión, no un accidente.
     */
    private const PERMISO = 'alm.articulos.desactivar';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($q) => $q
                ->where('name', 'super-admin')
                ->orWhereHas('permissions', fn ($p) => $p->where('name', 'alm.articulos.editar')))
            ->get()
            ->each(fn (Role $rol) => $rol->givePermissionTo(self::PERMISO));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
