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
            'costos.regimenes-fiscales.ver',
            'costos.regimenes-fiscales.crear',
            'costos.regimenes-fiscales.editar',
            'costos.regimenes-fiscales.eliminar',
            'costos.tipo-rubros.ver',
            'costos.tipo-rubros.crear',
            'costos.tipo-rubros.editar',
            'costos.tipo-rubros.eliminar',
            'costos.usos-cfdi.ver',
            'costos.usos-cfdi.crear',
            'costos.usos-cfdi.editar',
            'costos.usos-cfdi.eliminar',
            'costos.rubros.ver',
            'costos.rubros.crear',
            'costos.rubros.editar',
            'costos.rubros.eliminar',
            'costos.productos.ver',
            'costos.productos.crear',
            'costos.productos.editar',
            'costos.productos.eliminar',
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
            'costos.facturas.cancelar',
            'costos.facturas.crear',
            'costos.entregas.crear',
            'costos.pagos.programar',
            'costos.pagos.cancelar',
            'costos.facturas.aceptar-contabilidad',
            'costos.complementos.ver',
            'costos.complementos.desbloquear',
            'costos.cuentas-internas.ver',
            'costos.cuentas-internas.editar',
            'costos.solicitudes.confirmar-costos',
            'costos.solicitudes-pago.aprobar',
            // Requisiciones (Fase 10.2)
            'costos.requisiciones.ver',
            'costos.requisiciones.crear',
            'costos.requisiciones.cotizar',
            'costos.requisiciones.aprobar',
            'costos.requisiciones.liberar',
            'costos.requisiciones.cancelar',
            // Anticipos a proveedor (Fase 11)
            'costos.anticipos.ver',
            'costos.anticipos.crear',
            'costos.anticipos.aplicar',
            'costos.anticipos.cancelar',
            // Notas de crédito (Fase 12)
            'costos.notas-credito.ver',
            'costos.notas-credito.crear',
            'costos.notas-credito.cancelar',
            // Devoluciones a proveedor (Fase 13)
            'costos.devoluciones.ver',
            'costos.devoluciones.crear',
            'costos.devoluciones.cancelar',
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

        // Crear permisos del módulo Cobranza
        $cobPermissions = [
            'cob.dashboard.ver',
            'cob.clientes.ver',
            'cob.clientes.crear',
            'cob.clientes.editar',
            'cob.clientes.eliminar',
            'cob.obras.ver',
            'cob.obras.editar',
            'cob.obras.cerrar',
            'cob.partidas.ver',
            'cob.partidas.crear',
            'cob.partidas.editar',
            'cob.partidas.eliminar',
            'cob.estimaciones.ver',
            'cob.estimaciones.crear',
            'cob.estimaciones.editar',
            'cob.estimaciones.eliminar',
            'cob.estimaciones.cambiar-estado',
            'cob.pagos.ver',
            'cob.pagos.crear',
            'cob.anticipos.ver',
            'cob.anticipos.crear',
            'cob.anticipos.editar',
            'cob.anticipos.eliminar',
            'cob.adendas.ver',
            'cob.adendas.crear',
            'cob.adendas.editar',
            'cob.adendas.eliminar',
            'cob.comparativos.ver',
            'cob.comparativos.crear',
            'cob.comparativos.editar',
            'cob.comparativos.eliminar',
            'cob.deducciones.ver',
            'cob.deducciones.crear',
            'cob.deducciones.editar',
            'cob.deducciones.eliminar',
            'cob.eventos.ver',
            'cob.eventos.crear',
            'cob.eventos.editar',
            'cob.eventos.eliminar',
            'cob.disputas.ver',
            'cob.disputas.crear',
            'cob.disputas.editar',
            'cob.disputas.eliminar',
            'cob.penalizaciones.ver',
            'cob.penalizaciones.crear',
            'cob.penalizaciones.editar',
            'cob.penalizaciones.eliminar',
            'cob.tipos-retenciones.ver',
            'cob.tipos-retenciones.crear',
            'cob.tipos-retenciones.editar',
            'cob.tipos-retenciones.eliminar',
            'cob.configuracion-documentos.ver',
            'cob.configuracion-documentos.crear',
            'cob.configuracion-documentos.editar',
            'cob.configuracion-documentos.eliminar',
            'cob.documentos.ver',
            'cob.documentos.gestionar',
            'cob.reportes.ver',
            'cob.reportes.gestionar',
        ];

        // Crear permisos del módulo RH
        $rhPermissions = [
            'rh.puestos.ver',
            'rh.puestos.crear',
            'rh.puestos.editar',
            'rh.puestos.eliminar',
            'rh.skills.ver',
            'rh.skills.crear',
            'rh.skills.editar',
            'rh.skills.eliminar',
            'rh.requerimientos.ver',
            'rh.requerimientos.crear',
            'rh.requerimientos.editar',
            'rh.requerimientos.eliminar',
            'rh.personas.ver',
            'rh.personas.crear',
            'rh.personas.editar',
            'rh.personas.eliminar',
            'rh.periodos-laborales.ver',
            'rh.periodos-laborales.crear',
            'rh.periodos-laborales.editar',
            'rh.periodos-laborales.eliminar',
            'rh.requisiciones.ver',
            'rh.requisiciones.crear',
            'rh.requisiciones.editar',
            'rh.requisiciones.eliminar',
            'rh.candidaturas.ver',
            'rh.candidaturas.crear',
            'rh.onboarding.ver',
            'rh.onboarding.crear',
            'rh.onboarding.editar',
            'rh.permisos-ausencia.ver',
            'rh.permisos-ausencia.crear',
            'rh.permisos-ausencia.editar',
            'rh.permisos-ausencia.eliminar',
        ];

        // Crear permisos del módulo Drive
        $drivePermissions = [
            'drive.gestionar',
        ];

        // Crear permisos del módulo DG Reportes
        // El acceso a subir reportes se controla por la tabla pivote dg_carpeta_usuario (puede_escribir), no por permiso.
        $dgPermissions = [
            'dg.reportes.ver',
            'dg.reportes.administrar',
            'dg.reportes.notas',
        ];

        // Crear permisos del módulo Calidad
        $calPermissions = [
            'cal.obras.ver',
            'cal.etapas.ver',
            'cal.etapas.crear',
            'cal.etapas.editar',
            'cal.etapas.eliminar',
            'cal.piezas.ver',
            'cal.piezas.crear',
            'cal.piezas.editar',
            'cal.piezas.eliminar',
            'cal.planos.ver',
            'cal.planos.crear',
            'cal.planos.editar',
            'cal.planos.eliminar',
            'cal.reportes.ver',
            'cal.reportes.crear',
            'cal.reportes.editar',
            'cal.reportes.eliminar',
            'cal.flechas.ver',
            'cal.flechas.crear',
            'cal.flechas.editar',
            'cal.flechas.eliminar',
            'cal.soldadores.ver',
            'cal.soldadores.crear',
            'cal.soldadores.editar',
            'cal.soldadores.eliminar',
            'cal.usuarios.gestionar',
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
            'badge-configs.ver',
            'badge-configs.crear',
            'badge-configs.editar',
            'badge-configs.eliminar',
        ];

        // Crear permisos del módulo Cotización (catálogos globales — Fase 0).
        // El trabajo por obra (obras, generadoras, tarjetas, resumen) agrega sus permisos en fases posteriores.
        $cotizCatalogos = [
            'insumos',
            'mermas',
            'factores',
            'centros-costo',
            'categorias-tarjeta',
            'pintura-formulas',
            'kilos-reales-categorias',
            'cuadrillas',
            'personal',
            'fases-montaje',
            'fletes-viaticos',
            'resumen-filas',
        ];
        $cotizPermissions = [];
        foreach ($cotizCatalogos as $recurso) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                $cotizPermissions[] = "cotiz.{$recurso}.{$accion}";
            }
        }

        // Trabajo por obra (Fase 1+): lo opera el usuario-cotiz, no solo el admin.
        $cotizTrabajoPermissions = [];
        foreach (['obras', 'generadoras', 'tarjetas', 'analisis-mo', 'resumen', 'versiones'] as $recurso) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                $cotizTrabajoPermissions[] = "cotiz.{$recurso}.{$accion}";
            }
        }

        $allPermissions = array_merge($stiPermissions, $intraPermissions, $costosPermissions, $prodPermissions, $infraPermissions, $cobPermissions, $rhPermissions, $drivePermissions, $dgPermissions, $calPermissions, $corePermissions, $cotizPermissions, $cotizTrabajoPermissions);

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Renombrar roles de costos al namespace costos-* (idempotente)
        foreach (['compras' => 'costos-compras', 'almacen' => 'costos-almacen'] as $viejo => $nuevo) {
            if (($role = Role::where('name', $viejo)->first()) && ! Role::where('name', $nuevo)->exists()) {
                $role->update(['name' => $nuevo]);
            }
        }

        // Crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminSti = Role::firstOrCreate(['name' => 'admin-sti', 'guard_name' => 'web']);
        $tecnicoSti = Role::firstOrCreate(['name' => 'tecnico-sti', 'guard_name' => 'web']);
        $adminIntranet = Role::firstOrCreate(['name' => 'admin-intranet', 'guard_name' => 'web']);
        $adminCostos = Role::firstOrCreate(['name' => 'admin-costos', 'guard_name' => 'web']);
        $adminProduccion = Role::firstOrCreate(['name' => 'admin-produccion', 'guard_name' => 'web']);
        $adminInfra = Role::firstOrCreate(['name' => 'admin-infra', 'guard_name' => 'web']);
        $compras = Role::firstOrCreate(['name' => 'costos-compras', 'guard_name' => 'web']);
        $almacen = Role::firstOrCreate(['name' => 'costos-almacen', 'guard_name' => 'web']);
        $contabilidad = Role::firstOrCreate(['name' => 'contabilidad', 'guard_name' => 'web']);
        $adminCobranza = Role::firstOrCreate(['name' => 'admin-cobranza', 'guard_name' => 'web']);
        $adminRh = Role::firstOrCreate(['name' => 'admin-rh', 'guard_name' => 'web']);
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

        // costos-compras gestiona OC y proveedores; ve facturas solo en lectura.
        // La creación/cancelación de factura es responsabilidad de admin-costos.
        $compras->syncPermissions([
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.crear',
            'costos.ordenes-compra.editar',
            'costos.ordenes-compra.eliminar',
            'costos.ordenes-compra.aprobar',
            'costos.ordenes-compra.cancelar',
            'costos.facturas.ver',
            'costos.proveedores.ver',
            'costos.proveedores.crear',
            'costos.proveedores.editar',
            'costos.requisiciones.ver',
            'costos.requisiciones.cotizar',
            'costos.requisiciones.liberar',
            'costos.requisiciones.cancelar',
            'costos.productos.ver',
            'costos.productos.crear',
            'costos.productos.editar',
            'costos.productos.eliminar',
        ]);

        // costos-almacen registra recepciones contra OC y ve facturas/OC relacionadas.
        $almacen->syncPermissions([
            'costos.ordenes-compra.ver',
            'costos.facturas.ver',
            'costos.entregas.crear',
            'costos.devoluciones.ver',
            'costos.devoluciones.crear',
            'costos.devoluciones.cancelar',
            'costos.productos.ver',
            'costos.productos.crear',
            'costos.productos.editar',
            'costos.productos.eliminar',
        ]);

        // Contabilidad acepta facturas, crea pagos y los programa
        $contabilidad->givePermissionTo([
            'costos.pagos.ver',
            'costos.pagos.programar',
            'costos.pagos.editar',
            'costos.facturas.ver',
            'costos.facturas.aceptar-contabilidad',
            'costos.anticipos.ver',
            'costos.anticipos.crear',
            'costos.anticipos.aplicar',
            'costos.anticipos.cancelar',
            'costos.notas-credito.ver',
            'costos.notas-credito.crear',
            'costos.notas-credito.cancelar',
        ]);

        // Admin Cobranza tiene todos los permisos de cobranza
        $adminCobranza->givePermissionTo($cobPermissions);

        // Admin RH tiene todos los permisos de recursos humanos
        $adminRh->givePermissionTo($rhPermissions);

        // Admin Calidad tiene todos los permisos de calidad
        $adminCal->givePermissionTo($calPermissions);

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
        ]);

        // Empleado tiene permisos básicos de lectura
        $empleado->givePermissionTo([
            'intra.areas.ver',
            'intra.documentos.ver',
            'intra.secciones.ver',
        ]);

        // Director General: administra carpetas + ve todo + toma notas
        $directorGeneral->syncPermissions([
            'dg.reportes.ver',
            'dg.reportes.administrar',
            'dg.reportes.notas',
        ]);

        // Admin Cotización gestiona los catálogos globales y todo el trabajo por obra.
        $adminCotiz->givePermissionTo(array_merge($cotizPermissions, $cotizTrabajoPermissions));

        // Usuario Cotización: consume los catálogos (solo lectura) y opera el trabajo por
        // obra (obras/generadoras). Corte fino permiso-por-permiso en Fase 6.
        $usuarioCotiz->syncPermissions(array_merge(
            array_values(array_filter($cotizPermissions, fn (string $p) => str_ends_with($p, '.ver'))),
            $cotizTrabajoPermissions,
        ));
    }
}
