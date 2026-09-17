<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quién autoriza que se cancelen unidades de una orden ya emitida: el jefe de
 * compras. Solicitarla sigue siendo parte de `costos.ordenes-compra.cancelar`,
 * que compras ya tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::firstOrCreate(['name' => 'costos.ordenes-compra.autorizar-cancelacion', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::query()->where('name', 'costos.ordenes-compra.autorizar-cancelacion')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
