<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los dos permisos de la asignación de material por obra.
     *
     * No hay `alm.asignaciones.ver`: la partición no tiene pantalla propia, se
     * lee dentro de Existencias y del Kardex, que ya traen el suyo. Un permiso
     * para ver algo que no se puede abrir sería decoración.
     *
     * `alm.salidas.tomar-asignado` es la excepción, no la operación: la salida
     * consume lo de su obra y luego lo libre sin pedir nada. Sólo llevarse
     * material comprometido con **otra** obra lo exige — es lo que convierte la
     * asignación en una garantía y no en una sugerencia.
     *
     * @var list<string>
     */
    private array $permisos = [
        'alm.asignaciones.reasignar',
        'alm.salidas.tomar-asignado',
    ];

    /**
     * Se dan a super-admin aquí mismo: un permiso nuevo sin asignar deja la
     * acción invisible para todos, y el menú se pinta con los permisos del
     * usuario.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();

        $superAdmin?->givePermissionTo($this->permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
