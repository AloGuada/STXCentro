<?php

use App\Http\Controllers\Admin\Alm\ActivoController as AlmActivoController;
use App\Http\Controllers\Admin\Alm\AjusteController as AlmAjusteController;
use App\Http\Controllers\Admin\Alm\AlmacenController as AlmAlmacenController;
use App\Http\Controllers\Admin\Alm\AreaController as AlmAreaController;
use App\Http\Controllers\Admin\Alm\ArticuloController as AlmArticuloController;
use App\Http\Controllers\Admin\Alm\AsignacionController as AlmAsignacionController;
use App\Http\Controllers\Admin\Alm\ConteoController as AlmConteoController;
use App\Http\Controllers\Admin\Alm\DevolucionController as AlmDevolucionController;
use App\Http\Controllers\Admin\Alm\EntradaController as AlmEntradaController;
use App\Http\Controllers\Admin\Alm\ExistenciaController as AlmExistenciaController;
use App\Http\Controllers\Admin\Alm\KardexController as AlmKardexController;
use App\Http\Controllers\Admin\Alm\PedidoController as AlmPedidoController;
use App\Http\Controllers\Admin\Alm\PrestamoController as AlmPrestamoController;
use App\Http\Controllers\Admin\Alm\SalidaController as AlmSalidaController;
use App\Http\Controllers\Admin\Alm\TransferenciaController as AlmTransferenciaController;
use App\Http\Controllers\Admin\Alm\UbicacionController as AlmUbicacionController;
use App\Http\Controllers\Admin\Alm\VistasController as AlmVistasController;
use App\Http\Controllers\Admin\BadgeConfigController;
use App\Http\Controllers\Admin\BancoController;
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
use App\Http\Controllers\Admin\Cob\IcsoeController as CobIcsoeController;
use App\Http\Controllers\Admin\Cob\IcsoeMesController as CobIcsoeMesController;
use App\Http\Controllers\Admin\Cob\IcsoeSbcAnioController as CobIcsoeSbcAnioController;
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
use App\Http\Controllers\Admin\Costos\ConfirmacionController as CostosConfirmacionController;
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
use App\Http\Controllers\Admin\Drive\DriveCarpetaAccesoController;
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
use App\Http\Controllers\Admin\Prod\AsistenciaController as ProdAsistenciaController;
use App\Http\Controllers\Admin\Prod\CatalogoController as ProdCatalogoController;
use App\Http\Controllers\Admin\Prod\CategoriaController as ProdCategoriaController;
use App\Http\Controllers\Admin\Prod\CategoriaEmpleadoController as ProdCategoriaEmpleadoController;
use App\Http\Controllers\Admin\Prod\ConceptoController as ProdConceptoController;
use App\Http\Controllers\Admin\Prod\ConfiguracionController as ProdConfiguracionController;
use App\Http\Controllers\Admin\Prod\DestajoController as ProdDestajoController;
use App\Http\Controllers\Admin\Prod\GrupoPrecioConceptoController as ProdGrupoPrecioConceptoController;
use App\Http\Controllers\Admin\Prod\GrupoPrecioController as ProdGrupoPrecioController;
use App\Http\Controllers\Admin\Prod\GrupoTrabajoController as ProdGrupoTrabajoController;
use App\Http\Controllers\Admin\Prod\ModeloController as ProdModeloController;
use App\Http\Controllers\Admin\Prod\ObraProcesoController as ProdObraProcesoController;
use App\Http\Controllers\Admin\Prod\PagoExtraController as ProdPagoExtraController;
use App\Http\Controllers\Admin\Prod\ProcesoController as ProdProcesoController;
use App\Http\Controllers\Admin\Prod\RegistroController as ProdRegistroController;
use App\Http\Controllers\Admin\Prod\TipoPagoExtraController as ProdTipoPagoExtraController;
use App\Http\Controllers\Admin\Prod\UbicacionController as ProdUbicacionController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\Qal\AccesorioController as QalAccesorioController;
use App\Http\Controllers\Admin\Qal\CatalogoController as QalCatalogoController;
use App\Http\Controllers\Admin\Qal\DashboardController as QalDashboardController;
use App\Http\Controllers\Admin\Qal\DefectoController as QalDefectoController;
use App\Http\Controllers\Admin\Qal\EquipoController as QalEquipoController;
use App\Http\Controllers\Admin\Qal\IncidenciasController as QalIncidenciasController;
use App\Http\Controllers\Admin\Qal\InspeccionController as QalInspeccionController;
use App\Http\Controllers\Admin\Qal\LaboratorioController as QalLaboratorioController;
use App\Http\Controllers\Admin\Qal\ModeloMarcaController as QalModeloMarcaController;
use App\Http\Controllers\Admin\Qal\OperadorController as QalOperadorController;
use App\Http\Controllers\Admin\Qal\PiezaController as QalPiezaController;
use App\Http\Controllers\Admin\Qal\PndController as QalPndController;
use App\Http\Controllers\Admin\Qal\ProgramacionController as QalProgramacionController;
use App\Http\Controllers\Admin\Qal\RegistroController as QalRegistroController;
use App\Http\Controllers\Admin\Qal\ReporteSemanalController as QalReporteSemanalController;
use App\Http\Controllers\Admin\Qal\ResponsableController as QalResponsableController;
use App\Http\Controllers\Admin\Qal\SoldadorController as QalSoldadorController;
use App\Http\Controllers\Admin\Qal\SupervisorPinturaController as QalSupervisorPinturaController;
use App\Http\Controllers\Admin\Qal\TipoPiezaController as QalTipoPiezaController;
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
    Route::post('proveedores/{proveedor}/aprobar', [ProveedorController::class, 'aprobar'])->name('proveedores.aprobar');
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);
    Route::resource('regimenes-fiscales', RegimenFiscalController::class)
        ->parameters(['regimenes-fiscales' => 'regimenFiscal'])
        ->except(['show']);
    Route::resource('bancos', BancoController::class)->except(['show']);
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
        // Catalogos de piezas (versionados, uno vigente por obra)
        Route::post('catalogos/{catalogo}/nueva-version', [ProdCatalogoController::class, 'nuevaVersion'])->name('catalogos.nueva-version');
        Route::get('catalogos/{catalogo}/comparar/{contra}', [ProdCatalogoController::class, 'comparar'])->name('catalogos.comparar');
        Route::post('catalogos/{catalogo}/import-csv', [ProdConceptoController::class, 'importCsv'])->name('catalogos.import-csv');

        // El modelo 3D de la obra: su IFC convertido en marcas con sus
        // cordones. Es una opcion del catalogo porque aqui esta quien tiene el
        // IFC; Calidad reporta las juntas sobre esos cordones.
        Route::get('catalogos/{catalogo}/modelos', [ProdModeloController::class, 'index'])
            ->middleware('permission:qal.modelos.ver')
            ->name('catalogos.modelos');
        Route::prefix('modelos')->name('modelos.')->group(function () {
            Route::post('/', [ProdModeloController::class, 'store'])
                ->middleware('permission:qal.modelos.crear')
                ->name('store');
            Route::middleware('permission:qal.modelos.ver')->group(function () {
                Route::get('{modelo}', [ProdModeloController::class, 'show'])
                    ->whereNumber('modelo')
                    ->name('show');
                Route::get('{modelo}/estado', [ProdModeloController::class, 'estado'])
                    ->whereNumber('modelo')
                    ->name('estado');
            });
            Route::middleware('permission:qal.modelos.crear')->group(function () {
                Route::post('{modelo}/reprocesar', [ProdModeloController::class, 'reprocesar'])
                    ->name('reprocesar');
                Route::post('{modelo}/resolver-marcas', [ProdModeloController::class, 'resolverMarcas'])
                    ->name('resolver-marcas');
            });
            Route::delete('{modelo}', [ProdModeloController::class, 'destroy'])
                ->middleware('permission:qal.modelos.eliminar')
                ->name('destroy');
        });
        Route::resource('catalogos', ProdCatalogoController::class)
            ->parameters(['catalogos' => 'catalogo'])
            ->except(['create', 'edit']);

        // Procesos que se pagan como destajo y sus eventos del export de planta
        Route::resource('procesos', ProdProcesoController::class)->parameters(['procesos' => 'proceso'])->except(['show']);
        Route::put('obras/{obra}/procesos', [ProdObraProcesoController::class, 'update'])->name('obras.procesos.update');

        Route::get('conceptos/layout', [ProdConceptoController::class, 'descargarLayout'])->name('conceptos.layout');
        Route::resource('conceptos', ProdConceptoController::class)
            ->parameters(['conceptos' => 'concepto'])
            ->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::get('grupo-precios/obra/{obra}', [ProdGrupoPrecioController::class, 'showByObra'])->name('grupo-precios.show-by-obra');
        Route::resource('grupo-precios', ProdGrupoPrecioController::class)->parameters(['grupo-precios' => 'grupoPrecio'])->except(['show']);
        Route::post('grupo-precios/{grupoPrecio}/assign-conceptos', [ProdGrupoPrecioController::class, 'assignConceptos'])->name('grupo-precios.assign-conceptos');

        // Pivot concepto-grupo precio
        Route::post('grupo-precio-conceptos', [ProdGrupoPrecioConceptoController::class, 'store'])->name('grupo-precio-conceptos.store');
        Route::delete('grupo-precio-conceptos/{grupoPrecioConcepto}', [ProdGrupoPrecioConceptoController::class, 'destroy'])->name('grupo-precio-conceptos.destroy');

        // Grupos de trabajo
        Route::resource('grupos-trabajo', ProdGrupoTrabajoController::class)->parameters(['grupos-trabajo' => 'grupoTrabajo']);
        Route::post('grupos-trabajo/{grupoTrabajo}/empleados', [ProdGrupoTrabajoController::class, 'storeEmpleado'])->name('grupos-trabajo.empleados.store');
        Route::patch('grupos-trabajo/{grupoTrabajo}/empleados/{empleado}', [ProdGrupoTrabajoController::class, 'updateEmpleado'])->name('grupos-trabajo.empleados.update');
        Route::delete('grupos-trabajo/{grupoTrabajo}/empleados/{empleado}', [ProdGrupoTrabajoController::class, 'destroyEmpleado'])->name('grupos-trabajo.empleados.destroy');

        // Catalogos
        Route::resource('tipos-pago-extra', ProdTipoPagoExtraController::class)->parameters(['tipos-pago-extra' => 'tipoPagoExtra']);
        Route::resource('categorias', ProdCategoriaController::class)->parameters(['categorias' => 'categoria'])->except(['show']);
        Route::resource('ubicaciones', ProdUbicacionController::class)->parameters(['ubicaciones' => 'ubicacion'])->except(['show']);
        Route::resource('categorias-empleado', ProdCategoriaEmpleadoController::class)
            ->parameters(['categorias-empleado' => 'categoriaEmpleado'])
            ->except(['show']);

        // Configuracion del modulo (salario minimo diario)
        Route::get('configuracion', [ProdConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('configuracion', [ProdConfiguracionController::class, 'update'])->name('configuracion.update');

        // El catalogo baja en tres tiempos —obra, marca, QR— y estos son los dos
        // escalones de abajo. Los comparten la captura del destajo y el catalogo:
        // ninguna pantalla puede darse el lujo de traerse la obra entera.
        Route::get('obras/{obra}/marcas', [ProdConceptoController::class, 'marcasDeObra'])->name('obras.marcas');
        Route::get('marcas/{concepto}/piezas', [ProdConceptoController::class, 'piezas'])->name('marcas.piezas');

        // Destajos (semanal) y liquidaciones
        Route::resource('destajos', ProdDestajoController::class)->except(['edit', 'update'])->parameters(['destajos' => 'destajo']);
        Route::post('destajos/{destajo}/cerrar', [ProdDestajoController::class, 'cerrar'])->name('destajos.cerrar');
        Route::get('destajos/{destajo}/orden-pago', [ProdDestajoController::class, 'ordenPagoPdf'])->name('destajos.orden-pago');
        Route::get('destajos/{destajo}/asistencia', [ProdAsistenciaController::class, 'show'])->name('destajos.asistencia');
        Route::post('destajos/{destajo}/asistencia', [ProdAsistenciaController::class, 'store'])->name('destajos.asistencia.store');

        // Produccion y pagos extra dentro del destajo
        Route::post('destajos/{destajo}/registros', [ProdRegistroController::class, 'store'])->name('destajos.registros.store');
        // El import va en dos pasos: analizar devuelve lo que pasaria y no toca
        // la base; import-csv es el que escribe, ya con el usuario enterado.
        Route::post('destajos/{destajo}/registros/analizar-csv', [ProdRegistroController::class, 'analizarCsv'])->name('destajos.registros.analizar-csv');
        Route::post('destajos/{destajo}/registros/import-csv', [ProdRegistroController::class, 'importCsv'])->name('destajos.registros.import-csv');
        Route::delete('destajos/{destajo}/registros/{registro}', [ProdRegistroController::class, 'destroy'])->name('destajos.registros.destroy');
        Route::post('destajos/{destajo}/pagos-extra', [ProdPagoExtraController::class, 'store'])->name('destajos.pagos-extra.store');
        Route::delete('destajos/{destajo}/pagos-extra/{pagoExtra}', [ProdPagoExtraController::class, 'destroy'])->name('destajos.pagos-extra.destroy');
    });

    // Almacen admin routes
    Route::prefix('almacen')->name('alm.')->group(function () {
        // Catalogo de almacenes: la base del modulo, sin el no hay movimientos
        Route::resource('almacenes', AlmAlmacenController::class)
            ->parameters(['almacenes' => 'almacen'])
            ->except(['show'])
            ->middlewareFor(['index'], 'permission:alm.almacenes.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.almacenes.crear')
            ->middlewareFor(['edit', 'update'], 'permission:alm.almacenes.editar')
            ->middlewareFor(['destroy'], 'permission:alm.almacenes.eliminar');

        // Catalogo de areas: clasifica el articulo. Una sola pantalla, porque
        // es una lista de un campo. Sin destroy: aqui nada se borra, se
        // desactiva, para no dejar articulos apuntando a lo que ya no existe.
        Route::get('areas', [AlmAreaController::class, 'index'])
            ->middleware('permission:alm.areas.ver')
            ->name('areas.index');
        Route::post('areas', [AlmAreaController::class, 'store'])
            ->middleware('permission:alm.areas.crear')
            ->name('areas.store');
        Route::put('areas/{area}', [AlmAreaController::class, 'update'])
            ->whereNumber('area')
            ->middleware('permission:alm.areas.editar')
            ->name('areas.update');
        Route::patch('areas/{area}/toggle', [AlmAreaController::class, 'toggle'])
            ->whereNumber('area')
            ->middleware('permission:alm.areas.editar')
            ->name('areas.toggle');

        // Maquetas: pantallas sin backend todavia, dibujadas con datos de
        // ejemplo para revisar diseno y flujo. Se van reemplazando por su
        // controlador real conforme cada una se construya. Cada una ya va
        // detras de su permiso definitivo: el modulo mueve existencias de
        // forma irreversible, asi que reciclar un solo permiso no alcanza.
        // Existencias y kardex: solo lectura. Corregir un movimiento es capturar
        // el contrario, no borrar el renglon.
        Route::get('existencias', [AlmExistenciaController::class, 'index'])
            ->middleware('permission:alm.existencias.ver')
            ->name('existencias.index');
        // El mismo inventario filtrado, a Excel. Va antes de las rutas con
        // {existencia} para que «exportar» no se lea como un id.
        Route::get('existencias/exportar', [AlmExistenciaController::class, 'exportar'])
            ->middleware('permission:alm.existencias.ver')
            ->name('existencias.exportar');
        Route::get('kardex', [AlmKardexController::class, 'index'])
            ->middleware('permission:alm.kardex.ver')
            ->name('kardex.index');
        // Reasignar material entre obras. Sin pantalla propia: es el modal del
        // desglose de Existencias, porque repartir se decide viendo el saldo.
        Route::post('asignaciones/reasignar', [AlmAsignacionController::class, 'reasignar'])
            ->middleware('permission:alm.asignaciones.reasignar')
            ->name('asignaciones.reasignar');
        // Entradas: la recepcion vista desde Almacen. Escribe en costos_entregas,
        // que es lo que destraba la factura — no es una tabla nueva. Aqui vive
        // la entrada SIN orden; la que va contra una orden se captura en el
        // flujo de Costos, donde esta el tope contra lo pedido y lo facturado.
        Route::resource('entradas', AlmEntradaController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['entradas' => 'entrada'])
            ->middlewareFor(['index', 'show'], 'permission:alm.entradas.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.entradas.crear');
        // Salidas: entrega de material que se queda en el mismo domicilio. Sin
        // edit ni update, se corrige cancelando y volviendo a capturar.
        Route::resource('salidas', AlmSalidaController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['salidas' => 'salida'])
            ->middlewareFor(['index', 'show'], 'permission:alm.salidas.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.salidas.crear');
        // El vale impreso: la hoja sellada con el folio en codigo de barras que
        // firma quien se lleva el material. Es consulta, va con 'ver'.
        Route::get('salidas/{salida}/pdf', [AlmSalidaController::class, 'pdf'])
            ->whereNumber('salida')
            ->middleware('permission:alm.salidas.ver')
            ->name('salidas.pdf');
        Route::patch('salidas/{salida}/cancelar', [AlmSalidaController::class, 'cancelar'])
            ->whereNumber('salida')
            ->middleware('permission:alm.salidas.crear')
            ->name('salidas.cancelar');
        // Transferencias: un folio y dos firmas. `create` es el envio y `show`
        // es la recepcion del destino, no una ficha de consulta — por eso va
        // con 'recibir' y no con 'ver'. Declarada despues de 'create' o esa
        // ruta se la comeria.
        Route::get('transferencias', [AlmTransferenciaController::class, 'index'])
            ->middleware('permission:alm.transferencias.ver')
            ->name('transferencias.index');
        Route::get('transferencias/create', [AlmTransferenciaController::class, 'create'])
            ->middleware('permission:alm.transferencias.enviar')
            ->name('transferencias.create');
        Route::post('transferencias', [AlmTransferenciaController::class, 'store'])
            ->middleware('permission:alm.transferencias.enviar')
            ->name('transferencias.store');
        Route::get('transferencias/{transferencia}', [AlmTransferenciaController::class, 'show'])
            ->whereNumber('transferencia')
            ->middleware('permission:alm.transferencias.recibir')
            ->name('transferencias.show');
        Route::patch('transferencias/{transferencia}/recibir', [AlmTransferenciaController::class, 'recibir'])
            ->whereNumber('transferencia')
            ->middleware('permission:alm.transferencias.recibir')
            ->name('transferencias.recibir');
        // La hoja que viaja con el material y vuelve firmada por el destino.
        // Es consulta, asi que basta con poder verla desde cualquiera de los
        // dos extremos.
        Route::get('transferencias/{transferencia}/pdf', [AlmTransferenciaController::class, 'pdf'])
            ->whereNumber('transferencia')
            ->middleware('permission:alm.transferencias.recibir')
            ->name('transferencias.pdf');
        Route::patch('transferencias/{transferencia}/cancelar', [AlmTransferenciaController::class, 'cancelar'])
            ->whereNumber('transferencia')
            ->middleware('permission:alm.transferencias.enviar')
            ->name('transferencias.cancelar');
        // Ajustes: el unico documento que cambia la existencia sin material de
        // por medio. Sin edit/update/destroy, como todos los de almacen: un
        // ajuste equivocado se corrige con otro y los dos quedan en el kardex.
        // El acta del conteo, firmada. Va antes del resource para que el
        // segmento /pdf no lo capture {ajuste}.
        Route::get('ajustes/{ajuste}/pdf', [AlmAjusteController::class, 'pdf'])
            ->whereNumber('ajuste')
            ->middleware('permission:alm.ajustes.ver')
            ->name('ajustes.pdf');
        Route::resource('ajustes', AlmAjusteController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['ajustes' => 'ajuste'])
            ->middlewareFor(['index', 'show'], 'permission:alm.ajustes.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.ajustes.crear');

        // El catalogo con el saldo de ese almacen, para la hoja del conteo. Va
        // el catalogo entero: el ajuste es el documento que abre existencia
        // donde no habia, asi que acotarlo a lo que ya tiene renglon dejaba un
        // almacen recien abierto sin nada que contar.
        Route::get('almacenes/{almacen}/catalogo-conteo', [AlmAjusteController::class, 'catalogoDeConteo'])
            ->whereNumber('almacen')
            ->middleware('permission:alm.ajustes.crear')
            ->name('almacenes.catalogo-conteo');
        // Lo que hay ahora en el almacen. La usan la salida, el pedido y la
        // transferencia, que solo pueden mover lo que existe.
        Route::get('almacenes/{almacen}/existencias', [AlmAjusteController::class, 'existencias'])
            ->whereNumber('almacen')
            ->middleware('permission:alm.ajustes.crear')
            ->name('almacenes.existencias');
        // Pedidos: lo que un area le pide al almacen. Nace aprobado mientras la
        // matriz de aprobadores no exista.
        // El formato impreso del pedido, que es donde se autoriza: el modulo
        // no tiene flujo de aprobacion y la firma va en la hoja.
        Route::get('pedidos/{pedido}/pdf', [AlmPedidoController::class, 'pdf'])
            ->whereNumber('pedido')
            ->middleware('permission:alm.pedidos.ver')
            ->name('pedidos.pdf');
        Route::resource('pedidos', AlmPedidoController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['pedidos' => 'pedido'])
            ->middlewareFor(['index', 'show'], 'permission:alm.pedidos.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.pedidos.crear');
        Route::patch('pedidos/{pedido}/cancelar', [AlmPedidoController::class, 'cancelar'])
            ->whereNumber('pedido')
            ->middleware('permission:alm.pedidos.cancelar')
            ->name('pedidos.cancelar');
        // Prestamos: el resguardo. No mueve saldo, cambia la custodia; por eso
        // no tiene edit ni destroy: un resguardo se cierra devolviendo.
        Route::get('prestamos/prestables/{almacen}', [AlmPrestamoController::class, 'prestables'])
            ->whereNumber('almacen')
            ->middleware('permission:alm.prestamos.crear')
            ->name('prestamos.prestables');
        Route::get('prestamos/{prestamo}/pdf', [AlmPrestamoController::class, 'pdf'])
            ->whereNumber('prestamo')
            ->middleware('permission:alm.prestamos.ver')
            ->name('prestamos.pdf');
        Route::resource('prestamos', AlmPrestamoController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['prestamos' => 'prestamo'])
            ->middlewareFor(['index', 'show'], 'permission:alm.prestamos.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.prestamos.crear');
        // Devoluciones: el cierre de renglones de resguardo, venga del vale
        // que venga. No mueve existencia.
        Route::resource('devoluciones', AlmDevolucionController::class)
            ->only(['index', 'create', 'store'])
            ->parameters(['devoluciones' => 'devolucion'])
            ->middlewareFor(['index'], 'permission:alm.devoluciones.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.devoluciones.crear');
        // Activos: el padron de piezas con numero de serie. Sin show, la pieza
        // se corrige desde el modal de lapiz de su renglon. La baja va aparte
        // de la edicion porque descarga existencia.
        Route::resource('activos', AlmActivoController::class)
            ->only(['index', 'create', 'store', 'update'])
            ->parameters(['activos' => 'activo'])
            ->middlewareFor(['index'], 'permission:alm.activos.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.activos.crear')
            ->middlewareFor(['update'], 'permission:alm.activos.editar');
        Route::patch('activos/{activo}/baja', [AlmActivoController::class, 'baja'])
            ->whereNumber('activo')
            ->middleware('permission:alm.activos.editar')
            ->name('activos.baja');
        // El activo sin serie no tiene pieza que retirar: se retiran N de su
        // renglon de existencia.
        Route::patch('activos/por-cantidad/{existencia}/baja', [AlmActivoController::class, 'bajaPorCantidad'])
            ->whereNumber('existencia')
            ->middleware('permission:alm.activos.editar')
            ->name('activos.por-cantidad.baja');
        // Catalogo de articulos: es costos_productos visto desde Almacen, no una
        // tabla nueva. Sin destroy: un articulo con movimientos es parte del
        // historico del kardex, y se desactiva.
        Route::resource('articulos', AlmArticuloController::class)
            ->parameters(['articulos' => 'articulo'])
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:alm.articulos.ver')
            ->middlewareFor(['create', 'store'], 'permission:alm.articulos.crear')
            ->middlewareFor(['edit', 'update'], 'permission:alm.articulos.editar');
        // Desactivar/reactivar apaga o prende las dos caras del maestro a la
        // vez. Se niega con saldo o con piezas afuera: primero se ajusta.
        Route::patch('articulos/{articulo}/toggle', [AlmArticuloController::class, 'toggle'])
            ->whereNumber('articulo')
            ->middleware('permission:alm.articulos.desactivar')
            ->name('articulos.toggle');

        // Ubicaciones: una sola pantalla con el arbol y el alta. Sin destroy,
        // el kardex viejo menciona el lugar y borrarlo dejaria movimientos
        // apuntando a un anaquel que ya no existe.
        Route::get('ubicaciones', [AlmUbicacionController::class, 'index'])
            ->middleware('permission:alm.ubicaciones.ver')
            ->name('ubicaciones.index');
        Route::post('ubicaciones', [AlmUbicacionController::class, 'store'])
            ->middleware('permission:alm.ubicaciones.crear')
            ->name('ubicaciones.store');
        Route::put('ubicaciones/{ubicacion}', [AlmUbicacionController::class, 'update'])
            ->whereNumber('ubicacion')
            ->middleware('permission:alm.ubicaciones.editar')
            ->name('ubicaciones.update');
        Route::patch('ubicaciones/{ubicacion}/toggle', [AlmUbicacionController::class, 'toggle'])
            ->whereNumber('ubicacion')
            ->middleware('permission:alm.ubicaciones.editar')
            ->name('ubicaciones.toggle');
        // Acomodar material: la unica columna de alm_existencias que se escribe
        // fuera del ledger, porque donde esta guardado no cambia el saldo.
        Route::patch('existencias/{existencia}/ubicacion', [AlmUbicacionController::class, 'asignar'])
            ->whereNumber('existencia')
            ->middleware('permission:alm.ubicaciones.editar')
            ->name('existencias.ubicacion');
        // Inventarios ciclicos: el programa reparte el almacen en hojas por dia,
        // cada hoja se imprime para caminarla, se captura lo contado y al cerrar
        // genera el ajuste. Capturar y cerrar son permisos distintos: contar y
        // autorizar la correccion no son lo mismo.
        Route::get('conteos', [AlmConteoController::class, 'index'])
            ->middleware('permission:alm.conteos.ver')
            ->name('conteos.index');
        Route::post('conteos/programas', [AlmConteoController::class, 'storePrograma'])
            ->middleware('permission:alm.conteos.crear')
            ->name('conteos.programas.store');
        Route::get('conteos/{conteo}/pdf', [AlmConteoController::class, 'pdf'])
            ->whereNumber('conteo')
            ->middleware('permission:alm.conteos.ver')
            ->name('conteos.pdf');
        Route::get('conteos/{conteo}', [AlmConteoController::class, 'show'])
            ->whereNumber('conteo')
            ->middleware('permission:alm.conteos.ver')
            ->name('conteos.show');
        Route::patch('conteos/{conteo}/captura', [AlmConteoController::class, 'capturar'])
            ->whereNumber('conteo')
            ->middleware('permission:alm.conteos.capturar')
            ->name('conteos.capturar');
        Route::post('conteos/{conteo}/cerrar', [AlmConteoController::class, 'cerrar'])
            ->whereNumber('conteo')
            ->middleware('permission:alm.conteos.cerrar')
            ->name('conteos.cerrar');
        Route::get('etiquetas', [AlmVistasController::class, 'etiquetas'])
            ->middleware('permission:alm.etiquetas.ver')
            ->name('etiquetas.index');
        Route::get('aprobaciones', [AlmVistasController::class, 'aprobaciones'])
            ->middleware('permission:alm.aprobaciones.ver')
            ->name('aprobaciones.index');
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

        // Tipo de cambio de referencia del día (sugerencia para el campo editable).
        Route::get('tipo-cambio/{moneda}', [\App\Http\Controllers\Admin\Costos\TipoCambioController::class, 'show'])->name('tipo-cambio.show');

        Route::resource('tipo-rubros', CostosTipoRubroController::class)->parameters(['tipo-rubros' => 'tipoRubro']);
        Route::resource('usos-cfdi', CostosUsoCfdiController::class)->parameters(['usos-cfdi' => 'usoCfdi'])->except(['show']);
        Route::resource('rubros', CostosRubroController::class)->parameters(['rubros' => 'rubro']);
        Route::get('productos/buscar', [CostosProductoController::class, 'buscar'])->name('productos.buscar');
        // Sin create ni store: un producto nace solo desde Almacen > Articulos,
        // que da de alta las dos caras del maestro a la vez. Compras lo consulta
        // y lo edita.
        Route::resource('productos', CostosProductoController::class)->only(['index', 'edit', 'update', 'destroy'])->parameters(['productos' => 'producto']);
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
        // Antes del resource: si no, {requisicion} se traga /copiables.
        Route::get('requisiciones/copiables', [CostosRequisicionController::class, 'copiables'])->name('requisiciones.copiables');
        Route::get('requisiciones/{requisicion}/para-copiar', [CostosRequisicionController::class, 'paraCopiar'])->name('requisiciones.para-copiar');
        Route::resource('requisiciones', CostosRequisicionController::class)->parameters(['requisiciones' => 'requisicion']);
        Route::get('requisiciones/{requisicion}/pdf', [CostosRequisicionController::class, 'pdf'])->name('requisiciones.pdf');
        Route::post('requisiciones/{requisicion}/duplicar', [CostosRequisicionController::class, 'duplicar'])->name('requisiciones.duplicar');
        Route::post('requisiciones/{requisicion}/cancelar', [CostosRequisicionController::class, 'cancelar'])->name('requisiciones.cancelar');
        Route::post('requisiciones/{requisicion}/enviar-aprobacion', [CostosRequisicionController::class, 'enviarAprobacion'])->name('requisiciones.enviar-aprobacion');
        Route::post('requisiciones/{requisicion}/iniciar-aprobacion', [CostosRequisicionController::class, 'iniciarAprobacion'])->name('requisiciones.iniciar-aprobacion');
        Route::post('requisiciones/{requisicion}/tipo-cambio', [CostosRequisicionController::class, 'guardarTipoCambio'])->name('requisiciones.tipo-cambio');
        Route::post('requisiciones/{requisicion}/firmar-final', [CostosRequisicionController::class, 'firmarFinal'])->name('requisiciones.firmar-final');
        Route::post('requisiciones/{requisicion}/aprobar-interno', [CostosRequisicionController::class, 'aprobarInterno'])->name('requisiciones.aprobar-interno');
        Route::post('requisiciones/{requisicion}/rechazar-interno', [CostosRequisicionController::class, 'rechazarInterno'])->name('requisiciones.rechazar-interno');
        Route::post('requisiciones/{requisicion}/liberar', [CostosRequisicionController::class, 'liberar'])->name('requisiciones.liberar');
        Route::post('requisiciones/{requisicion}/dedazo', [CostosRequisicionController::class, 'setDedazo'])->name('requisiciones.dedazo');
        Route::post('requisiciones/{requisicion}/convertir-oc', [CostosRequisicionController::class, 'convertirAOc'])->name('requisiciones.convertir-oc');
        Route::post('requisiciones/{requisicion}/re-apartar', [CostosRequisicionController::class, 'reApartar'])->name('requisiciones.re-apartar');
        Route::post('requisiciones/detalles/{detalle}/clasificacion', [CostosRequisicionCotizacionController::class, 'clasificar'])->name('requisiciones.detalles.clasificar');
        Route::post('requisiciones/detalles/{detalle}/solo-cotizacion', [CostosRequisicionCotizacionController::class, 'soloCotizacion'])->name('requisiciones.detalles.solo-cotizacion');
        Route::post('requisiciones/detalles/{detalle}/sin-impuestos', [CostosRequisicionCotizacionController::class, 'sinImpuestos'])->name('requisiciones.detalles.sin-impuestos');
        Route::patch('requisiciones/detalles/{detalle}/producto', [CostosRequisicionCotizacionController::class, 'actualizarProducto'])->name('requisiciones.detalles.producto');
        Route::post('requisiciones/{requisicion}/detalles', [CostosRequisicionCotizacionController::class, 'detalleStore'])->name('requisiciones.detalles.store');
        Route::delete('requisiciones/detalles/{detalle}', [CostosRequisicionCotizacionController::class, 'detalleDestroy'])->name('requisiciones.detalles.destroy');
        Route::post('requisiciones/{requisicion}/documentos', [CostosRequisicionCotizacionController::class, 'subirDocumento'])->name('requisiciones.documentos.store');
        Route::delete('requisiciones/{requisicion}/documentos/{media}', [CostosRequisicionCotizacionController::class, 'eliminarDocumento'])->name('requisiciones.documentos.destroy');
        Route::post('requisiciones/cotizaciones', [CostosRequisicionCotizacionController::class, 'store'])->name('requisiciones.cotizaciones.store');
        Route::post('requisiciones/{requisicion}/cotizaciones/tiempo-entrega', [CostosRequisicionCotizacionController::class, 'tiempoEntrega'])->name('requisiciones.cotizaciones.tiempo-entrega');
        Route::delete('requisiciones/cotizaciones/{precio}', [CostosRequisicionCotizacionController::class, 'destroy'])->name('requisiciones.cotizaciones.destroy');
        Route::delete('requisiciones/{requisicion}/proveedores/{proveedor}', [CostosRequisicionCotizacionController::class, 'destroyProveedor'])->name('requisiciones.proveedores.destroy');
        Route::post('requisiciones/{requisicion}/opciones', [CostosRequisicionCotizacionController::class, 'opcionStore'])->name('requisiciones.opciones.store');
        Route::patch('requisiciones/opciones/{opcion}', [CostosRequisicionCotizacionController::class, 'opcionUpdate'])->name('requisiciones.opciones.update');
        Route::delete('requisiciones/opciones/{opcion}', [CostosRequisicionCotizacionController::class, 'opcionDestroy'])->name('requisiciones.opciones.destroy');
        Route::post('requisiciones/selecciones', [CostosRequisicionSeleccionController::class, 'store'])->name('requisiciones.selecciones.store');
        Route::patch('requisiciones/selecciones/{seleccion}', [CostosRequisicionSeleccionController::class, 'update'])->name('requisiciones.selecciones.update');
        Route::delete('requisiciones/selecciones/{seleccion}', [CostosRequisicionSeleccionController::class, 'destroy'])->name('requisiciones.selecciones.destroy');
        Route::post('requisiciones/{requisicion}/ocs', [CostosRequisicionOcController::class, 'store'])->name('requisiciones.ocs.store');

        Route::get('solicitudes-pago/reporte-pdf', [CostosSolicitudPagoController::class, 'reportePdf'])->name('solicitudes-pago.reporte-pdf');
        Route::get('solicitudes-pago/reporte-excel', [CostosSolicitudPagoController::class, 'reporteExcel'])->name('solicitudes-pago.reporte-excel');
        Route::resource('solicitudes-pago', CostosSolicitudPagoController::class)->parameters(['solicitudes-pago' => 'solicitudPago']);
        Route::post('solicitudes-pago/{solicitudPago}/archivos', [CostosSolicitudPagoController::class, 'storeArchivo'])->name('solicitudes-pago.archivos.store');
        Route::patch('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'updateArchivo'])->name('solicitudes-pago.archivos.update');
        Route::delete('solicitudes-pago/{solicitudPago}/archivos/{solicitudArchivo}', [CostosSolicitudPagoController::class, 'destroyArchivo'])->name('solicitudes-pago.archivos.destroy');
        Route::get('solicitudes-pago/{solicitudPago}/pdf', [CostosSolicitudPagoController::class, 'generarPdf'])->name('solicitudes-pago.pdf');
        Route::post('solicitudes-pago/{solicitudPago}/enviar-aprobacion', [CostosSolicitudPagoController::class, 'enviarAprobacion'])->name('solicitudes-pago.enviar-aprobacion');
        Route::post('solicitudes-pago/{solicitudPago}/upload-firmado', [CostosSolicitudPagoController::class, 'uploadFirmado'])->name('solicitudes-pago.upload-firmado');
        Route::post('solicitudes-pago/{solicitudPago}/cancelar', [CostosSolicitudPagoController::class, 'cancelar'])->name('solicitudes-pago.cancelar');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-costos', [CostosSolicitudPagoController::class, 'confirmarCostos'])->name('solicitudes-pago.confirmar-costos');
        Route::post('solicitudes-pago/{solicitudPago}/confirmar-contabilidad', [CostosSolicitudPagoController::class, 'confirmarContabilidad'])->name('solicitudes-pago.confirmar-contabilidad');
        Route::post('solicitudes-pago/{solicitudPago}/re-apartar', [CostosSolicitudPagoController::class, 'reApartar'])->name('solicitudes-pago.re-apartar');
        Route::post('solicitudes-pago/{solicitudPago}/reasignar', [CostosSolicitudPagoController::class, 'reasignar'])->name('solicitudes-pago.reasignar');

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
        Route::get('recepciones/exportar', [CostosEntregaController::class, 'exportar'])
            ->middleware('can:costos.ordenes-compra.ver')
            ->name('recepciones.exportar');
        Route::get('recepciones', [CostosEntregaController::class, 'index'])
            ->middleware('can:costos.ordenes-compra.ver')
            ->name('recepciones.index');
        Route::post('entregas/{entrega}', [CostosEntregaController::class, 'update'])->name('entregas.update');
        Route::post('entregas/{entrega}/cancelar', [CostosEntregaController::class, 'cancelar'])->name('entregas.cancelar');
        Route::get('entregas/{entrega}/pdf', [CostosEntregaController::class, 'pdf'])->name('entregas.pdf');
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

        // Puntos de control post-cadena (Costos / Contabilidad)
        Route::get('confirmaciones/exportar', [CostosConfirmacionController::class, 'exportar'])->name('confirmaciones.exportar');
        Route::get('confirmaciones', [CostosConfirmacionController::class, 'index'])->name('confirmaciones.index');

        // Afectaciones presupuestales
        Route::resource('afectaciones', CostosAfectacionPresupuestalController::class)->parameters(['afectaciones' => 'afectacion']);
        Route::get('afectaciones/{afectacion}/pdf', [CostosAfectacionPresupuestalController::class, 'generarPdf'])->name('afectaciones.pdf');
        Route::post('afectaciones/{afectacion}/afectar', [CostosAfectacionPresupuestalController::class, 'afectar'])->name('afectaciones.afectar');
        Route::post('afectaciones/{afectacion}/upload-firmado', [CostosAfectacionPresupuestalController::class, 'uploadFirmado'])->name('afectaciones.upload-firmado');
        Route::post('afectaciones/{afectacion}/cancelar', [CostosAfectacionPresupuestalController::class, 'cancelar'])->name('afectaciones.cancelar');

        // Presupuestos (proyecto / obra / partida)
        Route::get('presupuestos', [CostosPresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('obras-activas', [CostosPresupuestoController::class, 'obrasActivas'])->name('obras-activas.index');
        Route::get('obras-activas/pdf', [CostosPresupuestoController::class, 'obrasActivasPdf'])->name('obras-activas.pdf');
        Route::get('presupuestos/reporte-pdf', [CostosPresupuestoController::class, 'generarReportePdf'])->name('presupuestos.reporte-pdf');
        Route::post('presupuestos/planta', [CostosPresupuestoController::class, 'storePlanta'])->name('presupuestos.planta.store');
        Route::post('presupuestos', [CostosPresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('presupuestos/{presupuesto}/edit', [CostosPresupuestoController::class, 'edit'])->name('presupuestos.edit');
        Route::put('presupuestos/{presupuesto}', [CostosPresupuestoController::class, 'update'])->name('presupuestos.update');
        Route::post('presupuestos/{presupuesto}/estado', [CostosPresupuestoController::class, 'cambiarEstado'])->name('presupuestos.estado');
        Route::post('presupuestos/{presupuesto}/documento', [CostosPresupuestoController::class, 'subirDocumento'])->name('presupuestos.documento');

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

        // ICSOE / SIROC (IMSS): un seguimiento por proyecto
        Route::get('icsoe', [CobIcsoeController::class, 'index'])->name('icsoe.index');
        Route::post('proyectos/{proyecto}/icsoe', [CobIcsoeController::class, 'store'])->name('proyectos.icsoe.store');
        Route::get('icsoe/{seguimiento}', [CobIcsoeController::class, 'show'])->whereNumber('seguimiento')->name('icsoe.show');
        Route::put('icsoe/{seguimiento}', [CobIcsoeController::class, 'update'])->whereNumber('seguimiento')->name('icsoe.update');
        Route::delete('icsoe/{seguimiento}', [CobIcsoeController::class, 'destroy'])->whereNumber('seguimiento')->name('icsoe.destroy');
        Route::put('icsoe/{seguimiento}/meses', [CobIcsoeMesController::class, 'update'])->whereNumber('seguimiento')->name('icsoe.meses.update');
        Route::post('icsoe/{seguimiento}/recalcular', [CobIcsoeController::class, 'recalcular'])->whereNumber('seguimiento')->name('icsoe.recalcular');
        Route::post('icsoe/{seguimiento}/verificar', [CobIcsoeController::class, 'verificar'])->whereNumber('seguimiento')->name('icsoe.verificar');

        // Catálogo de SBC / costo DOF / prima de riesgo por año
        Route::get('icsoe-sbc', [CobIcsoeSbcAnioController::class, 'index'])->name('icsoe-sbc.index');
        Route::post('icsoe-sbc', [CobIcsoeSbcAnioController::class, 'store'])->name('icsoe-sbc.store');
        Route::put('icsoe-sbc/{sbcAnio}', [CobIcsoeSbcAnioController::class, 'update'])->name('icsoe-sbc.update');
        Route::delete('icsoe-sbc/{sbcAnio}', [CobIcsoeSbcAnioController::class, 'destroy'])->name('icsoe-sbc.destroy');

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
        Route::post('carpetas/{carpeta}/accesos-internos', [DriveCarpetaAccesoController::class, 'store'])->name('carpetas.accesos-internos.store');
        Route::patch('carpetas/{carpeta}/accesos-internos/{usuario}', [DriveCarpetaAccesoController::class, 'update'])->name('carpetas.accesos-internos.update');
        Route::delete('carpetas/{carpeta}/accesos-internos/{usuario}', [DriveCarpetaAccesoController::class, 'destroy'])->name('carpetas.accesos-internos.destroy');
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

    // Calidad admin routes
    //
    // El modulo se rehace bajo el prefijo qal_, que sustituye a cal_. Las
    // tablas, los modelos y los permisos cal.* se quedan intactos porque los
    // usa la aplicacion anterior por API (routes/api.php, guard Sanctum)
    // mientras siga viva; mueren con ella. Aqui nada apunta a cal.
    //
    // La URL sigue siendo /admin/calidad: qal es el prefijo de la base, no el
    // nombre del modulo.
    Route::prefix('calidad')->name('qal.')->group(function () {
        // El tablero solo lee: resume lo que capturaron las demas pantallas.
        Route::get('dashboard', [QalDashboardController::class, 'index'])
            ->middleware('permission:qal.dashboard.ver')
            ->name('dashboard');
        // Captura de inspección: 1ª, 2ª con sus juntas y pintura en una sola
        // pantalla, porque la tablet se queda abierta aquí y lo que cambia es
        // la fase. El lector de QR pregunta por la pieza sin salir de ella.
        Route::middleware('permission:qal.inspecciones.crear')->group(function () {
            Route::get('formularios', [QalInspeccionController::class, 'create'])
                ->name('formularios');
            Route::post('inspecciones', [QalInspeccionController::class, 'store'])
                ->name('inspecciones.store');
            // Una inspección nueva de la misma pieza: la anterior se conserva.
            Route::get('inspecciones/{inspeccion}/reinspeccionar', [QalInspeccionController::class, 'reinspeccionar'])
                ->name('inspecciones.reinspeccionar');
            Route::get('piezas/resolver', [QalPiezaController::class, 'resolver'])
                ->name('piezas.resolver');
        });
        Route::middleware('permission:qal.inspecciones.editar')->group(function () {
            Route::get('inspecciones/{inspeccion}/edit', [QalInspeccionController::class, 'edit'])
                ->name('inspecciones.edit');
            Route::put('inspecciones/{inspeccion}', [QalInspeccionController::class, 'update'])
                ->name('inspecciones.update');
        });
        Route::delete('inspecciones/{inspeccion}', [QalInspeccionController::class, 'destroy'])
            ->middleware('permission:qal.inspecciones.eliminar')
            ->name('inspecciones.destroy');

        // Lotes de accesorios: la entrega se captura en Formularios (modo
        // lote) y el avance de cada marca se consulta en la pestaña
        // Accesorios del tablero; aquí sólo se escribe.
        Route::prefix('accesorios')->name('accesorios.')->group(function () {
            Route::middleware('permission:qal.accesorios.crear')->group(function () {
                Route::post('sublotes', [QalAccesorioController::class, 'store'])
                    ->name('sublotes.store');
                Route::get('lotes/{lote}/sublote', [QalAccesorioController::class, 'nuevoSublote'])
                    ->name('lotes.sublote');
                Route::get('sublotes/{sublote}/reinspeccionar', [QalAccesorioController::class, 'reinspeccionar'])
                    ->name('sublotes.reinspeccionar');
            });
            Route::middleware('permission:qal.accesorios.editar')->group(function () {
                Route::get('sublotes/{sublote}/edit', [QalAccesorioController::class, 'edit'])
                    ->name('sublotes.edit');
                Route::put('sublotes/{sublote}', [QalAccesorioController::class, 'update'])
                    ->name('sublotes.update');
            });
            Route::delete('sublotes/{sublote}', [QalAccesorioController::class, 'destroy'])
                ->middleware('permission:qal.accesorios.eliminar')
                ->name('sublotes.destroy');
        });

        // La marca de un modelo 3D con sus cordones, para montar el visor. La
        // pide la captura de soldado, así que capturar alcanza para leerla; la
        // pantalla del modelo vive en Producción, junto al catálogo de la obra.
        Route::get('modelos/marcas/{modeloMarca}', [QalModeloMarcaController::class, 'show'])
            ->middleware('permission:qal.modelos.ver|qal.inspecciones.crear')
            ->name('modelos.marca');

        // La base en crudo de lo capturado, para auditar: quien la abre ve lo
        // que capturó cualquier inspector. Exportar va aparte (RF-18.3): es
        // sacar la información del sistema.
        Route::get('registros', [QalRegistroController::class, 'index'])
            ->middleware('permission:qal.registros.ver')
            ->name('registros.index');
        Route::get('registros/exportar', [QalRegistroController::class, 'exportar'])
            ->middleware('permission:qal.registros.exportar')
            ->name('registros.exportar');
        // Producción contra calidad, semana a semana. Cuelga del mismo permiso
        // que la captura porque lo que compara es justo lo que se inspecciona;
        // cuando pueda guardar el plan va a pedir qal.programacion.capturar.
        // Avance de producción: el plan de la semana contra lo que calidad
        // inspeccionó. El plan lo teclea Producción; el resto se deduce.
        Route::get('avance', [QalProgramacionController::class, 'index'])
            ->middleware('permission:qal.programacion.ver')
            ->name('avance');
        Route::post('avance/programaciones', [QalProgramacionController::class, 'store'])
            ->middleware('permission:qal.programacion.capturar')
            ->name('avance.programaciones.store');

        // Pruebas no destructivas. Es recurso aparte de reportes: aqui se
        // cuentan juntas soldadas evaluadas por un laboratorio externo, alla
        // piezas revisadas a la vista por el inspector. No se suman.
        Route::get('pnd', [QalPndController::class, 'index'])
            ->middleware('permission:qal.pnd.ver')
            ->name('pnd.index');
        Route::get('pnd/create', [QalPndController::class, 'create'])
            ->middleware('permission:qal.pnd.crear')
            ->name('pnd.create');
        Route::post('pnd', [QalPndController::class, 'store'])
            ->middleware('permission:qal.pnd.crear')
            ->name('pnd.store');
        Route::get('pnd/{pnd}/edit', [QalPndController::class, 'edit'])
            ->middleware('permission:qal.pnd.ver')
            ->name('pnd.edit');
        Route::post('pnd/{pnd}', [QalPndController::class, 'update'])
            ->middleware('permission:qal.pnd.editar')
            ->name('pnd.update');
        Route::delete('pnd/{pnd}', [QalPndController::class, 'destroy'])
            ->middleware('permission:qal.pnd.eliminar')
            ->name('pnd.destroy');
        Route::post('pnd/{pnd}/resolver-marcas', [QalPndController::class, 'resolverMarcas'])
            ->middleware('permission:qal.pnd.editar')
            ->name('pnd.resolver-marcas');

        // Incidencias en obra: lo que falla durante el montaje. Circuito
        // aparte del taller, y quien lo captura es el residente, no el
        // inspector, por eso tiene sus propios permisos.
        //
        // La captura va toda colgada de la obra ({obra}) porque el avance de
        // montaje y las incidencias solo significan algo dentro de una: un
        // porcentaje de todas las obras juntas mezcla denominadores.
        Route::prefix('incidencias')->name('incidencias.')->group(function () {
            Route::get('/', [QalIncidenciasController::class, 'index'])
                ->middleware('permission:qal.incidencias.ver')
                ->name('index');
            Route::get('{obra}', [QalIncidenciasController::class, 'show'])
                ->whereNumber('obra')
                ->middleware('permission:qal.incidencias.ver')
                ->name('show');

            Route::middleware('permission:qal.incidencias.capturar')->group(function () {
                Route::post('{obra}/montaje', [QalIncidenciasController::class, 'guardarMontaje'])
                    ->name('montaje');
                Route::post('{obra}/sin-incidencias', [QalIncidenciasController::class, 'sinIncidencias'])
                    ->name('sin-incidencias');
                Route::post('{obra}', [QalIncidenciasController::class, 'store'])
                    ->name('store');
                Route::patch('{obra}/{incidencia}/estado', [QalIncidenciasController::class, 'cambiarEstado'])
                    ->name('estado');
            });

            Route::middleware('permission:qal.incidencias.eliminar')->group(function () {
                Route::delete('{obra}/montaje/{montaje}', [QalIncidenciasController::class, 'borrarMontaje'])
                    ->name('montaje.destroy');
                Route::delete('{obra}/{incidencia}', [QalIncidenciasController::class, 'destroy'])
                    ->name('destroy');
            });
        });

        // El reporte semanal (F-STX-CA-31). Permiso propio y no el del
        // tablero: el tablero es la herramienta diaria del area y esto es el
        // documento con folio de formato que sale de la empresa.
        Route::get('reporte-semanal', [QalReporteSemanalController::class, 'index'])
            ->middleware('permission:qal.reporte-semanal.ver')
            ->name('reporte-semanal');

        // El plan comprometido es contrato, no captura: lo edita quien
        // administra la ficha de la obra.
        Route::put('pnd/plan/{obra}', [QalPndController::class, 'guardarPlan'])
            ->middleware('permission:qal.obras.editar')
            ->name('pnd.plan');
        // Catalogos del modulo: una sola pantalla con pestanas. Se entra con
        // cualquiera de los permisos de ver, y el front esconde las pestanas
        // que el usuario no puede consultar.
        Route::get('catalogos', [QalCatalogoController::class, 'index'])
            ->middleware('permission:qal.soldadores.ver|qal.laboratorios.ver|qal.tipos-pieza.ver|qal.equipos.ver|qal.operadores.ver|qal.responsables.ver|qal.supervisores-pintura.ver|qal.defectos.ver')
            ->name('catalogos.index');

        // Escritura, un permiso por catalogo. Ninguno tiene destroy: aqui nada
        // se borra, se desactiva.
        $catalogos = [
            'soldadores' => [QalSoldadorController::class, 'soldadores'],
            'laboratorios' => [QalLaboratorioController::class, 'laboratorios'],
            'tipos-pieza' => [QalTipoPiezaController::class, 'tipos-pieza'],
            'equipos' => [QalEquipoController::class, 'equipos'],
            'operadores' => [QalOperadorController::class, 'operadores'],
            'responsables' => [QalResponsableController::class, 'responsables'],
            'supervisores-pintura' => [QalSupervisorPinturaController::class, 'supervisores-pintura'],
            'defectos' => [QalDefectoController::class, 'defectos'],
        ];

        foreach ($catalogos as $ruta => [$controlador, $permiso]) {
            Route::post("catalogos/{$ruta}", [$controlador, 'store'])
                ->middleware("permission:qal.{$permiso}.crear")
                ->name("catalogos.{$ruta}.store");
            Route::put("catalogos/{$ruta}/{id}", [$controlador, 'update'])
                ->whereNumber('id')
                ->middleware("permission:qal.{$permiso}.editar")
                ->name("catalogos.{$ruta}.update");
            Route::patch("catalogos/{$ruta}/{id}/toggle", [$controlador, 'toggle'])
                ->whereNumber('id')
                ->middleware("permission:qal.{$permiso}.editar")
                ->name("catalogos.{$ruta}.toggle");
        }
    });

    // Documentacion
    Route::prefix('documentacion')->name('documentacion.')->group(function () {
        Route::get('costos', fn () => Inertia\Inertia::render('admin/documentacion/costos'))->name('costos');
        Route::get('rh', fn () => Inertia\Inertia::render('admin/documentacion/rh'))->name('rh');
    });
});
