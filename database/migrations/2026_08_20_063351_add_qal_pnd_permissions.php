<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos de la pantalla de pruebas no destructivas.
     *
     * PND es un recurso aparte de `reportes` y no una acción suya: son
     * universos distintos —`reportes` es inspección visual de piezas, PND son
     * juntas soldadas evaluadas por un laboratorio externo— y los captura otra
     * persona. El inspector de piso no entra aquí, así que `inspector-cal` no
     * los recibe.
     *
     * @var list<string>
     */
    private array $permisos = [
        'qal.pnd.ver',
        'qal.pnd.crear',
        'qal.pnd.editar',
        'qal.pnd.eliminar',
    ];

    /**
     * Se asignan en la misma migración: el menú se pinta con los permisos del
     * usuario, así que un permiso nuevo sin asignar deja la pantalla invisible
     * para todos.
     */
    public function up(): void
    {
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::where('name', 'super-admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo($this->permisos);

        Role::where('name', 'admin-cal')->where('guard_name', 'web')->first()
            ?->givePermissionTo($this->permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
