<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Avance de producción deja de abrirse con `qal.reportes.ver` —el último
     * permiso que quedaba de la aplicación de mapeo 2D— y tiene los suyos: ver
     * el cruce y capturar el plan de la semana.
     *
     * El plan lo teclea Producción, así que `admin-produccion` también los
     * recibe; el inspector sólo lo ve.
     *
     * @var list<string>
     */
    private const NUEVOS = ['qal.programacion.ver', 'qal.programacion.capturar'];

    public function up(): void
    {
        foreach (self::NUEVOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal', 'admin-produccion'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::NUEVOS);
        }

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()?->givePermissionTo('qal.programacion.ver');

        Permission::where('name', 'qal.reportes.ver')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $viejo = Permission::firstOrCreate(['name' => 'qal.reportes.ver', 'guard_name' => 'web']);

        foreach (['super-admin', 'admin-cal', 'inspector-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo($viejo);
        }

        Permission::whereIn('name', self::NUEVOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
