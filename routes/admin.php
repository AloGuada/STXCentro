<?php

use App\Http\Controllers\Admin\DepartamentoController;
use App\Http\Controllers\Admin\Infra\RecorridoController as InfraRecorridoController;
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
use App\Http\Controllers\Admin\Prod\RegistroController as ProdRegistroController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Sti\AsignacionActivoController as StiAsignacionActivoController;
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
    Route::resource('media', MediaController::class);
    Route::resource('tags', TagController::class);

    // Intranet admin routes
    Route::prefix('intra')->name('intra.')->group(function () {
        Route::resource('secciones', SeccionEstaticaController::class);
        Route::resource('areas', IntraAreaController::class);
        Route::resource('documentos', IntraDocumentoController::class);
    });

    // Produccion admin routes
    Route::prefix('prod')->name('prod.')->group(function () {
        Route::resource('conceptos', ProdConceptoController::class)->parameters(['conceptos' => 'concepto']);
        Route::resource('grupo-precios', ProdGrupoPrecioController::class)->parameters(['grupo-precios' => 'grupoPrecio']);
        Route::get('grupo-precios/obra/{obra}', [ProdGrupoPrecioController::class, 'showByObra'])->name('grupo-precios.show-by-obra');
        Route::post('grupo-precios/{grupoPrecio}/assign-conceptos', [ProdGrupoPrecioController::class, 'assignConceptos'])->name('grupo-precios.assign-conceptos');

        // Pivot concepto-grupo precio
        Route::post('grupo-precio-conceptos', [ProdGrupoPrecioConceptoController::class, 'store'])->name('grupo-precio-conceptos.store');
        Route::delete('grupo-precio-conceptos/{grupoPrecioConcepto}', [ProdGrupoPrecioConceptoController::class, 'destroy'])->name('grupo-precio-conceptos.destroy');

        // Grupos de trabajo
        Route::resource('grupos-trabajo', ProdGrupoTrabajoController::class)->parameters(['grupos-trabajo' => 'grupoTrabajo']);
        Route::post('grupos-trabajo/{grupoTrabajo}/empleados', [ProdGrupoTrabajoController::class, 'storeEmpleado'])->name('grupos-trabajo.empleados.store');
        Route::delete('grupos-trabajo/{grupoTrabajo}/empleados/{empleado}', [ProdGrupoTrabajoController::class, 'destroyEmpleado'])->name('grupos-trabajo.empleados.destroy');

        // Registros
        Route::resource('registros', ProdRegistroController::class)->parameters(['registros' => 'registro'])->except(['edit', 'update']);

        // Cortes y liquidaciones
        Route::resource('cortes', ProdCorteController::class)->except(['edit', 'update'])->parameters(['cortes' => 'corte']);
        Route::post('cortes/{corte}/cerrar', [ProdCorteController::class, 'cerrar'])->name('cortes.cerrar');
        Route::post('cortes/{corte}/liquidaciones/{liquidacion}/extras', [ProdCorteController::class, 'storeExtra'])->name('cortes.liquidaciones.extras.store');
        Route::delete('cortes/{corte}/liquidaciones/{liquidacion}/extras/{extra}', [ProdCorteController::class, 'destroyExtra'])->name('cortes.liquidaciones.extras.destroy');
    });

    // Infraestructura admin routes
    Route::prefix('infra')->name('infra.')->group(function () {
        Route::get('recorridos', [InfraRecorridoController::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/show', [InfraRecorridoController::class, 'show'])->name('recorridos.show');
        Route::get('recorridos/create', [InfraRecorridoController::class, 'create'])->name('recorridos.create');
        Route::post('recorridos', [InfraRecorridoController::class, 'store'])->name('recorridos.store');
    });

    // STI admin routes
    Route::prefix('sti')->name('sti.')->group(function () {
        Route::resource('equipos', StiEquipoController::class);
        Route::resource('tecnicos', StiTecnicoController::class);
        Route::resource('status', StiStatusController::class)->parameters(['status' => 'status']);
        Route::resource('tickets', StiTicketController::class);
        // Mantenimientos: programacion, gantt (antes del resource para evitar colision con {mantenimiento})
        Route::get('mantenimientos/programacion', [StiMantenimientoController::class, 'programacion'])->name('mantenimientos.programacion');
        Route::post('mantenimientos/generar', [StiMantenimientoController::class, 'generar'])->name('mantenimientos.generar');
        Route::get('mantenimientos-gantt', [StiMantenimientoController::class, 'gantt'])->name('mantenimientos.gantt');

        Route::resource('mantenimientos', StiMantenimientoController::class);
        Route::resource('asignacion-activos', StiAsignacionActivoController::class);
        Route::post('asignacion-activos/{asignacion_activo}/media', [StiAsignacionActivoController::class, 'storeMedia'])->name('asignacion-activos.media.store');
        Route::delete('asignacion-activos/{asignacion_activo}/media/{media}', [StiAsignacionActivoController::class, 'destroyMedia'])->name('asignacion-activos.media.destroy');

        // Costos y comentarios de tickets
        Route::post('tickets/{ticket}/costos', [StiTicketController::class, 'storeCosto'])->name('tickets.costos.store');
        Route::delete('tickets/{ticket}/costos/{costo}', [StiTicketController::class, 'destroyCosto'])->name('tickets.costos.destroy');
        Route::post('tickets/{ticket}/comentarios', [StiTicketController::class, 'storeComentario'])->name('tickets.comentarios.store');

        // Mantenimientos: completar, media, costos
        Route::get('mantenimientos-gantt-anual', [StiMantenimientoController::class, 'ganttAnual'])->name('mantenimientos.gantt-anual');
        Route::post('mantenimientos/{mantenimiento}/completar', [StiMantenimientoController::class, 'completar'])->name('mantenimientos.completar');
        Route::post('mantenimientos/{mantenimiento}/checks/{checkEjecucion}/toggle', [StiMantenimientoController::class, 'toggleCheck'])->name('mantenimientos.checks.toggle');
        Route::post('mantenimientos/{mantenimiento}/media', [StiMantenimientoController::class, 'storeMedia'])->name('mantenimientos.media.store');
        Route::delete('mantenimientos/{mantenimiento}/media/{media}', [StiMantenimientoController::class, 'destroyMedia'])->name('mantenimientos.media.destroy');
        Route::post('mantenimientos/{mantenimiento}/costos', [StiMantenimientoController::class, 'storeCosto'])->name('mantenimientos.costos.store');
        Route::delete('mantenimientos/{mantenimiento}/costos/{costo}', [StiMantenimientoController::class, 'destroyCosto'])->name('mantenimientos.costos.destroy');

        // Inventario de items
        Route::post('items/{item}/asignar', [StiItemController::class, 'asignar'])->name('items.asignar');
        Route::post('items/{item}/retirar', [StiItemController::class, 'retirar'])->name('items.retirar');
        Route::resource('items', StiItemController::class);
        Route::resource('items-tipos', StiItemTipoController::class)->parameters(['items-tipos' => 'itemTipo']);

        // Planes de mantenimiento
        Route::resource('planes', StiPlanController::class)->parameters(['planes' => 'plan']);
        Route::post('planes/{plan}/checks', [StiPlanController::class, 'storeCheck'])->name('planes.checks.store');
        Route::put('planes/{plan}/checks/{check}', [StiPlanController::class, 'updateCheck'])->name('planes.checks.update');
        Route::delete('planes/{plan}/checks/{check}', [StiPlanController::class, 'destroyCheck'])->name('planes.checks.destroy');
        Route::post('planes/{plan}/reorder-checks', [StiPlanController::class, 'reorderChecks'])->name('planes.checks.reorder');
    });
});
