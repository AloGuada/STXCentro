<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Un solo catálogo de defectos, un solo juego de permisos.
     *
     * Quien tenía un permiso de defectos de soldadura o de pintura —por rol o
     * directo— recibe el mismo sobre el catálogo unificado, así que nadie pierde
     * acceso; después se borran los viejos.
     *
     * @var array<string, list<string>>
     */
    private const EQUIVALENCIAS = [
        'qal.defectos.ver' => ['qal.defectos-soldadura.ver', 'qal.defectos-pintura.ver'],
        'qal.defectos.crear' => ['qal.defectos-soldadura.crear', 'qal.defectos-pintura.crear'],
        'qal.defectos.editar' => ['qal.defectos-soldadura.editar', 'qal.defectos-pintura.editar'],
    ];

    public function up(): void
    {
        foreach (self::EQUIVALENCIAS as $nuevo => $viejos) {
            $permiso = Permission::firstOrCreate(['name' => $nuevo, 'guard_name' => 'web']);

            foreach (Permission::whereIn('name', $viejos)->where('guard_name', 'web')->get() as $viejo) {
                $this->heredar($viejo->id, $permiso->id);
            }
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()
                ?->givePermissionTo(array_keys(self::EQUIVALENCIAS));
        }

        Permission::whereIn('name', array_merge(...array_values(self::EQUIVALENCIAS)))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::EQUIVALENCIAS as $nuevo => $viejos) {
            $permiso = Permission::where('name', $nuevo)->where('guard_name', 'web')->first();

            foreach ($viejos as $nombre) {
                $viejo = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);

                if ($permiso) {
                    $this->heredar($permiso->id, $viejo->id);
                }
            }

            $permiso?->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Da el permiso destino a todos los roles y usuarios que tienen el origen.
     */
    private function heredar(int $origen, int $destino): void
    {
        $tablas = config('permission.table_names');

        foreach ([$tablas['role_has_permissions'], $tablas['model_has_permissions']] as $tabla) {
            DB::table($tabla)->where('permission_id', $origen)->get()->each(function (object $fila) use ($tabla, $destino): void {
                DB::table($tabla)->insertOrIgnore(['permission_id' => $destino] + (array) $fila);
            });
        }
    }
};
