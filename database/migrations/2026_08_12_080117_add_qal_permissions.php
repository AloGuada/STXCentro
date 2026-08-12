<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Los permisos del módulo con el prefijo nuevo, `qal.`.
     *
     * Los `cal.*` se quedan donde están: los sigue usando la aplicación
     * anterior mientras viva, y se borran el día que se apague junto con sus
     * tablas. Aquí no se renombra nada, se crea el juego nuevo completo.
     *
     * Los roles no se tocan: `admin-cal` e `inspector-cal` nombran al área de
     * Calidad, no al prefijo de la base, así que conservan su nombre y sólo
     * ganan los permisos nuevos.
     *
     * No hay `eliminar` en ningún catálogo. Ahí nada se borra, se desactiva:
     * sacar un valor de los desplegables no debe tocar los registros que ya lo
     * mencionan.
     *
     * @var list<string>
     */
    private array $permisos = [
        'qal.obras.ver',
        'qal.obras.crear',
        'qal.obras.editar',
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
        'qal.reportes.ver',
        'qal.reportes.crear',
        'qal.reportes.editar',
        'qal.reportes.eliminar',
        'qal.flechas.ver',
        'qal.flechas.crear',
        'qal.flechas.editar',
        'qal.flechas.eliminar',
        'qal.usuarios.gestionar',
        'qal.soldadores.ver',
        'qal.soldadores.crear',
        'qal.soldadores.editar',
        'qal.laboratorios.ver',
        'qal.laboratorios.crear',
        'qal.laboratorios.editar',
        'qal.tipos-pieza.ver',
        'qal.tipos-pieza.crear',
        'qal.tipos-pieza.editar',
        'qal.equipos.ver',
        'qal.equipos.crear',
        'qal.equipos.editar',
        'qal.operadores.ver',
        'qal.operadores.crear',
        'qal.operadores.editar',
        'qal.responsables.ver',
        'qal.responsables.crear',
        'qal.responsables.editar',
        'qal.supervisores-pintura.ver',
        'qal.supervisores-pintura.crear',
        'qal.supervisores-pintura.editar',
        'qal.defectos-soldadura.ver',
        'qal.defectos-soldadura.crear',
        'qal.defectos-soldadura.editar',
        'qal.defectos-pintura.ver',
        'qal.defectos-pintura.crear',
        'qal.defectos-pintura.editar',
    ];

    /**
     * Lo que un inspector puede hacer: capturar su inspección y consultar
     * contra qué la captura. No administra catálogos.
     *
     * @var list<string>
     */
    private array $permisosInspector = [
        'qal.obras.ver',
        'qal.etapas.ver',
        'qal.piezas.ver',
        'qal.planos.ver',
        'qal.reportes.ver',
        'qal.reportes.crear',
        'qal.reportes.editar',
        'qal.flechas.ver',
        'qal.flechas.crear',
        'qal.flechas.editar',
        'qal.soldadores.ver',
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

        Role::where('name', 'inspector-cal')->where('guard_name', 'web')->first()
            ?->givePermissionTo($this->permisosInspector);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
