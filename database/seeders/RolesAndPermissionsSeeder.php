<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Crea (o confirma) el catálogo de permisos SIN tocar los roles.
     *
     * Es seguro correrlo en producción para dar de alta permisos nuevos: usa
     * firstOrCreate, no elimina nada y no re-sincroniza las asignaciones de los
     * roles. La creación y asignación de roles vive en RolesSeeder.
     */
    public function run(): void
    {
        // Resetear cache de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_merge(...array_values(self::groupedPermissions())) as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    /**
     * Permisos agrupados por módulo. Fuente única de verdad, tanto para crearlos
     * aquí como para que RolesSeeder los asigne a los roles.
     *
     * @return array<string, list<string>>
     */
    public static function groupedPermissions(): array
    {
        // Permisos del módulo STI
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

        // Permisos del módulo Intranet
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

        // Permisos del módulo Costos
        $costosPermissions = [
            'costos.proveedores.ver',
            'costos.proveedores.crear',
            'costos.proveedores.editar',
            'costos.proveedores.eliminar',
            'costos.proveedores.aprobar',
            'costos.regimenes-fiscales.ver',
            'costos.regimenes-fiscales.crear',
            'costos.regimenes-fiscales.editar',
            'costos.regimenes-fiscales.eliminar',
            'costos.bancos.ver',
            'costos.bancos.crear',
            'costos.bancos.editar',
            'costos.bancos.eliminar',
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
            'costos.solicitudes-pago.ver-todas',
            'costos.solicitudes-pago.ver-departamentos-aprobador',
            'costos.solicitudes-pago.ver-departamento-propio',
            'costos.solicitudes-pago.crear',
            'costos.solicitudes-pago.editar',
            'costos.solicitudes-pago.eliminar',
            'costos.solicitudes-pago.cancelar-propia',
            'costos.afectaciones.ver',
            'costos.afectaciones.crear',
            'costos.afectaciones.editar',
            'costos.afectaciones.eliminar',
            'costos.centros-costos.reasignar',
            'costos.pagos.ver',
            'costos.pagos.crear',
            'costos.pagos.editar',
            'costos.pagos.eliminar',
            'costos.ordenes-compra.ver',
            'costos.ordenes-compra.ver-todas',
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
            'costos.entregas.editar',
            'costos.entregas.cancelar',
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
            'costos.requisiciones.ver-todas',
            'costos.requisiciones.ver-departamentos-aprobador',
            'costos.requisiciones.ver-departamento-propio',
            'costos.requisiciones.crear',
            'costos.requisiciones.cotizar',
            'costos.requisiciones.control',
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

        // Permisos del módulo Produccion
        $prodPermissions = [
            'prod.registros.ver',
            'prod.registros.crear',
            'prod.registros.eliminar',
            'prod.destajos.ver',
            'prod.destajos.crear',
            'prod.destajos.cerrar',
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
            'prod.procesos.ver',
            'prod.procesos.crear',
            'prod.procesos.editar',
            'prod.procesos.eliminar',
            'prod.conceptos.ver',
            'prod.conceptos.crear',
            'prod.conceptos.editar',
            'prod.conceptos.eliminar',
            'prod.categorias.ver',
            'prod.categorias.crear',
            'prod.categorias.editar',
            'prod.categorias.eliminar',
            // Catalogo de piezas versionado (uno vigente por obra)
            'prod.catalogos.ver',
            'prod.catalogos.crear',
            'prod.catalogos.editar',
            'prod.catalogos.eliminar',
            // Ubicaciones del grupo de trabajo
            'prod.ubicaciones.ver',
            'prod.ubicaciones.crear',
            'prod.ubicaciones.editar',
            'prod.ubicaciones.eliminar',
            // Categorias de empleado (peso del reparto)
            'prod.categorias-empleado.ver',
            'prod.categorias-empleado.crear',
            'prod.categorias-empleado.editar',
            'prod.categorias-empleado.eliminar',
            // Asistencia del destajo (obligatoria para cerrar)
            'prod.asistencia.ver',
            'prod.asistencia.registrar',
            // Pagos extra dentro del destajo
            'prod.pagos-extra.ver',
            'prod.pagos-extra.crear',
            'prod.pagos-extra.eliminar',
            // Liquidaciones y orden de pago
            'prod.liquidaciones.ver',
            // Configuracion del modulo (salario minimo diario)
            'prod.configuracion.ver',
            'prod.configuracion.editar',
        ];

        // Permisos del módulo Infraestructura
        $infraPermissions = [
            'infra.recorridos.ver',
            'infra.recorridos.crear',
        ];

        // Permisos del módulo Cobranza
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

        // Permisos del módulo RH
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

        // Permisos del módulo Drive
        $drivePermissions = [
            'drive.gestionar',
        ];

        // Permisos del módulo DG Reportes
        // El acceso a subir reportes se controla por la tabla pivote dg_carpeta_usuario (puede_escribir), no por permiso.
        $dgPermissions = [
            'dg.reportes.ver',
            'dg.reportes.administrar',
            'dg.reportes.notas',
        ];

        // Permisos del módulo Calidad
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

        // Permisos del modulo Calidad, version nueva (prefijo qal_)
        //
        // Los cal.* de arriba se conservan porque los usa la aplicacion
        // anterior por API mientras siga viva; se borran el dia que se apague.
        // Todo lo que se construya de ahora en adelante pide qal.*.
        //
        // Los catalogos no tienen 'eliminar': ahi nada se borra, se desactiva,
        // para no dejar reportes citando algo que ya no existe.
        $qalPermissions = [
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
            // PND es recurso aparte de reportes: son juntas soldadas evaluadas
            // por un laboratorio externo, no piezas revisadas a la vista, y las
            // captura otra persona.
            'qal.pnd.ver',
            'qal.pnd.crear',
            'qal.pnd.editar',
            'qal.pnd.eliminar',
            // El tablero solo lee, y su lector es direccion, no el inspector.
            'qal.dashboard.ver',
            // El reporte semanal es el documento con folio de formato que sale
            // de la empresa, no la herramienta diaria del area.
            'qal.reporte-semanal.ver',
            // Incidencias en obra: lo que falla durante el montaje. Es un
            // circuito aparte del taller y lo captura el residente, no el
            // inspector, por eso lleva sus propios permisos.
            'qal.incidencias.ver',
            'qal.incidencias.capturar',
            'qal.incidencias.eliminar',
        ];

        // Permisos Core
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

        // Permisos del módulo Cotización (catálogos globales — Fase 0).
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

        return [
            'sti' => $stiPermissions,
            'intra' => $intraPermissions,
            'costos' => $costosPermissions,
            'prod' => $prodPermissions,
            'infra' => $infraPermissions,
            'cob' => $cobPermissions,
            'rh' => $rhPermissions,
            'drive' => $drivePermissions,
            'dg' => $dgPermissions,
            'cal' => $calPermissions,
            'qal' => $qalPermissions,
            'core' => $corePermissions,
            'cotiz' => $cotizPermissions,
            'cotizTrabajo' => $cotizTrabajoPermissions,
        ];
    }
}
