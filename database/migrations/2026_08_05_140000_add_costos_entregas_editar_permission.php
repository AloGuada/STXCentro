<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::firstOrCreate(['name' => 'costos.entregas.editar', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = app(PermissionRegistrar::class)->getPermissionClass();
        $permissions::query()->where('name', 'costos.entregas.editar')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
