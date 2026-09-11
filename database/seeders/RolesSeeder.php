<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Crea los roles del sistema y (re)sincroniza sus permisos a los valores por
     * defecto. OJO: re-correrlo revierte cualquier ajuste manual de permisos por
     * rol. Para dar de alta solo permisos nuevos sin tocar roles, usa
     * RolesAndPermissionsSeeder.
     */
    public function run(): void
    {
        // Asegura que los permisos existan antes de asignarlos.
        $this->call(RolesAndPermissionsSeeder::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = RolesAndPermissionsSeeder::groupedPermissions();
        $todos = array_merge(...array_values($permisos));

        // Consolidar a los 4 roles operativos de costos: compras, costos, almacen,
        // contabilidad. Sustituyen a costos-compras, costos-almacen y admin-costos
        // conservando las asignaciones (se renombra el registro). Idempotente.
        foreach ([
            'costos-compras' => 'compras',
            'costos-almacen' => 'almacen',
            'admin-costos' => 'costos',
        ] as $viejo => $nuevo) {
            if (($role = Role::where('name', $viejo)->first()) && ! Role::where('name', $nuevo)->exists()) {
                $role->update(['name' => $nuevo]);
            }
        }

        // Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminSti = Role::firstOrCreate(['name' => 'admin-sti', 'guard_name' => 'web']);
        $tecnicoSti = Role::firstOrCreate(['name' => 'tecnico-sti', 'guard_name' => 'web']);
        $adminIntranet = Role::firstOrCreate(['name' => 'admin-intranet', 'guard_name' => 'web']);
        $costos = Role::firstOrCreate(['name' => 'costos', 'guard_name' => 'web']);
        $adminProduccion = Role::firstOrCreate(['name' => 'admin-produccion', 'guard_name' => 'web']);
        $adminInfra = Role::firstOrCreate(['name' => 'admin-infra', 'guard_name' => 'web']);
        $compras = Role::firstOrCreate(['name' => 'compras', 'guard_name' => 'web']);
        $almacen = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad', 'guard_name' => 'web']);
        $adminCobranza = Role::firstOrCreate(['name' => 'admin-cobranza', 'guard_name' => 'web']);
        $adminRh = Role::firstOrCreate(['name' => 'admin-rh', 'guard_name' => 'web']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $adminCal = Role::firstOrCreate(['name' => 'admin-cal', 'guard_name' => 'web']);
        $inspectorCal = Role::firstOrCreate(['name' => 'inspector-cal', 'guard_name' => 'web']);
        $empleado = Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'web']);
        $directorGeneral = Role::firstOrCreate(['name' => 'director-general', 'guard_name' => 'web']);
        $adminCotiz = Role::firstOrCreate(['name' => 'admin-cotiz', 'guard_name' => 'web']);
        $usuarioCotiz = Role::firstOrCreate(['name' => 'usuario-cotiz', 'guard_name' => 'web']);

        // El rol `gerente` fue reemplazado por la ACL por carpeta (dg_carpeta_usuario.puede_escribir).
        Role::where('name', 'gerente')->delete();
        Permission::where('name', 'dg.reportes.subir')->delete();

        // Super admin tiene todos los permisos
        $superAdmin->givePermissionTo($todos);

        // Admin STI tiene todos los permisos STI
        $adminSti->givePermissionTo($permisos['sti']);

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
        $adminIntranet->givePermissionTo($permisos['intra']);

        // El rol `costos` es el operativo de costos con acceso completo al módulo
        // (catálogos, presupuesto, afectaciones, configuración de aprobaciones,
        // facturas y notas de crédito). Sustituye a admin-costos.
        $costos->givePermissionTo($permisos['costos']);

        // Admin Produccion tiene todos los permisos de produccion
        $adminProduccion->givePermissionTo($permisos['prod']);
        // y teclea la programación semanal que Calidad cruza contra lo inspeccionado.
        $adminProduccion->givePermissionTo(['qal.programacion.ver', 'qal.programacion.capturar']);

        // Admin Infra tiene todos los permisos de infraestructura
        $adminInfra->givePermissionTo($permisos['infra']);

        // compras gestiona OC, proveedores y devoluciones; ve facturas en lectura.
        // La creación/cancelación de factura es responsabilidad de costos.
        // Como operativo del módulo, ve todas las requisiciones, OC y solicitudes.
        $compras->syncPermissions([
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.ver-todas',
            'costos.ordenes-compra.crear',
            'costos.ordenes-compra.editar',
            'costos.ordenes-compra.eliminar',
            'costos.ordenes-compra.aprobar',
            'costos.ordenes-compra.cancelar',
            'costos.facturas.ver',
            'costos.proveedores.ver',
            'costos.proveedores.crear',
            'costos.proveedores.editar',
            'costos.bancos.ver',
            'costos.requisiciones.ver',
            'costos.requisiciones.ver-todas',
            'costos.requisiciones.cotizar',
            'costos.requisiciones.liberar',
            'costos.requisiciones.cancelar',
            'costos.devoluciones.ver',
            'costos.devoluciones.crear',
            'costos.devoluciones.cancelar',
            'costos.productos.ver',
            'costos.productos.crear',
            'costos.productos.editar',
            'costos.productos.eliminar',
            'costos.solicitudes-pago.ver',
            'costos.solicitudes-pago.ver-todas',
        ]);

        // supervisor es a cuyo nombre queda un pedido de almacén. No opera el
        // almacén: sólo pide, y su nombre firma lo que se surte contra el pedido.
        $supervisor->givePermissionTo('alm.pedidos.supervisar');

        // almacen registra recepciones contra OC y gestiona devoluciones.
        // Operativo del módulo: ve todas las requisiciones, OC y solicitudes.
        $almacen->syncPermissions([
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.ver-todas',
            'costos.facturas.ver',
            // La captura de la recepción vive en Almacén; el permiso de Costos
            // se queda para editar/cancelar desde el listado de recepciones.
            'alm.entradas.crear',
            'costos.entregas.crear',
            'costos.devoluciones.ver',
            'costos.devoluciones.crear',
            'costos.devoluciones.cancelar',
            'costos.requisiciones.ver',
            'costos.requisiciones.ver-todas',
            'costos.productos.ver',
            'costos.productos.crear',
            'costos.productos.editar',
            'costos.productos.eliminar',
            'costos.solicitudes-pago.ver',
            'costos.solicitudes-pago.ver-todas',
        ]);

        // contabilidad valida costos, crea/aprueba solicitudes de pago, programa
        // pagos, gestiona anticipos, notas de crédito y complementos de pago.
        // Operativo del módulo: ve todas las requisiciones, OC y solicitudes.
        $contabilidad->syncPermissions([
            'costos.pagos.ver',
            'costos.pagos.programar',
            'costos.pagos.editar',
            'costos.pagos.cancelar',
            'costos.facturas.ver',
            'costos.facturas.aceptar-contabilidad',
            'costos.solicitudes-pago.ver',
            'costos.solicitudes-pago.ver-todas',
            'costos.solicitudes-pago.crear',
            'costos.solicitudes-pago.editar',
            'costos.solicitudes-pago.aprobar',
            'costos.solicitudes.confirmar-costos',
            'costos.anticipos.ver',
            'costos.anticipos.crear',
            'costos.anticipos.aplicar',
            'costos.anticipos.cancelar',
            'costos.notas-credito.ver',
            'costos.notas-credito.crear',
            'costos.notas-credito.cancelar',
            'costos.complementos.ver',
            'costos.complementos.desbloquear',
            'costos.requisiciones.ver',
            'costos.requisiciones.ver-todas',
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.ver-todas',
        ]);

        // Admin Cobranza tiene todos los permisos de cobranza
        $adminCobranza->givePermissionTo($permisos['cob']);

        // Admin RH tiene todos los permisos de recursos humanos
        $adminRh->givePermissionTo($permisos['rh']);

        // Admin Calidad tiene todos los permisos de calidad, en los dos
        // prefijos: qal.* es el modulo nuevo y cal.* lo que aun sirve la API
        // de la aplicacion anterior. Los cal.* se quitan cuando esa se apague.
        $adminCal->givePermissionTo($permisos['cal']);
        $adminCal->givePermissionTo($permisos['qal']);

        // Inspector Calidad tiene permisos de ver/crear/editar reportes y flechas
        $inspectorCal->givePermissionTo([
            'cal.obras.ver',
            'cal.etapas.ver',
            'cal.piezas.ver',
            'cal.planos.ver',
            'cal.reportes.ver',
            'cal.reportes.crear',
            'cal.reportes.editar',
            'cal.flechas.ver',
            'cal.flechas.crear',
            'cal.flechas.editar',
            'cal.soldadores.ver',
            'qal.obras.ver',
            'qal.programacion.ver',
            'qal.soldadores.ver',
            'qal.inspecciones.ver',
            'qal.inspecciones.crear',
            'qal.inspecciones.editar',
            'qal.accesorios.ver',
            'qal.accesorios.crear',
            'qal.accesorios.editar',
            'qal.modelos.ver',
            'qal.registros.ver',
            'qal.firmantes.ver',
            'qal.reportes.ver',
        ]);

        // Empleado tiene permisos básicos de lectura
        $empleado->givePermissionTo([
            'intra.areas.ver',
            'intra.documentos.ver',
            'intra.secciones.ver',
            // Puede crear solicitudes de pago y cancelar las suyas.
            'costos.solicitudes-pago.ver',
            'costos.solicitudes-pago.crear',
            'costos.solicitudes-pago.cancelar-propia',
            // Levanta sus propias requisiciones y da seguimiento (solo lectura)
            // a ellas y a las OC / solicitudes de pago que derivan de ellas.
            'costos.requisiciones.ver',
            'costos.requisiciones.crear',
        ]);

        // Director General: administra carpetas + ve todo + toma notas
        $directorGeneral->syncPermissions([
            'dg.reportes.ver',
            'dg.reportes.administrar',
            'dg.reportes.notas',
        ]);

        // Admin Cotización gestiona los catálogos globales y todo el trabajo por obra.
        $adminCotiz->givePermissionTo(array_merge($permisos['cotiz'], $permisos['cotizTrabajo']));

        // Usuario Cotización: consume los catálogos (solo lectura) y opera el trabajo por
        // obra (obras/generadoras). Corte fino permiso-por-permiso en Fase 6.
        $usuarioCotiz->syncPermissions(array_merge(
            array_values(array_filter($permisos['cotiz'], fn (string $p) => str_ends_with($p, '.ver'))),
            $permisos['cotizTrabajo'],
        ));
    }
}
