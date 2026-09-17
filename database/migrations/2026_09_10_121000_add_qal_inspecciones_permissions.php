<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos del módulo de Calidad replanteado.
     *
     * - `inspecciones`: la captura de 1ª, 2ª y pintura, con sus juntas. Las
     *   juntas se capturan en el mismo formulario que la pieza, así que no
     *   llevan permiso aparte.
     * - `accesorios`: los lotes y sublotes, que se inspeccionan por muestreo.
     * - `modelos`: el IFC de la obra y sus cordones detectados.
     * - `registros`: la base en crudo; exportar va aparte porque saca la
     *   información del sistema.
     * - `programacion`: el plan semanal de producción contra lo inspeccionado.
     *
     * El inspector captura y consulta; borrar y exportar quedan en el
     * administrador del módulo.
     *
     * @var list<string>
     */
    private const PERMISOS = [
        'qal.inspecciones.ver',
        'qal.inspecciones.crear',
        'qal.inspecciones.editar',
        'qal.inspecciones.eliminar',
        'qal.accesorios.ver',
        'qal.accesorios.crear',
        'qal.accesorios.editar',
        'qal.accesorios.eliminar',
        'qal.modelos.ver',
        'qal.modelos.crear',
        'qal.modelos.eliminar',
        'qal.registros.ver',
        'qal.registros.exportar',
        'qal.programacion.ver',
        'qal.programacion.capturar',
    ];

    /**
     * @var list<string>
     */
    private const DEL_INSPECTOR = [
        'qal.inspecciones.ver',
        'qal.inspecciones.crear',
        'qal.inspecciones.editar',
        'qal.accesorios.ver',
        'qal.accesorios.crear',
        'qal.accesorios.editar',
        'qal.modelos.ver',
        'qal.registros.ver',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin-cal'] as $rol) {
            Role::where('name', $rol)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISOS);
        }

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()?->givePermissionTo(self::DEL_INSPECTOR);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
