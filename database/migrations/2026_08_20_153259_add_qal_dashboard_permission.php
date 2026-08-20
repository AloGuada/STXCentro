<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El permiso del tablero de Calidad.
     *
     * Es uno solo y de lectura: el tablero no escribe nada, resume lo que otros
     * capturaron. Y va aparte de `qal.reportes.ver` porque su lector es otro
     * —dirección y el administrador de calidad, no el inspector de piso—, y
     * porque el tablero enseña la obra entera mientras que un reporte enseña
     * una pieza.
     *
     * `inspector-cal` no lo recibe por lo mismo que no recibe PND: captura lo
     * suyo, no consulta el desempeño del taller.
     */
    private const PERMISO = 'qal.dashboard.ver';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()
                ?->givePermissionTo(self::PERMISO);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
