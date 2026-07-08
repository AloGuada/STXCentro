<?php

use App\Http\Controllers\Admin\BadgeConfigController;
use App\Http\Controllers\Admin\Cob\AdendaController as CobAdendaController;
use App\Http\Controllers\Admin\Cob\AnticipoController as CobAnticipoController;
use App\Http\Controllers\Admin\Cob\ClienteController as CobClienteController;
use App\Http\Controllers\Admin\Cob\ComparativoController as CobComparativoController;
use App\Http\Controllers\Admin\Cob\ContactoController as CobContactoController;
use App\Http\Controllers\Admin\Cob\DashboardController as CobDashboardController;
use App\Http\Controllers\Admin\Cob\DeduccionController as CobDeduccionController;
use App\Http\Controllers\Admin\Cob\DisputaController as CobDisputaController;
use App\Http\Controllers\Admin\Cob\DocumentoSeccionController as CobDocumentoSeccionController;
use App\Http\Controllers\Admin\Cob\EstimacionController as CobEstimacionController;
use App\Http\Controllers\Admin\Cob\EstimacionPagoController as CobEstimacionPagoController;
use App\Http\Controllers\Admin\Cob\EventoController as CobEventoController;
use App\Http\Controllers\Admin\Cob\ObraCobranzaController as CobObraCobranzaController;
use App\Http\Controllers\Admin\Cob\PartidaController as CobPartidaController;
use App\Http\Controllers\Admin\Cob\PenalizacionController as CobPenalizacionController;
use App\Http\Controllers\Admin\Cob\ProyectoController as CobProyectoController;
use App\Http\Controllers\Admin\Cob\ProyectoDocumentoController as CobProyectoDocumentoController;
use App\Http\Controllers\Admin\Cob\ReporteCobranzaController as CobReporteCobranzaController;
use App\Http\Controllers\Admin\Cob\TipoRetencionController as CobTipoRetencionController;
use App\Http\Controllers\Admin\Costos\AfectacionPresupuestalController as CostosAfectacionPresupuestalController;
use App\Http\Controllers\Admin\Costos\AnticipoController as CostosAnticipoController;
use App\Http\Controllers\Admin\Costos\AprobacionController as CostosAprobacionController;
use App\Http\Controllers\Admin\Costos\ConfiguracionCostosController as CostosConfiguracionController;
use App\Http\Controllers\Admin\Costos\CuentaInternaController as CostosCuentaInternaController;
use App\Http\Controllers\Admin\Costos\DevolucionController as CostosDevolucionController;
use App\Http\Controllers\Admin\Costos\EditLockController as CostosEditLockController;
use App\Http\Controllers\Admin\Costos\EntregaController as CostosEntregaController;
use App\Http\Controllers\Admin\Costos\FacturaAdminController as CostosFacturaAdminController;
use App\Http\Controllers\Admin\Costos\FirmaController as CostosFirmaController;
use App\Http\Controllers\Admin\Costos\NotaCreditoController as CostosNotaCreditoController;
use App\Http\Controllers\Admin\Costos\ObraRubroController as CostosObraRubroController;
use App\Http\Controllers\Admin\Costos\OrdenCompraController as CostosOrdenCompraController;
use App\Http\Controllers\Admin\Costos\PagoController as CostosPagoController;
use App\Http\Controllers\Admin\Costos\PermisoController as CostosPermisoController;
use App\Http\Controllers\Admin\Costos\PresupuestoController as CostosPresupuestoController;
use App\Http\Controllers\Admin\Costos\ProductoController as CostosProductoController;
use App\Http\Controllers\Admin\Costos\RequisicionController as CostosRequisicionController;
use App\Http\Controllers\Admin\Costos\RequisicionCotizacionController as CostosRequisicionCotizacionController;
use App\Http\Controllers\Admin\Costos\RequisicionOcController as CostosRequisicionOcController;
use App\Http\Controllers\Admin\Costos\RequisicionSeleccionController as CostosRequisicionSeleccionController;
use App\Http\Controllers\Admin\Costos\RubroController as CostosRubroController;
use App\Http\Controllers\Admin\Costos\SolicitudPagoController as CostosSolicitudPagoController;
use App\Http\Controllers\Admin\Costos\TipoRubroController as CostosTipoRubroController;
use App\Http\Controllers\Admin\Costos\TipoSolicitudController as CostosTipoSolicitudController;
use App\Http\Controllers\Admin\Costos\UsoCfdiController as CostosUsoCfdiController;
use App\Http\Controllers\Admin\Cotiz\AnalisisMoController as CotizAnalisisMoController;
use App\Http\Controllers\Admin\Cotiz\CaratulaController as CotizCaratulaController;
use App\Http\Controllers\Admin\Cotiz\CatalogoObraController as CotizCatalogoObraController;
use App\Http\Controllers\Admin\Cotiz\CategoriaTarjetaController as CotizCategoriaTarjetaController;
use App\Http\Controllers\Admin\Cotiz\CentroCostoController as CotizCentroCostoController;
use App\Http\Controllers\Admin\Cotiz\CuadrillaController as CotizCuadrillaController;
use App\Http\Controllers\Admin\Cotiz\CuadrillaGlobalController as CotizCuadrillaGlobalController;
use App\Http\Controllers\Admin\Cotiz\EditLockController as CotizEditLockController;
use App\Http\Controllers\Admin\Cotiz\FactorController as CotizFactorController;
use App\Http\Controllers\Admin\Cotiz\FaseMontajeController as CotizFaseMontajeController;
use App\Http\Controllers\Admin\Cotiz\FleteEstandarController as CotizFleteEstandarController;
use App\Http\Controllers\Admin\Cotiz\FleteViaticoCatalogoController as CotizFleteViaticoCatalogoController;
use App\Http\Controllers\Admin\Cotiz\FleteViaticoObraController as CotizFleteViaticoObraController;
use App\Http\Controllers\Admin\Cotiz\GeneradoraController as CotizGeneradoraController;
use App\Http\Controllers\Admin\Cotiz\GeneradoraRegistroController as CotizGeneradoraRegistroController;
use App\Http\Controllers\Admin\Cotiz\InsumoController as CotizInsumoController;
use App\Http\Controllers\Admin\Cotiz\KilosRealesCategoriaController as CotizKilosRealesCategoriaController;
use App\Http\Controllers\Admin\Cotiz\MermaController as CotizMermaController;
use App\Http\Controllers\Admin\Cotiz\ObraController as CotizObraController;
use App\Http\Controllers\Admin\Cotiz\PersonalCategoriaController as CotizPersonalCategoriaController;
use App\Http\Controllers\Admin\Cotiz\PinturaFormulaController as CotizPinturaFormulaController;
use App\Http\Controllers\Admin\Cotiz\ResumenController as CotizResumenController;
use App\Http\Controllers\Admin\Cotiz\ResumenFilaController as CotizResumenFilaController;
use App\Http\Controllers\Admin\Cotiz\SeccionMontajeController as CotizSeccionMontajeController;
use App\Http\Controllers\Admin\Cotiz\TarjetaController as CotizTarjetaController;
use App\Http\Controllers\Admin\Cotiz\TarjetaDetalleController as CotizTarjetaDetalleController;
use App\Http\Controllers\Admin\Cotiz\VersionController as CotizVersionController;
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
use App\Http\Controllers\Admin\RegimenFiscalController;
use App\Http\Controllers\Admin\Rh\DashboardController as RhDashboardController;
use App\Http\Controllers\Admin\Rh\OnboardingController as RhOnboardingController;
use App\Http\Controllers\Admin\Rh\PeriodoLaboralController as RhPeriodoLaboralController;
use App\Http\Controllers\Admin\Rh\PermisoAusenciaController as RhPermisoAusenciaController;
use App\Http\Controllers\Admin\Rh\PersonaController as RhPersonaController;
use App\Http\Controllers\Admin\Rh\PuestoController as RhPuestoController;
use App\Http\Controllers\Admin\Rh\RequisicionController as RhRequisicionController;
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
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->name('usuarios.estado');
    Route::resource('usuarios', UsuarioController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('departamentos', DepartamentoController::class);
    Route::resource('obras', ObraController::class);
    Route::post('obras/{obra}/import-conceptos', [ObraController::class, 'importConceptos'])->name('obras.import-conceptos');
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
    Route::resource('regimenes-fiscales', RegimenFiscalController::class)
        ->parameters(['regimenes-fiscales' => 'regimenFiscal'])
        ->except(['show']);
    Route::middleware('role:super-admin')->group(function () {
        Route::resource('media', MediaController::class);
        Route::resource('tags', TagController::class);
    });
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
        // Edit lock transversal (aplica a cualquier entidad bloqueable de costos)
        Route::post('lock/{type}/{id}', [CostosEditLockController::class, 'lock'])->name('lock');
        Route::post('unlock/{type}/{id}', [CostosEditLockController::class, 'unlock'])->name('unlock');

        Route::resource('tipo-rubros', CostosTipoRubroController::class)->parameters(['tipo-rubros' => 'tipoRubro']);
        Route::resource('usos-cfdi', CostosUsoCfdiController::class)->parameters(['usos-cfdi' => 'usoCfdi'])->except(['show']);
        Route::resource('rubros', CostosRubroController::class)->parameters(['rubros' => 'rubro']);
        Route::get('productos/buscar', [CostosProductoController::class, 'buscar'])->name('productos.buscar');
        Route::resource('productos', CostosProductoController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->parameters(['productos' => 'producto']);
        Route::resource('tipo-solicitudes', CostosTipoSolicitudController::class)->parameters(['tipo-solicitudes' => 'tipoSolicitud']);
        Route::resource('permisos', CostosPermisoController::class)->parameters(['permisos' => 'permiso']);
        Route::post('permisos/{permiso}/sync-departamentos', [CostosPermisoController::class, 'syncDepartamentos'])->name('permisos.sync-departamentos');
        Route::get('configuracion', [CostosConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('configuracion', [CostosConfiguracionController::class, 'update'])->name('configuracion.update');
        // Devoluciones a proveedor (Fase 13)
        Route::resource('devoluciones', CostosDevolucionController::class)->only(['index', 'store', 'show'])->parameters(['devoluciones' => 'devolucion']);
        Route::post('devoluciones/{devolucion}/cancelar', [CostosDevolucionController::class, 'cancelar'])->name('devoluciones.cancelar');

        // Notas de crédito (Fase 12)
        Route::resource('notas-credito', CostosNotaCreditoController::class)->only(['index', 'show'])->parameters(['notas-credito' => 'notaCredito']);
        Route::post('notas-credito/{notaCredito}/cancelar', [CostosNotaCreditoController::class, 'cancelar'])->name('notas-credito.cancelar');

        // Anticipos a proveedor (Fase 11)
        Route::resource('anticipos', CostosAnticipoController::class)->only(['index', 'create', 'store', 'show'])->parameters(['anticipos' => 'anticipo']);
        Route::post('anticipos/aplicar', [CostosAnticipoController::class, 'aplicar'])->name('anticipos.aplicar');
        Route::post('anticipos/{anticipo}/cancelar', [CostosAnticipoController::class, 'cancelar'])->name('anticipos.cancelar');
        Route::get('facturas/{factura}/anticipos-disponibles', [CostosAnticipoController::class, 'disponiblesParaFactura'])->name('facturas.anticipos-disponibles');

        // Requisiciones (Fase 10.2)
        Route::resource('requisiciones', CostosRequisicionController::class)->parameters(['requisiciones' => 'requisicion']);
        Route::post('requisiciones/{requisicion}/duplicar', [CostosRequisicionController::class, 'duplicar'])->name('requisiciones.duplicar');
        Route::post('requisiciones/{requisicion}/cancelar', [CostosRequisicionController::class, 'cancelar'])->name('requisiciones.cancelar');
        Route::post('requisiciones/{requisicion}/enviar-aprobacion', [CostosRequisicionController::class, 'enviarAprobacion'])->name('requisiciones.enviar-aprobacion');
        Route::post('requisiciones/{requisicion}/firmar-final', [CostosRequisicionController::class, 'firmarFinal'])->name('requisiciones.firmar-final');
        Route::post('requisiciones/{requisicion}/liberar', [CostosRequisicionController::class, 'liberar'])->name('requisiciones.liberar');
        Route::post('requisiciones/{requisicion}/re-apartar', [CostosRequisicionController::class, 'reApartar'])->name('requisiciones.re-apartar');
        Route::post('requisiciones/detalles/{detalle}/clasificacion', [CostosRequisicionCotizacionController::class, 'clasificar'])->name('requisiciones.detalles.clasificar');
        Route::patch('requisiciones/detalles/{detalle}/producto', [CostosRequisicionCotizacionController::class, 'actualizarProducto'])->name('requisiciones.detalles.producto');
        Route::post('requisiciones/{requisicion}/documentos', [CostosRequisicionCotizacionController::class, 'subirDocumento'])->name('requisiciones.documentos.store');
        Route::delete('requisiciones/{requisicion}/documentos/{media}', [CostosRequisicionCotizacionController::class, 'eliminarDocumento'])->name('requisiciones.documentos.destroy');
        Route::post('requisiciones/cotizaciones', [CostosRequisicionCotizacionController::class, 'store'])->name('requisiciones.cotizaciones.store');
        Route::post('requisiciones/{requisicion}/cotizaciones/tiempo-entrega', [CostosRequisicionCotizacionController::class, 'tiempoEntrega'])->name('requisiciones.cotizaciones.tiempo-entrega');
        Route::delete('requisiciones/cotizaciones/{precio}', [CostosRequisicionCotizacionController::class, 'destroy'])->name('requisiciones.cotizaciones.destroy');
        Route::delete('requisiciones/{requisicion}/proveedores/{proveedor}', [CostosRequisicionCotizacionController::class, 'destroyProveedor'])->name('requisiciones.proveedores.destroy');
        Route::post('requisiciones/selecciones', [CostosRequisicionSeleccionController::class, 'store'])->name('requisiciones.selecciones.store');
        Route::patch('requisiciones/selecciones/{seleccion}', [CostosRequisicionSeleccionController::class, 'update'])->name('requisiciones.selecciones.update');
        Route::delete('requisiciones/selecciones/{seleccion}', [CostosRequisicionSeleccionController::class, 'destroy'])->name('requisiciones.selecciones.destroy');
        Route::post('requisiciones/{requisicion}/ocs', [CostosRequisicionOcController::class, 'store'])->name('requisiciones.ocs.store');

        Route::get('solicitudes-pago/reporte-pdf', [CostosSolicitudPagoController::class, 'reportePdf'])->name('solicitudes-pago.reporte-pdf');
        Route::resource('solicitudes-pago', CostosSolicitudPagoController::class)->parameters(['solicitudes-pago' => 'solicitudPago']);
        Route::post('solicitudes-pago/{solicitudPago}/archivos', [CostosSolicitudPagoController::class, 'storeArchivo'])->name('solicitudes-pago.archivos.store');
        Route::patch('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'updateArchivo'])->name('solicitudes-pago.archivos.update');
        Route::delete('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'destroyArchivo'])->name('solicitudes-pago.archivos.destroy');
        Route::get('solicitudes-pago/{solicitudPago}/pdf', [CostosSolicitudPagoController::class, 'generarPdf'])->name('solicitudes-pago.pdf');
        Route::post('solicitudes-pago/{solicitudPago}/upload-firmado', [CostosSolicitudPagoController::class, 'uploadFirmado'])->name('solicitudes-pago.upload-firmado');
        Route::post('solicitudes-pago/{solicitudPago}/cancelar', [CostosSolicitudPagoController::class, 'cancelar'])->name('solicitudes-pago.cancelar');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-costos', [CostosSolicitudPagoController::class, 'confirmarCostos'])->name('solicitudes-pago.confirmar-costos');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-contabilidad', [CostosSolicitudPagoController::class, 'confirmarContabilidad'])->name('solicitudes-pago.confirmar-contabilidad');
        Route::post('solicitudes-pago/{solicitudPago}/re-apartar', [CostosSolicitudPagoController::class, 'reApartar'])->name('solicitudes-pago.re-apartar');

        // Ordenes de Compra
        Route::get('ordenes-compra/exportar', [CostosOrdenCompraController::class, 'exportar'])->name('ordenes-compra.exportar');
        Route::resource('ordenes-compra', CostosOrdenCompraController::class)->only(['index', 'create', 'store', 'show', 'destroy'])->parameters(['ordenes-compra' => 'ordenCompra']);
        Route::post('ordenes-compra/{ordenCompra}/cancelar', [CostosOrdenCompraController::class, 'cancelar'])->name('ordenes-compra.cancelar');
        Route::post('ordenes-compra/{ordenCompra}/factura-contado', [CostosOrdenCompraController::class, 'subirFacturaContado'])->name('ordenes-compra.factura-contado');
        Route::get('ordenes-compra/{ordenCompra}/pdf-requisicion', [CostosOrdenCompraController::class, 'pdfRequisicion'])->name('ordenes-compra.pdf-requisicion');
        Route::get('ordenes-compra/{ordenCompra}/pdf-oc', [CostosOrdenCompraController::class, 'pdfOc'])->name('ordenes-compra.pdf-oc');
        Route::get('ordenes-compra/{ordenCompra}/pdf-contrarecibo/{factura}', [CostosOrdenCompraController::class, 'pdfContrarecibo'])->name('ordenes-compra.pdf-contrarecibo');

        // Facturas
        Route::get('facturas/reporte-semanal', [CostosFacturaAdminController::class, 'reporteSemanal'])->name('facturas.reporte-semanal');
        Route::get('facturas/reporte-semanal-proveedor', [CostosFacturaAdminController::class, 'reporteSemanalProveedor'])->name('facturas.reporte-semanal-proveedor');
        Route::resource('facturas', CostosFacturaAdminController::class)
            ->only(['index', 'show', 'create', 'store'])
            ->parameters(['facturas' => 'factura']);
        Route::post('ordenes-compra/{ordenCompra}/entregas', [CostosEntregaController::class, 'store'])->name('ordenes-compra.entregas.store');
        Route::post('facturas/{factura}/aprobar-costos', [CostosFacturaAdminController::class, 'aprobarCostos'])->name('facturas.aprobar-costos');
        Route::post('facturas/{factura}/aceptar-contabilidad', [CostosFacturaAdminController::class, 'aceptarContabilidad'])->name('facturas.aceptar-contabilidad');
        Route::post('facturas/{factura}/cancelar', [CostosFacturaAdminController::class, 'cancelar'])->name('facturas.cancelar');

        // Pagos
        Route::get('pagos/reporte', [CostosPagoController::class, 'reporte'])->name('pagos.reporte');
        Route::resource('pagos', CostosPagoController::class)->only(['index', 'show'])->parameters(['pagos' => 'pago']);
        Route::post('pagos/{pago}/programar', [CostosPagoController::class, 'programar'])->name('pagos.programar');
        Route::get('pagos/{pago}/parcializar', [CostosPagoController::class, 'showParcializar'])->name('pagos.parcializar.show');
        Route::post('pagos/{pago}/parcializar', [CostosPagoController::class, 'parcializar'])->name('pagos.parcializar');
        Route::post('pagos/{pago}/upload-comprobante', [CostosPagoController::class, 'uploadComprobante'])->name('pagos.upload-comprobante');
        Route::post('pagos/{pago}/cancelar', [CostosPagoController::class, 'cancelar'])->name('pagos.cancelar');

        // Firma del aprobador
        Route::middleware('can:aprobador-costos')->group(function () {
            Route::get('firma', [CostosFirmaController::class, 'edit'])->name('firma.edit');
            Route::post('firma', [CostosFirmaController::class, 'update'])->name('firma.update');
            Route::delete('firma', [CostosFirmaController::class, 'destroy'])->name('firma.destroy');
        });

        // Aprobaciones digitales
        Route::get('aprobaciones', [CostosAprobacionController::class, 'index'])->name('aprobaciones.index');
        Route::get('aprobaciones/bandeja/{usuario?}', [CostosAprobacionController::class, 'bandejaDe'])
            ->middleware('role:super-admin')
            ->name('aprobaciones.bandeja');
        Route::get('aprobaciones/{aprobacionSolicitud}', [CostosAprobacionController::class, 'show'])->name('aprobaciones.show');
        Route::post('aprobaciones/{aprobacionSolicitud}/aprobar', [CostosAprobacionController::class, 'aprobar'])->name('aprobaciones.aprobar');
        Route::post('aprobaciones/{aprobacionSolicitud}/rechazar', [CostosAprobacionController::class, 'rechazar'])->name('aprobaciones.rechazar');

        // Afectaciones presupuestales
        Route::resource('afectaciones', CostosAfectacionPresupuestalController::class)->parameters(['afectaciones' => 'afectacion']);
        Route::get('afectaciones/{afectacion}/pdf', [CostosAfectacionPresupuestalController::class, 'generarPdf'])->name('afectaciones.pdf');
        Route::post('afectaciones/{afectacion}/upload-firmado', [CostosAfectacionPresupuestalController::class, 'uploadFirmado'])->name('afectaciones.upload-firmado');
        Route::post('afectaciones/{afectacion}/cancelar', [CostosAfectacionPresupuestalController::class, 'cancelar'])->name('afectaciones.cancelar');

        // Presupuestos (proyecto / obra / partida)
        Route::get('presupuestos', [CostosPresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('obras-activas', [CostosPresupuestoController::class, 'obrasActivas'])->name('obras-activas.index');
        Route::get('presupuestos/reporte-pdf', [CostosPresupuestoController::class, 'generarReportePdf'])->name('presupuestos.reporte-pdf');
        Route::post('presupuestos/planta', [CostosPresupuestoController::class, 'storePlanta'])->name('presupuestos.planta.store');
        Route::post('presupuestos', [CostosPresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('presupuestos/{presupuesto}/edit', [CostosPresupuestoController::class, 'edit'])->name('presupuestos.edit');
        Route::put('presupuestos/{presupuesto}', [CostosPresupuestoController::class, 'update'])->name('presupuestos.update');
        Route::post('presupuestos/{presupuesto}/estado', [CostosPresupuestoController::class, 'cambiarEstado'])->name('presupuestos.estado');

        // Cuentas Internas
        Route::get('cuentas-internas', [CostosCuentaInternaController::class, 'index'])->name('cuentas-internas.index');
        Route::post('cuentas-internas/usuario', [CostosCuentaInternaController::class, 'crearUsuario'])->name('cuentas-internas.crear-usuario');
        Route::post('cuentas-internas', [CostosCuentaInternaController::class, 'store'])->name('cuentas-internas.store');
        Route::delete('cuentas-internas/{usuario}/{rol}', [CostosCuentaInternaController::class, 'destroy'])->name('cuentas-internas.destroy');

        Route::post('obra-rubros', [CostosObraRubroController::class, 'store'])->name('obra-rubros.store');
        Route::post('obra-rubros/todos', [CostosObraRubroController::class, 'storeAll'])->name('obra-rubros.store-all');
        Route::put('obra-rubros/{obraRubro}', [CostosObraRubroController::class, 'update'])->name('obra-rubros.update');
        Route::delete('obra-rubros/{obraRubro}', [CostosObraRubroController::class, 'destroy'])->name('obra-rubros.destroy');
    });

    // Cotización admin routes (catálogos globales)
    Route::prefix('cotiz')->name('cotiz.')->group(function () {
        Route::resource('insumos', CotizInsumoController::class)->parameters(['insumos' => 'insumo']);
        Route::resource('mermas', CotizMermaController::class)->parameters(['mermas' => 'merma']);
        Route::resource('factores', CotizFactorController::class)->parameters(['factores' => 'factor']);
        Route::resource('centros-costo', CotizCentroCostoController::class)->parameters(['centros-costo' => 'centroCosto']);
        Route::resource('categorias-tarjeta', CotizCategoriaTarjetaController::class)->parameters(['categorias-tarjeta' => 'categoriaTarjeta']);
        Route::resource('pintura-formulas', CotizPinturaFormulaController::class)->parameters(['pintura-formulas' => 'pinturaFormula']);
        Route::resource('kilos-reales-categorias', CotizKilosRealesCategoriaController::class)->parameters(['kilos-reales-categorias' => 'kilosRealesCategoria']);
        Route::resource('cuadrillas', CotizCuadrillaController::class)->parameters(['cuadrillas' => 'cuadrilla']);
        Route::resource('personal', CotizPersonalCategoriaController::class)->parameters(['personal' => 'personal']);
        Route::resource('fases-montaje', CotizFaseMontajeController::class)->parameters(['fases-montaje' => 'faseMontaje']);
        Route::resource('fletes-viaticos', CotizFleteViaticoCatalogoController::class)->parameters(['fletes-viaticos' => 'fleteViatico']);
        Route::resource('resumen-filas', CotizResumenFilaController::class)->parameters(['resumen-filas' => 'resumenFila']);

        // Obras + generadoras (Fase 1)
        Route::resource('obras', CotizObraController::class)->parameters(['obras' => 'obra'])->except(['show']);
        Route::get('obras/{obra}/generadoras', [CotizGeneradoraController::class, 'index'])->name('generadoras.index');
        Route::post('generadoras', [CotizGeneradoraController::class, 'store'])->name('generadoras.store');
        Route::post('generadoras/reorder', [CotizGeneradoraController::class, 'reorder'])->name('generadoras.reorder');
        Route::get('generadoras/{generadora}/edit', [CotizGeneradoraController::class, 'edit'])->name('generadoras.edit');
        Route::put('generadoras/{generadora}', [CotizGeneradoraController::class, 'update'])->name('generadoras.update');
        Route::delete('generadoras/{generadora}', [CotizGeneradoraController::class, 'destroy'])->name('generadoras.destroy');
        Route::post('generadoras/{generadora}/registros', [CotizGeneradoraRegistroController::class, 'store'])->name('generadoras.registros.store');
        Route::put('registros/{registro}', [CotizGeneradoraRegistroController::class, 'update'])->name('registros.update');
        Route::put('registros/{registro}/insumo-override', [CotizGeneradoraController::class, 'insumoOverride'])->name('registros.insumo-override');
        Route::delete('registros/{registro}', [CotizGeneradoraRegistroController::class, 'destroy'])->name('registros.destroy');
        Route::patch('registros/{registro}/validar', [CotizGeneradoraRegistroController::class, 'validar'])->name('registros.validar');
        Route::post('lock/{type}/{id}', [CotizEditLockController::class, 'lock'])->name('lock');
        Route::post('unlock/{type}/{id}', [CotizEditLockController::class, 'unlock'])->name('unlock');

        // Overrides de catálogo por obra (Fase 2)
        Route::get('obras/{obra}/catalogo', [CotizCatalogoObraController::class, 'index'])->name('obras.catalogo.index');
        Route::put('obras/{obra}/catalogo/insumos/{insumo}', [CotizCatalogoObraController::class, 'updateInsumo'])->name('obras.catalogo.insumos.update');
        Route::delete('obras/{obra}/catalogo/insumos/{insumo}', [CotizCatalogoObraController::class, 'destroyInsumo'])->name('obras.catalogo.insumos.destroy');
        Route::put('obras/{obra}/catalogo/factores/{factor}', [CotizCatalogoObraController::class, 'updateFactor'])->name('obras.catalogo.factores.update');
        Route::delete('obras/{obra}/catalogo/factores/{factor}', [CotizCatalogoObraController::class, 'destroyFactor'])->name('obras.catalogo.factores.destroy');

        // Tarjetas (Fase 3a): CRUD por obra + vínculo con generadoras
        Route::get('obras/{obra}/tarjetas', [CotizTarjetaController::class, 'index'])->name('tarjetas.index');
        Route::post('tarjetas', [CotizTarjetaController::class, 'store'])->name('tarjetas.store');
        Route::get('tarjetas/{tarjeta}/edit', [CotizTarjetaController::class, 'edit'])->name('tarjetas.edit');
        Route::put('tarjetas/{tarjeta}', [CotizTarjetaController::class, 'update'])->name('tarjetas.update');
        Route::delete('tarjetas/{tarjeta}', [CotizTarjetaController::class, 'destroy'])->name('tarjetas.destroy');
        Route::post('tarjetas/{tarjeta}/generadoras', [CotizTarjetaController::class, 'vincularGeneradora'])->name('tarjetas.generadoras.vincular');
        Route::delete('tarjetas/{tarjeta}/generadoras/{generadora}', [CotizTarjetaController::class, 'desvincularGeneradora'])->name('tarjetas.generadoras.desvincular');

        // Tarjetas — subrecursos de la grilla densa (Fase 3c-2)
        Route::post('tarjetas/validar-formula', [CotizTarjetaDetalleController::class, 'validarFormula'])->name('tarjetas.validar-formula');
        Route::post('tarjetas/{tarjeta}/registros-manual', [CotizTarjetaDetalleController::class, 'registroStore'])->name('tarjetas.registros.store');
        Route::put('tarjeta-registros/{tarjetaRegistro}', [CotizTarjetaDetalleController::class, 'registroUpdate'])->name('tarjetas.registros.update');
        Route::delete('tarjeta-registros/{tarjetaRegistro}', [CotizTarjetaDetalleController::class, 'registroDestroy'])->name('tarjetas.registros.destroy');
        Route::put('tarjetas/{tarjeta}/precios/{insumo}', [CotizTarjetaDetalleController::class, 'precioUpdate'])->name('tarjetas.precios.update');
        Route::post('tarjetas/{tarjeta}/factores', [CotizTarjetaDetalleController::class, 'factorStore'])->name('tarjetas.factores.store');
        Route::put('tarjeta-factores/{tarjetaFactor}', [CotizTarjetaDetalleController::class, 'factorUpdate'])->name('tarjetas.factores.update');
        Route::delete('tarjeta-factores/{tarjetaFactor}', [CotizTarjetaDetalleController::class, 'factorDestroy'])->name('tarjetas.factores.destroy');
        Route::post('tarjetas/{tarjeta}/estructuras', [CotizTarjetaDetalleController::class, 'estructuraStore'])->name('tarjetas.estructuras.store');
        Route::put('tarjeta-estructuras/{tarjetaEstructura}', [CotizTarjetaDetalleController::class, 'estructuraUpdate'])->name('tarjetas.estructuras.update');
        Route::delete('tarjeta-estructuras/{tarjetaEstructura}', [CotizTarjetaDetalleController::class, 'estructuraDestroy'])->name('tarjetas.estructuras.destroy');
        Route::post('tarjetas/{tarjeta}/kr-categorias', [CotizTarjetaDetalleController::class, 'krCategoriaStore'])->name('tarjetas.kr-categorias.store');
        Route::put('tarjeta-kr-categorias/{categoriaKilos}', [CotizTarjetaDetalleController::class, 'krCategoriaUpdate'])->name('tarjetas.kr-categorias.update');
        Route::delete('tarjeta-kr-categorias/{categoriaKilos}', [CotizTarjetaDetalleController::class, 'krCategoriaDestroy'])->name('tarjetas.kr-categorias.destroy');
        Route::put('kr-categorias-catalogo/{categoria}/tipo', [CotizTarjetaDetalleController::class, 'krCategoriaTipo'])->name('tarjetas.kr-categorias.tipo');
        Route::put('tarjetas/{tarjeta}/kr-celdas', [CotizTarjetaDetalleController::class, 'krCeldaUpsert'])->name('tarjetas.kr-celdas.upsert');
        Route::put('tarjetas/{tarjeta}/registros-grupo', [CotizTarjetaDetalleController::class, 'registroGrupo'])->name('tarjetas.registros.grupo');
        Route::delete('tarjetas/{tarjeta}/registros-grupo', [CotizTarjetaDetalleController::class, 'registroGrupoDestroy'])->name('tarjetas.registros.grupo-destroy');
        Route::post('tarjetas/{tarjeta}/validar-todas', [CotizTarjetaDetalleController::class, 'validarTodas'])->name('tarjetas.validar-todas');
        Route::post('tarjetas/{tarjeta}/aplicar-sugerido', [CotizTarjetaDetalleController::class, 'aplicarSugerido'])->name('tarjetas.aplicar-sugerido');
        Route::post('tarjetas/{tarjeta}/generadoras/{generadora}/resincronizar', [CotizTarjetaDetalleController::class, 'resincronizarGeneradora'])->name('tarjetas.generadoras.resincronizar');

        // Análisis MO / Montaje + Fletes y Viáticos (Fase 4)
        Route::get('obras/{obra}/analisis-mo', [CotizAnalisisMoController::class, 'index'])->name('analisis-mo.index');

        // Secciones / zonas de montaje
        Route::post('obras/{obra}/secciones', [CotizSeccionMontajeController::class, 'store'])->name('secciones.store');
        Route::get('secciones/{seccion}/edit', [CotizSeccionMontajeController::class, 'edit'])->name('secciones.edit');
        Route::put('secciones/{seccion}', [CotizSeccionMontajeController::class, 'update'])->name('secciones.update');
        Route::delete('secciones/{seccion}', [CotizSeccionMontajeController::class, 'destroy'])->name('secciones.destroy');
        Route::put('secciones/{seccion}/personal', [CotizSeccionMontajeController::class, 'personalUpsert'])->name('secciones.personal.upsert');
        Route::post('secciones/{seccion}/rendimientos', [CotizSeccionMontajeController::class, 'rendimientoStore'])->name('secciones.rendimientos.store');
        Route::put('seccion-rendimientos/{rendimiento}', [CotizSeccionMontajeController::class, 'rendimientoUpdate'])->name('secciones.rendimientos.update');
        Route::delete('seccion-rendimientos/{rendimiento}', [CotizSeccionMontajeController::class, 'rendimientoDestroy'])->name('secciones.rendimientos.destroy');

        // Cuadrilla global de montaje
        Route::put('obras/{obra}/cuadrilla-global', [CotizCuadrillaGlobalController::class, 'upsert'])->name('cuadrilla-global.upsert');
        Route::put('obras/{obra}/num-grupos', [CotizCuadrillaGlobalController::class, 'updateGrupos'])->name('cuadrilla-global.num-grupos');

        // Fletes y viáticos por obra (nombres bajo `obras.` para no chocar con el catálogo global `fletes-viaticos.*`)
        Route::post('obras/{obra}/fletes-viaticos', [CotizFleteViaticoObraController::class, 'store'])->name('obras.fletes-viaticos.store');
        Route::post('obras/{obra}/fletes-viaticos/importar', [CotizFleteViaticoObraController::class, 'importarPlantilla'])->name('obras.fletes-viaticos.importar');
        Route::post('obras/{obra}/fletes-viaticos/aplicar-formulas', [CotizFleteViaticoObraController::class, 'aplicarFormulas'])->name('obras.fletes-viaticos.aplicar-formulas');
        Route::post('obras/{obra}/fletes-viaticos/recalcular', [CotizFleteViaticoObraController::class, 'recalcular'])->name('obras.fletes-viaticos.recalcular');
        Route::put('obra-fletes-viaticos/{fleteViatico}', [CotizFleteViaticoObraController::class, 'update'])->name('obras.fletes-viaticos.update');
        Route::delete('obra-fletes-viaticos/{fleteViatico}', [CotizFleteViaticoObraController::class, 'destroy'])->name('obras.fletes-viaticos.destroy');

        // Fletes estándar por obra
        Route::post('obras/{obra}/fletes-estandar', [CotizFleteEstandarController::class, 'store'])->name('obras.fletes-estandar.store');
        Route::put('obra-fletes-estandar/{fleteEstandar}', [CotizFleteEstandarController::class, 'update'])->name('obras.fletes-estandar.update');
        Route::delete('obra-fletes-estandar/{fleteEstandar}', [CotizFleteEstandarController::class, 'destroy'])->name('obras.fletes-estandar.destroy');

        // Resumen de Proyecto (Fase 5)
        Route::get('obras/{obra}/resumen', [CotizResumenController::class, 'index'])->name('resumen.index');
        Route::put('obras/{obra}/resumen/celda', [CotizResumenController::class, 'updateCelda'])->name('resumen.celda');
        Route::put('obras/{obra}/resumen/coeficiente', [CotizResumenController::class, 'updateCoeficiente'])->name('resumen.coeficiente');
        Route::put('resumen-columnas/{columna}/sueldo', [CotizResumenController::class, 'updateColumnaSueldo'])->name('resumen.columna-sueldo');

        // Carátula de cotización (Fase 5)
        Route::get('obras/{obra}/caratula', [CotizCaratulaController::class, 'index'])->name('caratula.index');
        Route::get('obras/{obra}/caratula/pdf', [CotizCaratulaController::class, 'pdf'])->name('caratula.pdf');

        // Versiones (Fase 5.5, modelo lineal)
        Route::get('obras/{obra}/versiones', [CotizVersionController::class, 'index'])->name('versiones.index');
        Route::post('obras/{obra}/versiones', [CotizVersionController::class, 'store'])->name('versiones.store');
        Route::post('obras/{obra}/versiones/{version}/restaurar', [CotizVersionController::class, 'restaurar'])->name('versiones.restaurar');
        Route::delete('obras/{obra}/versiones/{version}', [CotizVersionController::class, 'destroy'])->name('versiones.destroy');
    });

    // Cobranza admin routes
    Route::prefix('cob')->name('cob.')->group(function () {
        Route::get('dashboard', [CobDashboardController::class, 'index'])->name('dashboard.index');

        // Reporte semanal de cobranza (calculado en vivo; solo notas se guardan)
        Route::get('reportes', [CobReporteCobranzaController::class, 'index'])->name('reportes.index');
        Route::get('reportes/{anio}/{semana}', [CobReporteCobranzaController::class, 'show'])->whereNumber(['anio', 'semana'])->name('reportes.show');
        Route::put('reportes/{anio}/{semana}/notas', [CobReporteCobranzaController::class, 'updateNotas'])->whereNumber(['anio', 'semana'])->name('reportes.notas');
        Route::get('reportes/{anio}/{semana}/pdf', [CobReporteCobranzaController::class, 'pdf'])->whereNumber(['anio', 'semana'])->name('reportes.pdf');

        Route::resource('clientes', CobClienteController::class)->parameters(['clientes' => 'cliente']);
        Route::post('clientes/{cliente}/contactos', [CobContactoController::class, 'store'])->name('clientes.contactos.store');
        Route::put('clientes/{cliente}/contactos/{contacto}', [CobContactoController::class, 'update'])->name('clientes.contactos.update');
        Route::delete('clientes/{cliente}/contactos/{contacto}', [CobContactoController::class, 'destroy'])->name('clientes.contactos.destroy');

        Route::resource('tipos-retenciones', CobTipoRetencionController::class)->parameters(['tipos-retenciones' => 'tipoRetencion']);

        // Proyectos (entidad comercial que agrupa obras)
        Route::get('proyectos', [CobProyectoController::class, 'index'])->name('proyectos.index');
        Route::get('proyectos/create', [CobProyectoController::class, 'create'])->name('proyectos.create');
        Route::post('proyectos', [CobProyectoController::class, 'store'])->name('proyectos.store');
        Route::get('proyectos/{proyecto}', [CobProyectoController::class, 'show'])->name('proyectos.show');
        Route::put('proyectos/{proyecto}', [CobProyectoController::class, 'update'])->name('proyectos.update');
        Route::put('proyectos/{proyecto}/plan-cobro', [CobProyectoController::class, 'guardarPlanCobro'])->name('proyectos.plan-cobro');
        Route::put('proyectos/{proyecto}/planeacion', [CobProyectoController::class, 'guardarPlaneacion'])->name('proyectos.planeacion');
        Route::post('proyectos/{proyecto}/etapas', [CobProyectoController::class, 'storeEtapa'])->name('proyectos.etapas.store');
        Route::delete('proyectos/{proyecto}/etapas/{etapa}', [CobProyectoController::class, 'destroyEtapa'])->name('proyectos.etapas.destroy');

        // Obras del proyecto (alta/edición a nivel proyecto)
        Route::get('proyectos/{proyecto}/obras/create', [CobObraCobranzaController::class, 'createObra'])->name('proyectos.obras.create');
        Route::post('proyectos/{proyecto}/obras', [CobObraCobranzaController::class, 'storeObra'])->name('proyectos.obras.store');
        Route::get('proyectos/{proyecto}/obras/{obra}/edit', [CobObraCobranzaController::class, 'editObra'])->name('proyectos.obras.edit');
        Route::put('proyectos/{proyecto}/obras/{obra}', [CobObraCobranzaController::class, 'updateObra'])->name('proyectos.obras.update');

        // Obras cobranza
        Route::get('obras', [CobObraCobranzaController::class, 'index'])->name('obras.index');
        Route::get('obras/reporte-pdf', [CobObraCobranzaController::class, 'reportePdf'])->name('obras.reporte-pdf');
        Route::get('obras/{obra}', [CobObraCobranzaController::class, 'show'])->name('obras.show');
        Route::get('obras/{obra}/estado-cuenta-pdf', [CobObraCobranzaController::class, 'estadoCuentaPdf'])->name('obras.estado-cuenta-pdf');
        Route::put('obras/{obra}/financial', [CobObraCobranzaController::class, 'updateFinancial'])->name('obras.update-financial');
        Route::put('obras/{obra}/estado', [CobObraCobranzaController::class, 'cambiarEstado'])->name('obras.cambiar-estado');

        // Sub-recursos de obra
        Route::get('obras/{obra}/partidas/create', [CobPartidaController::class, 'create'])->name('obras.partidas.create');
        Route::post('obras/{obra}/partidas', [CobPartidaController::class, 'store'])->name('obras.partidas.store');
        Route::get('obras/{obra}/partidas/{partida}/edit', [CobPartidaController::class, 'edit'])->name('obras.partidas.edit');
        Route::put('obras/{obra}/partidas/{partida}', [CobPartidaController::class, 'update'])->name('obras.partidas.update');
        Route::delete('obras/{obra}/partidas/{partida}', [CobPartidaController::class, 'destroy'])->name('obras.partidas.destroy');

        // Estimaciones: ancladas al proyecto; nivel global/obra/partida en el request.
        Route::get('proyectos/{proyecto}/estimaciones/create', [CobEstimacionController::class, 'create'])->name('proyectos.estimaciones.create');
        Route::post('proyectos/{proyecto}/estimaciones', [CobEstimacionController::class, 'store'])->name('proyectos.estimaciones.store');
        Route::get('proyectos/{proyecto}/estimaciones/{estimacion}/edit', [CobEstimacionController::class, 'edit'])->name('proyectos.estimaciones.edit');
        Route::put('proyectos/{proyecto}/estimaciones/{estimacion}', [CobEstimacionController::class, 'update'])->name('proyectos.estimaciones.update');
        Route::delete('proyectos/{proyecto}/estimaciones/{estimacion}', [CobEstimacionController::class, 'destroy'])->name('proyectos.estimaciones.destroy');
        Route::post('proyectos/{proyecto}/estimaciones/{estimacion}/cambiar-estado', [CobEstimacionController::class, 'cambiarEstado'])->name('proyectos.estimaciones.cambiar-estado');
        Route::post('proyectos/{proyecto}/estimaciones/{estimacion}/pagos', [CobEstimacionPagoController::class, 'store'])->name('proyectos.estimaciones.pagos.store');

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

        Route::get('proyectos/{proyecto}/comparativos/create', [CobComparativoController::class, 'create'])->name('proyectos.comparativos.create');
        Route::post('proyectos/{proyecto}/comparativos', [CobComparativoController::class, 'store'])->name('proyectos.comparativos.store');
        Route::get('proyectos/{proyecto}/comparativos/{comparativo}/edit', [CobComparativoController::class, 'edit'])->name('proyectos.comparativos.edit');
        Route::put('proyectos/{proyecto}/comparativos/{comparativo}', [CobComparativoController::class, 'update'])->name('proyectos.comparativos.update');
        Route::delete('proyectos/{proyecto}/comparativos/{comparativo}', [CobComparativoController::class, 'destroy'])->name('proyectos.comparativos.destroy');

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

        // Catálogo de secciones de documentación
        Route::get('documento-secciones', [CobDocumentoSeccionController::class, 'index'])->name('documento-secciones.index');
        Route::post('documento-secciones', [CobDocumentoSeccionController::class, 'store'])->name('documento-secciones.store');
        Route::put('documento-secciones/{documentoSeccion}', [CobDocumentoSeccionController::class, 'update'])->name('documento-secciones.update');
        Route::delete('documento-secciones/{documentoSeccion}', [CobDocumentoSeccionController::class, 'destroy'])->name('documento-secciones.destroy');

        // Documentación por proyecto (secciones → subcarpetas → archivos)
        Route::post('proyectos/{proyecto}/documentos/carpetas', [CobProyectoDocumentoController::class, 'carpetaStore'])->name('proyectos.documentos.carpetas.store');
        Route::put('proyectos/{proyecto}/documentos/carpetas/{carpeta}', [CobProyectoDocumentoController::class, 'carpetaUpdate'])->name('proyectos.documentos.carpetas.update');
        Route::delete('proyectos/{proyecto}/documentos/carpetas/{carpeta}', [CobProyectoDocumentoController::class, 'carpetaDestroy'])->name('proyectos.documentos.carpetas.destroy');
        Route::post('proyectos/{proyecto}/documentos/archivos', [CobProyectoDocumentoController::class, 'archivoStore'])->name('proyectos.documentos.archivos.store');
        Route::delete('proyectos/{proyecto}/documentos/archivos/{archivo}', [CobProyectoDocumentoController::class, 'archivoDestroy'])->name('proyectos.documentos.archivos.destroy');
        Route::get('documentos/archivos/{archivo}/descargar', [CobProyectoDocumentoController::class, 'archivoDownload'])->name('documentos.archivos.descargar');
        Route::get('documentos/archivos/{archivo}/stream', [CobProyectoDocumentoController::class, 'archivoStream'])->name('documentos.archivos.stream');

        // Estatus (pendiente/completado) y visibilidad de cada sección del expediente por proyecto.
        Route::put('proyectos/{proyecto}/secciones/{documentoSeccion}/estatus', [CobProyectoDocumentoController::class, 'seccionEstatus'])->name('proyectos.secciones.estatus');
        Route::put('proyectos/{proyecto}/secciones/{documentoSeccion}/visibilidad', [CobProyectoDocumentoController::class, 'seccionVisibilidad'])->name('proyectos.secciones.visibilidad');
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
        Route::post('puestos/{puesto}/plantilla-onboarding', [RhPuestoController::class, 'storePlantillaOnboarding'])->name('puestos.plantilla-onboarding.store');
        Route::put('puestos/{puesto}/plantilla-onboarding/{plantilla}', [RhPuestoController::class, 'updatePlantillaOnboarding'])->name('puestos.plantilla-onboarding.update');
        Route::delete('puestos/{puesto}/plantilla-onboarding/{plantilla}', [RhPuestoController::class, 'destroyPlantillaOnboarding'])->name('puestos.plantilla-onboarding.destroy');

        // Personas
        Route::resource('personas', RhPersonaController::class)->parameters(['personas' => 'persona']);
        Route::post('personas/{persona}/documentos', [RhPersonaController::class, 'storeDocumento'])->name('personas.documentos.store');
        Route::delete('personas/{persona}/documentos/{documento}', [RhPersonaController::class, 'destroyDocumento'])->name('personas.documentos.destroy');

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
