<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Permisos del módulo Almacén, uno por recurso y acción.
     *
     * Hasta ahora las 17 pantallas reciclaban `alm.almacenes.ver`, que era el
     * único permiso que existía: quien podía ver el catálogo podía capturar
     * cualquier movimiento. Con el kardex de por medio eso deja de ser
     * aceptable, porque cada documento afecta existencias de forma irreversible.
     *
     * `alm.almacenes.ver-todos` es aparte: no abre pantallas, levanta el filtro
     * de visibilidad por almacén. Sin él, el usuario sólo ve los almacenes a los
     * que está asignado.
     *
     * @var list<string>
     */
    private array $permisos = [
        'alm.almacenes.ver-todos',
        'alm.existencias.ver',
        'alm.kardex.ver',
        'alm.articulos.ver',
        'alm.articulos.crear',
        'alm.articulos.editar',
        'alm.pedidos.ver',
        'alm.pedidos.crear',
        'alm.pedidos.aprobar',
        'alm.pedidos.cancelar',
        'alm.entradas.ver',
        'alm.entradas.crear',
        'alm.entradas.cancelar',
        'alm.salidas.ver',
        'alm.salidas.crear',
        'alm.transferencias.ver',
        'alm.transferencias.enviar',
        'alm.transferencias.recibir',
        'alm.devoluciones.ver',
        'alm.devoluciones.crear',
        'alm.ajustes.ver',
        'alm.ajustes.crear',
        'alm.prestamos.ver',
        'alm.prestamos.crear',
        'alm.prestamos.devolver',
        'alm.activos.ver',
        'alm.activos.crear',
        'alm.activos.editar',
        'alm.aprobaciones.ver',
        'alm.aprobaciones.configurar',
    ];

    /**
     * Se dan a super-admin en la misma migración: el menú se pinta con los
     * permisos del usuario, así que un permiso nuevo sin asignar deja la
     * pantalla invisible para todos.
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
