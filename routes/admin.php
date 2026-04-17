<?php

use App\Http\Controllers\Admin\BadgeConfigController;
use App\Http\Controllers\Admin\Cob\AdendaController as CobAdendaController;
use App\Http\Controllers\Admin\Cob\AnticipoController as CobAnticipoController;
use App\Http\Controllers\Admin\Cob\ClienteController as CobClienteController;
use App\Http\Controllers\Admin\Cob\ComparativoController as CobComparativoController;
use App\Http\Controllers\Admin\Cob\ConfiguracionDocumentoController as CobConfiguracionDocumentoController;
use App\Http\Controllers\Admin\Cob\ContactoController as CobContactoController;
use App\Http\Controllers\Admin\Cob\DashboardController as CobDashboardController;
use App\Http\Controllers\Admin\Cob\DeduccionController as CobDeduccionController;
use App\Http\Controllers\Admin\Cob\DisputaController as CobDisputaController;
use App\Http\Controllers\Admin\Cob\EstimacionController as CobEstimacionController;
use App\Http\Controllers\Admin\Cob\EstimacionPagoController as CobEstimacionPagoController;
use App\Http\Controllers\Admin\Cob\EventoController as CobEventoController;
use App\Http\Controllers\Admin\Cob\ObraCobranzaController as CobObraCobranzaController;
use App\Http\Controllers\Admin\Cob\PartidaController as CobPartidaController;
use App\Http\Controllers\Admin\Cob\PenalizacionController as CobPenalizacionController;
use App\Http\Controllers\Admin\Cob\TipoRetencionController as CobTipoRetencionController;
use App\Http\Controllers\Admin\Costos\AfectacionPresupuestalController as CostosAfectacionPresupuestalController;
use App\Http\Controllers\Admin\Costos\AprobacionController as CostosAprobacionController;
use App\Http\Controllers\Admin\Costos\CuentaInternaController as CostosCuentaInternaController;
use App\Http\Controllers\Admin\Costos\EntregaController as CostosEntregaController;
use App\Http\Controllers\Admin\Costos\FacturaAdminController as CostosFacturaAdminController;
use App\Http\Controllers\Admin\Costos\FirmaController as CostosFirmaController;
use App\Http\Controllers\Admin\Costos\ObraRubroController as CostosObraRubroController;
use App\Http\Controllers\Admin\Costos\OrdenCompraController as CostosOrdenCompraController;
use App\Http\Controllers\Admin\Costos\PagoController as CostosPagoController;
use App\Http\Controllers\Admin\Costos\PermisoController as CostosPermisoController;
use App\Http\Controllers\Admin\Costos\PresupuestoController as CostosPresupuestoController;
use App\Http\Controllers\Admin\Costos\RubroController as CostosRubroController;
use App\Http\Controllers\Admin\Costos\SolicitudPagoController as CostosSolicitudPagoController;
use App\Http\Controllers\Admin\Costos\TipoRubroController as CostosTipoRubroController;
use App\Http\Controllers\Admin\Costos\TipoSolicitudController as CostosTipoSolicitudController;
use App\Http\Controllers\Admin\DepartamentoController;
use App\Http\Controllers\Admin\Dg\CarpetaAccesoController as DgCarpetaAccesoController;
use App\Http\Controllers\Admin\Dg\CarpetaController as DgCarpetaController;
use App\Http\Controllers\Admin\Dg\DashboardController as DgDashboardController;
use App\Http\Controllers\Admin\Dg\MisReportesController as DgMisReportesController;
use App\Http\Controllers\Admin\Dg\NotaController as DgNotaController;
use App\Http\Controllers\Admin\Dg\ReporteController as DgReporteController;
use App\Http\Controllers\Admin\Drive\DriveCarpetaController;
use App\Http\Controllers\Admin\Drive\DriveDashboardController as DriveAdminDashboardController;
use App\Http\Controllers\Admin\Drive\DriveExternoController;
use App\Http\Controllers\Admin\Infra\RecorridoController as InfraRecorridoController;
use App\Http\Controllers\Admin\Infra\TurnoController as InfraTurnoController;
use App\Http\Controllers\Admin\Intra\AreaController as IntraAreaController;
use App\Http\Controllers\Admin\Intra\DocumentoController as IntraDocumentoController;
use App\Http\Controllers\Admin\Intra\SeccionEstaticaController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ObraController;
use App\Http\Controllers\Admin\Prod\ConceptoController as ProdConceptoController;
use App\Http\Controllers\Admin\Prod\CorteController as ProdCorteController;
use App\Http\Controllers\Admin\Prod\GrupoPrecioConceptoController as ProdGrupoPrecioConceptoController;
use App\Http\Controllers\Admin\Prod\GrupoPrecioController as ProdGrupoPrecioController;
use App\Http\Controllers\Admin\Prod\GrupoTrabajoController as ProdGrupoTrabajoController;
use App\Http\Controllers\Admin\Prod\PagoExtraController as ProdPagoExtraController;
use App\Http\Controllers\Admin\Prod\RegistroController as ProdRegistroController;
use App\Http\Controllers\Admin\Prod\TipoPagoExtraController as ProdTipoPagoExtraController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\Rh\DashboardController as RhDashboardController;
use App\Http\Controllers\Admin\Rh\OnboardingController as RhOnboardingController;
use App\Http\Controllers\Admin\Rh\PeriodoLaboralController as RhPeriodoLaboralController;
use App\Http\Controllers\Admin\Rh\PermisoAusenciaController as RhPermisoAusenciaController;
use App\Http\Controllers\Admin\Rh\PersonaController as RhPersonaController;
use App\Http\Controllers\Admin\Rh\PuestoController as RhPuestoController;
use App\Http\Controllers\Admin\Rh\RequerimientoController as RhRequerimientoController;
use App\Http\Controllers\Admin\Rh\RequisicionController as RhRequisicionController;
use App\Http\Controllers\Admin\Rh\SkillController as RhSkillController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Sti\AsignacionActivoController as StiAsignacionActivoController;
use App\Http\Controllers\Admin\Sti\DashboardController as StiDashboardController;
use App\Http\Controllers\Admin\Sti\EquipoController as StiEquipoController;
use App\Http\Controllers\Admin\Sti\ItemController as StiItemController;
use App\Http\Controllers\Admin\Sti\ItemTipoController as StiItemTipoController;
use App\Http\Controllers\Admin\Sti\MantenimientoController as StiMantenimientoController;
use App\Http\Controllers\Admin\Sti\PlanController as StiPlanController;
use App\Http\Controllers\Admin\Sti\StatusController as StiStatusController;
use App\Http\Controllers\Admin\Sti\TecnicoController as StiTecnicoController;
use App\Http\Controllers\Admin\Sti\TicketController as StiTicketController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', UsuarioController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('obras', ObraController::class);
    Route::post('obras/{obra}/import-conceptos', [ObraController::class, 'importConceptos'])->name('obras.import-conceptos');
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
    Route::resource('media', MediaController::class);
    Route::resource('tags', TagController::class);
    Route::resource('badge-configs', BadgeConfigController::class)->parameters(['badge-configs' => 'badgeConfig']);

    // Intranet admin routes
    Route::prefix('intra')->name('intra.')->group(function () {
        Route::resource('secciones', SeccionEstaticaController::class);
        Route::resource('areas', IntraAreaController::class);
        Route::resource('documentos', IntraDocumentoController::class);
    });

    // Produccion admin routes
    Route::prefix('prod')->name('prod.')->group(function () {
        Route::get('conceptos/obra/{obra}', [ProdConceptoController::class, 'showByObra'])->name('conceptos.show-by-obra');
        Route::post('conceptos/obra/{obra}/import-csv', [ProdConceptoController::class, 'importCsv'])->name('conceptos.import-csv');
        Route::resource('conceptos', ProdConceptoController::class)->parameters(['conceptos' => 'concepto']);
        Route::get('grupo-precios/obra/{obra}', [ProdGrupoPrecioController::class, 'showByObra'])->name('grupo-precios.show-by-obra');
        Route::resource('grupo-precios', ProdGrupoPrecioController::class)->parameters(['grupo-precios' => 'grupoPrecio'])->except(['show']);
        Route::post('grupo-precios/{grupoPrecio}/assign-conceptos', [ProdGrupoPrecioController::class, 'assignConceptos'])->name('grupo-precios.assign-conceptos');

        // Pivot concepto-grupo precio
        Route::post('grupo-precio-conceptos', [ProdGrupoPrecioConceptoController::class, 'store'])->name('grupo-precio-conceptos.store');
        Route::delete('grupo-precio-conceptos/{grupoPrecioConcepto}', [ProdGrupoPrecioConceptoController::class, 'destroy'])->name('grupo-precio-conceptos.destroy');

        // Grupos de trabajo
        Route::resource('grupos-trabajo', ProdGrupoTrabajoController::class)->parameters(['grupos-trabajo' => 'grupoTrabajo']);
        Route::post('grupos-trabajo/{grupoTrabajo}/empleados', [ProdGrupoTrabajoController::class, 'storeEmpleado'])->name('grupos-trabajo.empleados.store');
        Route::delete('grupos-trabajo/{grupoTrabajo}/empleados/{empleado}', [ProdGrupoTrabajoController::class, 'destroyEmpleado'])->name('grupos-trabajo.empleados.destroy');

        // Catalogos
        Route::resource('tipos-pago-extra', ProdTipoPagoExtraController::class)->parameters(['tipos-pago-extra' => 'tipoPagoExtra']);

        // Registros y Pagos Extra
        Route::resource('registros', ProdRegistroController::class)->parameters(['registros' => 'registro'])->except(['edit', 'update']);
        Route::resource('pagos-extra', ProdPagoExtraController::class)->parameters(['pagos-extra' => 'pagoExtra'])->except(['edit', 'update', 'show']);

        // Cortes y liquidaciones
        Route::resource('cortes', ProdCorteController::class)->except(['edit', 'update'])->parameters(['cortes' => 'corte']);
        Route::post('cortes/{corte}/cerrar', [ProdCorteController::class, 'cerrar'])->name('cortes.cerrar');
    });

    // Infraestructura admin routes
    Route::prefix('infra')->name('infra.')->group(function () {
        Route::get('recorridos', [InfraRecorridoController::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/show', [InfraRecorridoController::class, 'show'])->name('recorridos.show');
        Route::get('recorridos/create', [InfraRecorridoController::class, 'create'])->name('recorridos.create');
        Route::post('recorridos', [InfraRecorridoController::class, 'store'])->name('recorridos.store');
        Route::resource('turnos', InfraTurnoController::class)->parameters(['turnos' => 'turno']);
    });

    // Costos admin routes
    Route::prefix('costos')->name('costos.')->group(function () {
        Route::resource('tipo-rubros', CostosTipoRubroController::class)->parameters(['tipo-rubros' => 'tipoRubro']);
        Route::resource('rubros', CostosRubroController::class)->parameters(['rubros' => 'rubro']);
        Route::resource('tipo-solicitudes', CostosTipoSolicitudController::class)->parameters(['tipo-solicitudes' => 'tipoSolicitud']);
        Route::resource('permisos', CostosPermisoController::class)->parameters(['permisos' => 'permiso']);
        Route::post('permisos/{permiso}/sync-departamentos', [CostosPermisoController::class, 'syncDepartamentos'])->name('permisos.sync-departamentos');
        Route::resource('solicitudes-pago', CostosSolicitudPagoController::class)->parameters(['solicitudes-pago' => 'solicitudPago']);
        Route::post('solicitudes-pago/{solicitudPago}/archivos', [CostosSolicitudPagoController::class, 'storeArchivo'])->name('solicitudes-pago.archivos.store');
        Route::patch('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'updateArchivo'])->name('solicitudes-pago.archivos.update');
        Route::delete('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'destroyArchivo'])->name('solicitudes-pago.archivos.destroy');
        Route::get('solicitudes-pago/{solicitudPago}/pdf', [CostosSolicitudPagoController::class, 'generarPdf'])->name('solicitudes-pago.pdf');
        Route::post('solicitudes-pago/{solicitudPago}/upload-firmado', [CostosSolicitudPagoController::class, 'uploadFirmado'])->name('solicitudes-pago.upload-firmado');
        Route::post('solicitudes-pago/{solicitudPago}/cancelar', [CostosSolicitudPagoController::class, 'cancelar'])->name('solicitudes-pago.cancelar');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-costos', [CostosSolicitudPagoController::class, 'confirmarCostos'])->name('solicitudes-pago.confirmar-costos');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-contabilidad', [CostosSolicitudPagoController::class, 'confirmarContabilidad'])->name('solicitudes-pago.confirmar-contabilidad');

        // Ordenes de Compra
        Route::resource('ordenes-compra', CostosOrdenCompraController::class)->only(['index', 'create', 'store', 'show', 'destroy'])->parameters(['ordenes-compra' => 'ordenCompra']);
        Route::post('ordenes-compra/{ordenCompra}/cancelar', [CostosOrdenCompraController::class, 'cancelar'])->name('ordenes-compra.cancelar');

        // Facturas
        Route::get('facturas/reporte-semanal', [CostosFacturaAdminController::class, 'reporteSemanal'])->name('facturas.reporte-semanal');
        Route::get('facturas/reporte-semanal-proveedor', [CostosFacturaAdminController::class, 'reporteSemanalProveedor'])->name('facturas.reporte-semanal-proveedor');
        Route::resource('facturas', CostosFacturaAdminController::class)->only(['index', 'show'])->parameters(['facturas' => 'factura']);
        Route::post('facturas/{factura}/entregas', [CostosEntregaController::class, 'store'])->name('facturas.entregas.store');
        Route::post('facturas/{factura}/aprobar-costos', [CostosFacturaAdminController::class, 'aprobarCostos'])->name('facturas.aprobar-costos');
        Route::post('facturas/{factura}/aceptar-contabilidad', [CostosFacturaAdminController::class, 'aceptarContabilidad'])->name('facturas.aceptar-contabilidad');

        // Pagos
        Route::get('pagos/reporte', [CostosPagoController::class, 'reporte'])->name('pagos.reporte');
        Route::resource('pagos', CostosPagoController::class)->only(['index', 'show'])->parameters(['pagos' => 'pago']);
        Route::post('pagos/{pago}/programar', [CostosPagoController::class, 'programar'])->name('pagos.programar');
        Route::get('pagos/{pago}/parcializar', [CostosPagoController::class, 'showParcializar'])->name('pagos.parcializar.show');
        Route::post('pagos/{pago}/parcializar', [CostosPagoController::class, 'parcializar'])->name('pagos.parcializar');
        Route::post('pagos/{pago}/upload-comprobante', [CostosPagoController::class, 'uploadComprobante'])->name('pagos.upload-comprobante');

        // Firma del aprobador
        Route::get('firma', [CostosFirmaController::class, 'edit'])->name('firma.edit');
        Route::post('firma', [CostosFirmaController::class, 'update'])->name('firma.update');
        Route::delete('firma', [CostosFirmaController::class, 'destroy'])->name('firma.destroy');

        // Aprobaciones digitales
        Route::get('aprobaciones', [CostosAprobacionController::class, 'index'])->name('aprobaciones.index');
        Route::get('aprobaciones/{aprobacionSolicitud}', [CostosAprobacionController::class, 'show'])->name('aprobaciones.show');
        Route::post('aprobaciones/{aprobacionSolicitud}/aprobar', [CostosAprobacionController::class, 'aprobar'])->name('aprobaciones.aprobar');
        Route::post('aprobaciones/{aprobacionSolicitud}/rechazar', [CostosAprobacionController::class, 'rechazar'])->name('aprobaciones.rechazar');

        // Afectaciones presupuestales
        Route::resource('afectaciones', CostosAfectacionPresupuestalController::class)->parameters(['afectaciones' => 'afectacion']);
        Route::get('afectaciones/{afectacion}/pdf', [CostosAfectacionPresupuestalController::class, 'generarPdf'])->name('afectaciones.pdf');
        Route::post('afectaciones/{afectacion}/upload-firmado', [CostosAfectacionPresupuestalController::class, 'uploadFirmado'])->name('afectaciones.upload-firmado');
        Route::post('afectaciones/{afectacion}/cancelar', [CostosAfectacionPresupuestalController::class, 'cancelar'])->name('afectaciones.cancelar');

        // Presupuestos (vista por obra)
        Route::get('presupuestos', [CostosPresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('presupuestos/reporte-pdf', [CostosPresupuestoController::class, 'generarReportePdf'])->name('presupuestos.reporte-pdf');
        Route::get('presupuestos/{obra}/edit', [CostosPresupuestoController::class, 'edit'])->name('presupuestos.edit');

        // Cuentas Internas
        Route::get('cuentas-internas', [CostosCuentaInternaController::class, 'index'])->name('cuentas-internas.index');
        Route::post('cuentas-internas', [CostosCuentaInternaController::class, 'store'])->name('cuentas-internas.store');
        Route::delete('cuentas-internas/{usuario}/{rol}', [CostosCuentaInternaController::class, 'destroy'])->name('cuentas-internas.destroy');

        Route::post('obra-rubros', [CostosObraRubroController::class, 'store'])->name('obra-rubros.store');
        Route::put('obra-rubros/{obraRubro}', [CostosObraRubroController::class, 'update'])->name('obra-rubros.update');
        Route::delete('obra-rubros/{obraRubro}', [CostosObraRubroController::class, 'destroy'])->name('obra-rubros.destroy');
    });

    // Cobranza admin routes
    Route::prefix('cob')->name('cob.')->group(function () {
        Route::get('dashboard', [CobDashboardController::class, 'index'])->name('dashboard.index');

        Route::resource('clientes', CobClienteController::class)->parameters(['clientes' => 'cliente']);
        Route::post('clientes/{cliente}/contactos', [CobContactoController::class, 'store'])->name('clientes.contactos.store');
        Route::put('clientes/{cliente}/contactos/{contacto}', [CobContactoController::class, 'update'])->name('clientes.contactos.update');
        Route::delete('clientes/{cliente}/contactos/{contacto}', [CobContactoController::class, 'destroy'])->name('clientes.contactos.destroy');

        Route::resource('tipos-retenciones', CobTipoRetencionController::class)->parameters(['tipos-retenciones' => 'tipoRetencion']);

        // Obras cobranza
        Route::get('obras', [CobObraCobranzaController::class, 'index'])->name('obras.index');
        Route::get('obras/reporte-pdf', [CobObraCobranzaController::class, 'reportePdf'])->name('obras.reporte-pdf');
        Route::get('obras/{obra}', [CobObraCobranzaController::class, 'show'])->name('obras.show');
        Route::get('obras/{obra}/estado-cuenta-pdf', [CobObraCobranzaController::class, 'estadoCuentaPdf'])->name('obras.estado-cuenta-pdf');
        Route::put('obras/{obra}/financial', [CobObraCobranzaController::class, 'updateFinancial'])->name('obras.update-financial');

        // Sub-recursos de obra
        Route::get('obras/{obra}/partidas/create', [CobPartidaController::class, 'create'])->name('obras.partidas.create');
        Route::post('obras/{obra}/partidas', [CobPartidaController::class, 'store'])->name('obras.partidas.store');
        Route::get('obras/{obra}/partidas/{partida}/edit', [CobPartidaController::class, 'edit'])->name('obras.partidas.edit');
        Route::put('obras/{obra}/partidas/{partida}', [CobPartidaController::class, 'update'])->name('obras.partidas.update');
        Route::delete('obras/{obra}/partidas/{partida}', [CobPartidaController::class, 'destroy'])->name('obras.partidas.destroy');

        Route::get('obras/{obra}/estimaciones/create', [CobEstimacionController::class, 'create'])->name('obras.estimaciones.create');
        Route::post('obras/{obra}/estimaciones', [CobEstimacionController::class, 'store'])->name('obras.estimaciones.store');
        Route::get('obras/{obra}/estimaciones/{estimacion}/edit', [CobEstimacionController::class, 'edit'])->name('obras.estimaciones.edit');
        Route::put('obras/{obra}/estimaciones/{estimacion}', [CobEstimacionController::class, 'update'])->name('obras.estimaciones.update');
        Route::delete('obras/{obra}/estimaciones/{estimacion}', [CobEstimacionController::class, 'destroy'])->name('obras.estimaciones.destroy');
        Route::post('obras/{obra}/estimaciones/{estimacion}/cambiar-estado', [CobEstimacionController::class, 'cambiarEstado'])->name('obras.estimaciones.cambiar-estado');

        Route::post('obras/{obra}/estimaciones/{estimacion}/pagos', [CobEstimacionPagoController::class, 'store'])->name('obras.estimaciones.pagos.store');

        Route::get('obras/{obra}/anticipos/create', [CobAnticipoController::class, 'create'])->name('obras.anticipos.create');
        Route::post('obras/{obra}/anticipos', [CobAnticipoController::class, 'store'])->name('obras.anticipos.store');
        Route::get('obras/{obra}/anticipos/{anticipo}/edit', [CobAnticipoController::class, 'edit'])->name('obras.anticipos.edit');
        Route::put('obras/{obra}/anticipos/{anticipo}', [CobAnticipoController::class, 'update'])->name('obras.anticipos.update');
        Route::delete('obras/{obra}/anticipos/{anticipo}', [CobAnticipoController::class, 'destroy'])->name('obras.anticipos.destroy');
        Route::post('obras/{obra}/anticipos/{anticipo}/marcar-pagado', [CobAnticipoController::class, 'marcarPagado'])->name('obras.anticipos.marcar-pagado');

        Route::get('obras/{obra}/adendas/create', [CobAdendaController::class, 'create'])->name('obras.adendas.create');
        Route::post('obras/{obra}/adendas', [CobAdendaController::class, 'store'])->name('obras.adendas.store');
        Route::get('obras/{obra}/adendas/{adenda}/edit', [CobAdendaController::class, 'edit'])->name('obras.adendas.edit');
        Route::put('obras/{obra}/adendas/{adenda}', [CobAdendaController::class, 'update'])->name('obras.adendas.update');
        Route::delete('obras/{obra}/adendas/{adenda}', [CobAdendaController::class, 'destroy'])->name('obras.adendas.destroy');

        Route::get('obras/{obra}/comparativos/create', [CobComparativoController::class, 'create'])->name('obras.comparativos.create');
        Route::post('obras/{obra}/comparativos', [CobComparativoController::class, 'store'])->name('obras.comparativos.store');
        Route::get('obras/{obra}/comparativos/{comparativo}/edit', [CobComparativoController::class, 'edit'])->name('obras.comparativos.edit');
        Route::put('obras/{obra}/comparativos/{comparativo}', [CobComparativoController::class, 'update'])->name('obras.comparativos.update');
        Route::delete('obras/{obra}/comparativos/{comparativo}', [CobComparativoController::class, 'destroy'])->name('obras.comparativos.destroy');

        Route::get('obras/{obra}/deducciones/create', [CobDeduccionController::class, 'create'])->name('obras.deducciones.create');
        Route::post('obras/{obra}/deducciones', [CobDeduccionController::class, 'store'])->name('obras.deducciones.store');
        Route::get('obras/{obra}/deducciones/{deduccion}/edit', [CobDeduccionController::class, 'edit'])->name('obras.deducciones.edit');
        Route::put('obras/{obra}/deducciones/{deduccion}', [CobDeduccionController::class, 'update'])->name('obras.deducciones.update');
        Route::delete('obras/{obra}/deducciones/{deduccion}', [CobDeduccionController::class, 'destroy'])->name('obras.deducciones.destroy');

        Route::post('obras/{obra}/eventos', [CobEventoController::class, 'store'])->name('obras.eventos.store');
        Route::put('obras/{obra}/eventos/{evento}', [CobEventoController::class, 'update'])->name('obras.eventos.update');
        Route::delete('obras/{obra}/eventos/{evento}', [CobEventoController::class, 'destroy'])->name('obras.eventos.destroy');

        Route::get('obras/{obra}/disputas/create', [CobDisputaController::class, 'create'])->name('obras.disputas.create');
        Route::post('obras/{obra}/disputas', [CobDisputaController::class, 'store'])->name('obras.disputas.store');
        Route::get('obras/{obra}/disputas/{disputa}/edit', [CobDisputaController::class, 'edit'])->name('obras.disputas.edit');
        Route::put('obras/{obra}/disputas/{disputa}', [CobDisputaController::class, 'update'])->name('obras.disputas.update');
        Route::delete('obras/{obra}/disputas/{disputa}', [CobDisputaController::class, 'destroy'])->name('obras.disputas.destroy');

        Route::get('obras/{obra}/penalizaciones/create', [CobPenalizacionController::class, 'create'])->name('obras.penalizaciones.create');
        Route::post('obras/{obra}/penalizaciones', [CobPenalizacionController::class, 'store'])->name('obras.penalizaciones.store');
        Route::get('obras/{obra}/penalizaciones/{penalizacion}/edit', [CobPenalizacionController::class, 'edit'])->name('obras.penalizaciones.edit');
        Route::put('obras/{obra}/penalizaciones/{penalizacion}', [CobPenalizacionController::class, 'update'])->name('obras.penalizaciones.update');
        Route::delete('obras/{obra}/penalizaciones/{penalizacion}', [CobPenalizacionController::class, 'destroy'])->name('obras.penalizaciones.destroy');

        Route::get('obras/{obra}/configuracion-documentos/create', [CobConfiguracionDocumentoController::class, 'create'])->name('obras.configuracion-documentos.create');
        Route::post('obras/{obra}/configuracion-documentos', [CobConfiguracionDocumentoController::class, 'store'])->name('obras.configuracion-documentos.store');
        Route::get('obras/{obra}/configuracion-documentos/{configuracionDocumento}/edit', [CobConfiguracionDocumentoController::class, 'edit'])->name('obras.configuracion-documentos.edit');
        Route::put('obras/{obra}/configuracion-documentos/{configuracionDocumento}', [CobConfiguracionDocumentoController::class, 'update'])->name('obras.configuracion-documentos.update');
        Route::delete('obras/{obra}/configuracion-documentos/{configuracionDocumento}', [CobConfiguracionDocumentoController::class, 'destroy'])->name('obras.configuracion-documentos.destroy');
    });

    // STI admin routes
    Route::prefix('sti')->name('sti.')->group(function () {
        Route::get('dashboard', [StiDashboardController::class, 'index'])->name('dashboard.index');
        Route::resource('equipos', StiEquipoController::class);
        Route::resource('tecnicos', StiTecnicoController::class);
        Route::resource('status', StiStatusController::class)->parameters(['status' => 'status']);
        Route::resource('tickets', StiTicketController::class);
        // Mantenimientos: programacion, gantt (antes del resource para evitar colision con {mantenimiento})
        Route::get('mantenimientos/programacion', [StiMantenimientoController::class, 'programacion'])->name('mantenimientos.programacion');
        Route::post('mantenimientos/generar', [StiMantenimientoController::class, 'generar'])->name('mantenimientos.generar');
        Route::get('mantenimientos-gantt', [StiMantenimientoController::class, 'gantt'])->name('mantenimientos.gantt');

        Route::resource('mantenimientos', StiMantenimientoController::class)->except(['show']);
        Route::resource('asignacion-activos', StiAsignacionActivoController::class);
        Route::get('asignacion-activos/{asignacion_activo}/pdf', [StiAsignacionActivoController::class, 'generarPdf'])->name('asignacion-activos.pdf');
        Route::post('asignacion-activos/{asignacion_activo}/media', [StiAsignacionActivoController::class, 'storeMedia'])->name('asignacion-activos.media.store');
        Route::delete('asignacion-activos/{asignacion_activo}/media/{media}', [StiAsignacionActivoController::class, 'destroyMedia'])->name('asignacion-activos.media.destroy');

        // Costos y comentarios de tickets
        Route::post('tickets/{ticket}/costos', [StiTicketController::class, 'storeCosto'])->name('tickets.costos.store');
        Route::delete('tickets/{ticket}/costos/{costo}', [StiTicketController::class, 'destroyCosto'])->name('tickets.costos.destroy');
        Route::post('tickets/{ticket}/comentarios', [StiTicketController::class, 'storeComentario'])->name('tickets.comentarios.store');

        // Mantenimientos: completar, media, costos
        Route::get('mantenimientos-gantt-anual', [StiMantenimientoController::class, 'ganttAnual'])->name('mantenimientos.gantt-anual');
        Route::get('mantenimientos-gantt-anual/exportar', [StiMantenimientoController::class, 'exportarGanttAnual'])->name('mantenimientos.gantt-anual.exportar');
        Route::get('mantenimientos-gantt-anual/exportar-excel', [StiMantenimientoController::class, 'exportarGanttAnualExcel'])->name('mantenimientos.gantt-anual.exportar-excel');
        Route::post('mantenimientos/{mantenimiento}/completar', [StiMantenimientoController::class, 'completar'])->name('mantenimientos.completar');
        Route::post('mantenimientos/{mantenimiento}/checks/{checkEjecucion}/toggle', [StiMantenimientoController::class, 'toggleCheck'])->name('mantenimientos.checks.toggle');
        Route::post('mantenimientos/{mantenimiento}/media', [StiMantenimientoController::class, 'storeMedia'])->name('mantenimientos.media.store');
        Route::delete('mantenimientos/{mantenimiento}/media/{media}', [StiMantenimientoController::class, 'destroyMedia'])->name('mantenimientos.media.destroy');
        Route::post('mantenimientos/{mantenimiento}/costos', [StiMantenimientoController::class, 'storeCosto'])->name('mantenimientos.costos.store');
        Route::delete('mantenimientos/{mantenimiento}/costos/{costo}', [StiMantenimientoController::class, 'destroyCosto'])->name('mantenimientos.costos.destroy');

        // Inventario de items
        Route::post('items/{item}/asignar', [StiItemController::class, 'asignar'])->name('items.asignar');
        Route::post('items/{item}/retirar', [StiItemController::class, 'retirar'])->name('items.retirar');
        Route::post('items/{item}/media', [StiItemController::class, 'storeMedia'])->name('items.media.store');
        Route::delete('items/{item}/media/{media}', [StiItemController::class, 'destroyMedia'])->name('items.media.destroy');
        Route::resource('items', StiItemController::class);
        Route::resource('items-tipos', StiItemTipoController::class)->parameters(['items-tipos' => 'itemTipo']);

        // Planes de mantenimiento
        Route::resource('planes', StiPlanController::class)->parameters(['planes' => 'plan']);
        Route::post('planes/{plan}/checks', [StiPlanController::class, 'storeCheck'])->name('planes.checks.store');
        Route::put('planes/{plan}/checks/{check}', [StiPlanController::class, 'updateCheck'])->name('planes.checks.update');
        Route::delete('planes/{plan}/checks/{check}', [StiPlanController::class, 'destroyCheck'])->name('planes.checks.destroy');
        Route::post('planes/{plan}/reorder-checks', [StiPlanController::class, 'reorderChecks'])->name('planes.checks.reorder');
    });

    // Recursos Humanos admin routes
    Route::prefix('rh')->name('rh.')->group(function () {
        Route::get('dashboard', [RhDashboardController::class, 'index'])->name('dashboard.index');

        // Catalogos
        Route::resource('skills', RhSkillController::class);
        Route::resource('requerimientos', RhRequerimientoController::class)->parameters(['requerimientos' => 'requerimiento']);

        // Puestos
        Route::resource('puestos', RhPuestoController::class)->parameters(['puestos' => 'puesto']);
        Route::post('puestos/{puesto}/skills', [RhPuestoController::class, 'addSkill'])->name('puestos.skills.add');
        Route::delete('puestos/{puesto}/skills/{skill}', [RhPuestoController::class, 'removeSkill'])->name('puestos.skills.remove');
        Route::post('puestos/{puesto}/requerimientos', [RhPuestoController::class, 'addRequerimiento'])->name('puestos.requerimientos.add');
        Route::delete('puestos/{puesto}/requerimientos/{requerimiento}', [RhPuestoController::class, 'removeRequerimiento'])->name('puestos.requerimientos.remove');
        Route::post('puestos/{puesto}/actividades', [RhPuestoController::class, 'storeActividad'])->name('puestos.actividades.store');
        Route::delete('puestos/{puesto}/actividades/{actividad}', [RhPuestoController::class, 'destroyActividad'])->name('puestos.actividades.destroy');
        Route::post('puestos/{puesto}/documentos-puesto', [RhPuestoController::class, 'storeDocumentoPuesto'])->name('puestos.documentos-puesto.store');
        Route::delete('puestos/{puesto}/documentos-puesto/{documentoPuesto}', [RhPuestoController::class, 'destroyDocumentoPuesto'])->name('puestos.documentos-puesto.destroy');

        // Personas
        Route::resource('personas', RhPersonaController::class)->parameters(['personas' => 'persona']);
        Route::put('personas/{persona}/datos-extra', [RhPersonaController::class, 'updateDatosExtra'])->name('personas.datos-extra.update');
        Route::post('personas/{persona}/documentos', [RhPersonaController::class, 'storeDocumento'])->name('personas.documentos.store');
        Route::delete('personas/{persona}/documentos/{documento}', [RhPersonaController::class, 'destroyDocumento'])->name('personas.documentos.destroy');
        Route::post('personas/{persona}/contactos-emergencia', [RhPersonaController::class, 'storeContactoEmergencia'])->name('personas.contactos-emergencia.store');
        Route::delete('personas/{persona}/contactos-emergencia/{contacto}', [RhPersonaController::class, 'destroyContactoEmergencia'])->name('personas.contactos-emergencia.destroy');

        // Periodos Laborales
        Route::resource('periodos-laborales', RhPeriodoLaboralController::class)->parameters(['periodos-laborales' => 'periodoLaboral']);
        Route::post('periodos-laborales/{periodoLaboral}/terminar', [RhPeriodoLaboralController::class, 'terminar'])->name('periodos-laborales.terminar');
        Route::post('periodos-laborales/{periodoLaboral}/onboarding', [RhPeriodoLaboralController::class, 'crearOnboarding'])->name('periodos-laborales.onboarding');
        Route::get('periodos-laborales/{periodoLaboral}/contrato-pdf', [RhPeriodoLaboralController::class, 'generarContratoPdf'])->name('periodos-laborales.contrato-pdf');
        Route::get('periodos-laborales/{periodoLaboral}/gafete-pdf', [RhPeriodoLaboralController::class, 'generarGafetePdf'])->name('periodos-laborales.gafete-pdf');
        Route::get('periodos-laborales/{periodoLaboral}/tarjeta-pdf', [RhPeriodoLaboralController::class, 'generarTarjetaPdf'])->name('periodos-laborales.tarjeta-pdf');

        // Requisiciones
        Route::resource('requisiciones', RhRequisicionController::class)->parameters(['requisiciones' => 'requisicion']);
        Route::get('requisiciones/{requisicion}/candidatos', [RhRequisicionController::class, 'candidatos'])->name('requisiciones.candidatos');
        Route::post('requisiciones/{requisicion}/candidaturas', [RhRequisicionController::class, 'storeCandidatura'])->name('requisiciones.candidaturas.store');
        Route::delete('requisiciones/{requisicion}/candidaturas/{candidatura}', [RhRequisicionController::class, 'destroyCandidatura'])->name('requisiciones.candidaturas.destroy');

        // Onboarding
        Route::get('onboarding/{onboarding}', [RhOnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('onboarding/{onboarding}/tareas', [RhOnboardingController::class, 'storeTarea'])->name('onboarding.tareas.store');
        Route::post('onboarding/{onboarding}/tareas/{tarea}/toggle', [RhOnboardingController::class, 'toggleTarea'])->name('onboarding.tareas.toggle');
        Route::post('onboarding/{onboarding}/tareas/{tarea}/evidencia', [RhOnboardingController::class, 'subirEvidencia'])->name('onboarding.tareas.evidencia');
        Route::delete('onboarding/{onboarding}/tareas/{tarea}', [RhOnboardingController::class, 'destroyTarea'])->name('onboarding.tareas.destroy');

        // Permisos de Ausencia
        Route::resource('permisos-ausencia', RhPermisoAusenciaController::class)->parameters(['permisos-ausencia' => 'permisoAusencia']);
    });

    // Drive admin routes
    Route::prefix('drive')->name('drive.')->group(function () {
        Route::get('/', [DriveAdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('externos', DriveExternoController::class)->parameters(['externos' => 'externo']);
        Route::resource('carpetas', DriveCarpetaController::class)->parameters(['carpetas' => 'carpeta']);
        Route::patch('carpetas/{carpeta}/acceso/{externo}', [DriveCarpetaController::class, 'toggleAcceso'])->name('carpetas.toggle-acceso');
        Route::post('carpetas/{carpeta}/archivos', [DriveCarpetaController::class, 'uploadArchivo'])->name('carpetas.archivos.store');
        Route::get('archivos/{archivo}/descargar', [DriveCarpetaController::class, 'downloadArchivo'])->name('archivos.descargar');
        Route::delete('archivos/{archivo}', [DriveCarpetaController::class, 'destroyArchivo'])->name('archivos.destroy');
        Route::post('archivos/{archivo}/generar-link', [DriveCarpetaController::class, 'generarLink'])->name('archivos.generar-link');
        Route::delete('archivos/{archivo}/revocar-link', [DriveCarpetaController::class, 'revocarLink'])->name('archivos.revocar-link');
    });

    // DG Reportes
    Route::prefix('dg')->name('dg.')->group(function () {
        Route::get('/', [DgDashboardController::class, 'index'])->name('dashboard');
        Route::get('mis-reportes', [DgMisReportesController::class, 'index'])->name('mis-reportes');
        Route::get('notas', [DgNotaController::class, 'index'])->name('notas.index');
        Route::post('notas', [DgNotaController::class, 'store'])->name('notas.store');
        Route::get('notas/{nota}', [DgNotaController::class, 'show'])->name('notas.show');
        Route::patch('notas/{nota}', [DgNotaController::class, 'update'])->name('notas.update');
        Route::delete('notas/{nota}', [DgNotaController::class, 'destroy'])->name('notas.destroy');
        Route::post('carpetas', [DgCarpetaController::class, 'store'])->name('carpetas.store');
        Route::get('carpetas/{carpeta}', [DgCarpetaController::class, 'show'])->name('carpetas.show');
        Route::patch('carpetas/{carpeta}', [DgCarpetaController::class, 'update'])->name('carpetas.update');
        Route::delete('carpetas/{carpeta}', [DgCarpetaController::class, 'destroy'])->name('carpetas.destroy');
        Route::get('carpetas/{carpeta}/accesos', [DgCarpetaAccesoController::class, 'index'])->name('carpetas.accesos');
        Route::post('carpetas/{carpeta}/accesos', [DgCarpetaAccesoController::class, 'store'])->name('carpetas.accesos.store');
        Route::patch('carpetas/{carpeta}/accesos/{usuario}', [DgCarpetaAccesoController::class, 'update'])->name('carpetas.accesos.update');
        Route::delete('carpetas/{carpeta}/accesos/{usuario}', [DgCarpetaAccesoController::class, 'destroy'])->name('carpetas.accesos.destroy');
        Route::post('reportes', [DgReporteController::class, 'store'])->name('reportes.store');
        Route::patch('reportes/{reporte}', [DgReporteController::class, 'update'])->name('reportes.update');
        Route::delete('reportes/{reporte}', [DgReporteController::class, 'destroy'])->name('reportes.destroy');
        Route::post('reportes/{reporte}/archivos', [DgReporteController::class, 'uploadArchivo'])->name('reportes.archivos.store');
        Route::get('archivos/{archivo}/descargar', [DgReporteController::class, 'downloadArchivo'])->name('archivos.descargar');
        Route::get('archivos/{archivo}/stream', [DgReporteController::class, 'streamArchivo'])->name('archivos.stream');
        Route::post('archivos/{archivo}/marcar-visto', [DgReporteController::class, 'marcarVisto'])->name('archivos.marcar-visto');
        Route::patch('archivos/{archivo}/notas', [DgReporteController::class, 'updateNotas'])->name('archivos.notas.update');
        Route::delete('archivos/{archivo}', [DgReporteController::class, 'destroyArchivo'])->name('archivos.destroy');
    });

    // Documentacion
    Route::prefix('documentacion')->name('documentacion.')->group(function () {
        Route::get('costos', fn () => Inertia\Inertia::render('admin/documentacion/costos'))->name('costos');
        Route::get('rh', fn () => Inertia\Inertia::render('admin/documentacion/rh'))->name('rh');
    });
});
