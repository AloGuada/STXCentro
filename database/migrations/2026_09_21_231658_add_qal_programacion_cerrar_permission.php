<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cerrar el plan de la semana es comprometerlo: va aparte de capturarlo
     * para que quien lo arma y quien lo firma puedan ser personas distintas.
     */
    private const PERMISO = 'qal.programacion.cerrar';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        foreach (['super-admin', 'admin-cal', 'admin-produccion'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
