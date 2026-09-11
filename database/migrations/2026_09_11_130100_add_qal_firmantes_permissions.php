<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El orden de los firmantes lo decide la jefatura de calidad; el inspector
     * sólo lo consulta para saber quién firma después de él.
     *
     * @var list<string>
     */
    private const NUEVOS = ['qal.firmantes.ver', 'qal.firmantes.editar'];

    public function up(): void
    {
        foreach (self::NUEVOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::NUEVOS);
        }

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()?->givePermissionTo('qal.firmantes.ver');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::NUEVOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
