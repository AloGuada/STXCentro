<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de las pantallas del mapeo 2D, que ya no existen: etapas,
     * piezas y planos propios, reportes sobre el plano y sus flechas.
     *
     * `qal.reportes.ver` se queda: todavía abre Avance de producción, hasta que
     * esa pantalla tenga su permiso propio. `qal.obras.*` también: PND lo usa
     * para el plan de la obra.
     *
     * @var list<string>
     */
    private const RETIRADOS = [
        'qal.etapas.ver',
        'qal.etapas.crear',
        'qal.etapas.editar',
        'qal.etapas.eliminar',
        'qal.piezas.ver',
        'qal.piezas.crear',
        'qal.piezas.editar',
        'qal.piezas.eliminar',
        'qal.planos.ver',
        'qal.planos.crear',
        'qal.planos.editar',
        'qal.planos.eliminar',
        'qal.reportes.crear',
        'qal.reportes.editar',
        'qal.reportes.eliminar',
        'qal.flechas.ver',
        'qal.flechas.crear',
        'qal.flechas.editar',
        'qal.flechas.eliminar',
    ];

    /**
     * Los que tenía el inspector, para devolvérselos si se revierte.
     *
     * @var list<string>
     */
    private const DEL_INSPECTOR = [
        'qal.etapas.ver',
        'qal.piezas.ver',
        'qal.planos.ver',
        'qal.reportes.crear',
        'qal.reportes.editar',
        'qal.flechas.ver',
        'qal.flechas.crear',
        'qal.flechas.editar',
    ];

    public function up(): void
    {
        Permission::whereIn('name', self::RETIRADOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::RETIRADOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::RETIRADOS);
        }

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()?->givePermissionTo(self::DEL_INSPECTOR);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
