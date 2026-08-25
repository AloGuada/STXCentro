<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Visibilidad acotada del Drive: quien tiene `drive.propias` solo ve las carpetas
 * que creó y las que le compartieron. No se asigna a ningún rol: es un permiso
 * que se otorga usuario por usuario, y `drive.gestionar` sigue viendo todo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();

        $permissions::firstOrCreate(['name' => 'drive.propias', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::query()->where('name', 'drive.propias')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
