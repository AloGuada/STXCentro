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

        $allPermissions = array_merge($stiPermissions, $intraPermissions, $costosPermissions, $corePermissions);

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminSti = Role::firstOrCreate(['name' => 'admin-sti', 'guard_name' => 'web']);
        $tecnicoSti = Role::firstOrCreate(['name' => 'tecnico-sti', 'guard_name' => 'web']);
        $adminIntranet = Role::firstOrCreate(['name' => 'admin-intranet', 'guard_name' => 'web']);
        $adminCostos = Role::firstOrCreate(['name' => 'admin-costos', 'guard_name' => 'web']);
        $empleado = Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'web']);

        // Super admin tiene todos los permisos
        $superAdmin->syncPermissions($allPermissions);

        // Admin STI tiene todos los permisos STI
        $adminSti->syncPermissions($stiPermissions);

        // Técnico STI tiene permisos limitados de STI
        $tecnicoSti->syncPermissions([
            'sti.tickets.ver',
            'sti.tickets.crear',
            'sti.tickets.editar',
            'sti.equipos.ver',
            'sti.mantenimientos.ver',
            'sti.mantenimientos.programar',
        ]);

        // Admin Intranet tiene todos los permisos de intranet
        $adminIntranet->syncPermissions($intraPermissions);

        // Admin Costos tiene todos los permisos de costos
        $adminCostos->syncPermissions($costosPermissions);

        // Empleado tiene permisos básicos de lectura
        $empleado->syncPermissions([
            'intra.areas.ver',
            'intra.documentos.ver',
            'intra.secciones.ver',
        ]);
    }
}
