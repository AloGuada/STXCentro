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

        $allPermissions = array_merge($stiPermissions, $intraPermissions, $corePermissions);

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminSti = Role::firstOrCreate(['name' => 'admin-sti', 'guard_name' => 'web']);
        $tecnicoSti = Role::firstOrCreate(['name' => 'tecnico-sti', 'guard_name' => 'web']);
        $adminIntranet = Role::firstOrCreate(['name' => 'admin-intranet', 'guard_name' => 'web']);
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

        // Empleado tiene permisos básicos de lectura
        $empleado->syncPermissions([
            'intra.areas.ver',
            'intra.documentos.ver',
            'intra.secciones.ver',
        ]);
    }
}
