<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Resetear cache de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos del módulo STI
        $stiPermissions = [
            'sti.tickets.ver',
            'sti.tickets.crear',
            'sti.tickets.editar',
            'sti.tickets.eliminar',
            'sti.equipos.ver',
            'sti.equipos.crear',
            'sti.equipos.editar',
            'sti.equipos.eliminar',
            'sti.tecnicos.ver',
            'sti.tecnicos.crear',
            'sti.tecnicos.editar',
            'sti.tecnicos.eliminar',
            'sti.mantenimientos.ver',
            'sti.mantenimientos.programar',
            'sti.mantenimientos.editar',
            'sti.mantenimientos.eliminar',
        ];

        // Crear permisos del módulo Intranet
        $intraPermissions = [
            'intra.areas.ver',
            'intra.areas.crear',
            'intra.areas.editar',
            'intra.areas.eliminar',
            'intra.areas.administrar',
            'intra.documentos.ver',
            'intra.documentos.subir',
            'intra.documentos.editar',
            'intra.documentos.eliminar',
            'intra.secciones.ver',
            'intra.secciones.crear',
            'intra.secciones.editar',
            'intra.secciones.eliminar',
        ];

        // Crear permisos del módulo Costos
        $costosPermissions = [
            'costos.proveedores.ver',
            'costos.proveedores.crear',
            'costos.proveedores.editar',
            'costos.proveedores.eliminar',
            'costos.tipo-rubros.ver',
            'costos.tipo-rubros.crear',
            'costos.tipo-rubros.editar',
            'costos.tipo-rubros.eliminar',
            'costos.rubros.ver',
            'costos.rubros.crear',
            'costos.rubros.editar',
            'costos.rubros.eliminar',
            'costos.tipo-solicitudes.ver',
            'costos.tipo-solicitudes.crear',
            'costos.tipo-solicitudes.editar',
            'costos.tipo-solicitudes.eliminar',
            'costos.obra-rubros.ver',
            'costos.obra-rubros.crear',
            'costos.obra-rubros.editar',
            'costos.obra-rubros.eliminar',
            'costos.aprobaciones.ver',
            'costos.aprobaciones.crear',
            'costos.aprobaciones.editar',
            'costos.aprobaciones.eliminar',
            'costos.solicitudes-pago.ver',
            'costos.solicitudes-pago.crear',
            'costos.solicitudes-pago.editar',
            'costos.solicitudes-pago.eliminar',
            'costos.afectaciones.ver',
            'costos.afectaciones.crear',
            'costos.afectaciones.editar',
            'costos.afectaciones.eliminar',
            'costos.pagos.ver',
            'costos.pagos.crear',
            'costos.pagos.editar',
            'costos.pagos.eliminar',
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.crear',
            'costos.ordenes-compra.editar',
            'costos.ordenes-compra.eliminar',
            'costos.ordenes-compra.aprobar',
            'costos.ordenes-compra.cancelar',
            'costos.facturas.ver',
            'costos.facturas.recibir',
            'costos.facturas.aprobar',
            'costos.entregas.crear',
            'costos.pagos.programar',
            'costos.facturas.aceptar-contabilidad',
            'costos.cuentas-internas.ver',
            'costos.cuentas-internas.editar',
        ];

        // Crear permisos del módulo Produccion
        $prodPermissions = [
            'prod.registros.ver',
            'prod.registros.crear',
            'prod.registros.eliminar',
            'prod.cortes.ver',
            'prod.cortes.crear',
            'prod.cortes.cerrar',
            'prod.grupos-trabajo.ver',
            'prod.grupos-trabajo.crear',
            'prod.grupos-trabajo.editar',
            'prod.grupos-trabajo.eliminar',
            'prod.grupo-precios.ver',
            'prod.grupo-precios.crear',
            'prod.grupo-precios.editar',
            'prod.grupo-precios.eliminar',
            'prod.tipos-pago-extra.ver',
            'prod.tipos-pago-extra.crear',
            'prod.tipos-pago-extra.editar',
            'prod.tipos-pago-extra.eliminar',
            'prod.conceptos.ver',
            'prod.conceptos.crear',
            'prod.conceptos.editar',
            'prod.conceptos.eliminar',
        ];

        // Crear permisos del módulo Infraestructura
        $infraPermissions = [
            'infra.recorridos.ver',
            'infra.recorridos.crear',
        ];

        // Crear permisos Core
        $corePermissions = [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'departamentos.ver',
            'departamentos.crear',
            'departamentos.editar',
            'departamentos.eliminar',
            'obras.ver',
            'obras.crear',
            'obras.editar',
            'obras.eliminar',
            'roles.ver',
            'roles.asignar',
        ];

        $allPermissions = array_merge($stiPermissions, $intraPermissions, $costosPermissions, $prodPermissions, $infraPermissions, $corePermissions);

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminSti = Role::firstOrCreate(['name' => 'admin-sti', 'guard_name' => 'web']);
        $tecnicoSti = Role::firstOrCreate(['name' => 'tecnico-sti', 'guard_name' => 'web']);
        $adminIntranet = Role::firstOrCreate(['name' => 'admin-intranet', 'guard_name' => 'web']);
        $adminCostos = Role::firstOrCreate(['name' => 'admin-costos', 'guard_name' => 'web']);
        $adminProduccion = Role::firstOrCreate(['name' => 'admin-produccion', 'guard_name' => 'web']);
        $adminInfra = Role::firstOrCreate(['name' => 'admin-infra', 'guard_name' => 'web']);
        $compras = Role::firstOrCreate(['name' => 'compras', 'guard_name' => 'web']);
        $almacen = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad', 'guard_name' => 'web']);
        $empleado = Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'web']);

        // Super admin tiene todos los permisos
        $superAdmin->givePermissionTo($allPermissions);

        // Admin STI tiene todos los permisos STI
        $adminSti->givePermissionTo($stiPermissions);

        // Técnico STI tiene permisos limitados de STI
        $tecnicoSti->givePermissionTo([
            'sti.tickets.ver',
            'sti.tickets.crear',
            'sti.tickets.editar',
            'sti.equipos.ver',
            'sti.mantenimientos.ver',
            'sti.mantenimientos.programar',
        ]);

        // Admin Intranet tiene todos los permisos de intranet
        $adminIntranet->givePermissionTo($intraPermissions);

        // Admin Costos tiene todos los permisos de costos
        $adminCostos->givePermissionTo($costosPermissions);

        // Admin Produccion tiene todos los permisos de produccion
        $adminProduccion->givePermissionTo($prodPermissions);

        // Admin Infra tiene todos los permisos de infraestructura
        $adminInfra->givePermissionTo($infraPermissions);

        // Compras tiene permisos de ordenes de compra y facturas
        $compras->givePermissionTo([
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.crear',
            'costos.ordenes-compra.editar',
            'costos.ordenes-compra.eliminar',
            'costos.ordenes-compra.aprobar',
            'costos.ordenes-compra.cancelar',
            'costos.facturas.ver',
            'costos.proveedores.ver',
            'costos.proveedores.editar',
        ]);

        // Almacen tiene permisos de recepcion
        $almacen->givePermissionTo([
            'costos.ordenes-compra.ver',
            'costos.facturas.ver',
            'costos.facturas.recibir',
            'costos.entregas.crear',
        ]);

        // Contabilidad acepta facturas, crea pagos y los programa
        $contabilidad->givePermissionTo([
            'costos.pagos.ver',
            'costos.pagos.programar',
            'costos.pagos.editar',
            'costos.facturas.ver',
            'costos.facturas.aceptar-contabilidad',
        ]);

        // Empleado tiene permisos básicos de lectura
        $empleado->givePermissionTo([
            'intra.areas.ver',
            'intra.documentos.ver',
            'intra.secciones.ver',
        ]);
    }
}
