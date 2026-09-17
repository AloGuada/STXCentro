<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quién autoriza que se cancelen unidades de una orden ya emitida: el jefe de
 * compras. Solicitarla sigue siendo parte de `costos.ordenes-compra.cancelar`,
 * que compras ya tiene.
 *
 * Nace sólo en manos de super-admin. A diferencia de otros permisos nuevos, no
 * se reparte a quien ya puede cancelar órdenes: el punto de esta firma es que
 * la autorice alguien distinto de quien la pide, así que quién más lo tiene es
 * una decisión que se toma en la pantalla de roles, no aquí.
 */
return new class extends Migration
{
    private const PERMISO = 'costos.ordenes-compra.autorizar-cancelacion';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super-admin')
            ->get()
            ->each(fn (Role $rol) => $rol->givePermissionTo(self::PERMISO));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
