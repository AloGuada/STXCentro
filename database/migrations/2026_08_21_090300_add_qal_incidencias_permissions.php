<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de incidencias en obra.
     *
     * Van aparte de los del taller porque el circuito es otro: esto lo captura
     * quien está en obra durante el montaje, no el inspector de planta, y quien
     * consulta el porcentaje —dirección, el residente— no tiene por qué poder
     * borrar el historial de una obra.
     *
     * `capturar` cubre el alta de incidencias, el avance de montaje y el cierre
     * o reapertura: son el mismo trabajo semanal. `eliminar` se separa porque
     * borrar el avance de una semana deja a sus incidencias sin denominador.
     *
     * @var list<string>
     */
    private const PERMISOS = [
        'qal.incidencias.ver',
        'qal.incidencias.capturar',
        'qal.incidencias.eliminar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()
                ?->givePermissionTo(self::PERMISOS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
