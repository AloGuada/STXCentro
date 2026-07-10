<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\RequisicionEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RequisicionCotizacionPrecioStoreRequest;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Producto;
use App\Models\Costos\ProductoPrecio;
use App\Models\Costos\Requisicion;
use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\RequisicionSeleccion;
use App\Models\Media;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Maneja la matriz de precio comparativo de la requisicion: por cada
 * (partida, proveedor) hay un solo registro de cotizacion. Solo accesible
 * mientras la requisicion este en `borrador` o `cotizada` (compras todavia
 * editando) o `rechazada` (re-cotizando).
 */
class RequisicionCotizacionController extends Controller
{
    public function store(RequisicionCotizacionPrecioStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle = RequisicionDetalle::with('requisicion')->findOrFail(
            $request->integer('requisicion_detalle_id')
        );

        $this->ensureEditable($detalle->requisicion->estatus);

        // La opción define a qué proveedor pertenece la celda; se valida que
        // pertenezca a la misma requisición que la partida.
        $opcion = RequisicionCotizacionOpcion::findOrFail($request->integer('opcion_id'));
        abort_unless($opcion->requisicion_id === $detalle->requisicion_id, 422, 'La opción no pertenece a esta requisición.');

        $valores = [
            'precio_unitario' => $request->float('precio_unitario'),
            'proveedor_id' => $opcion->proveedor_id,
        ];

        foreach (['descripcion', 'codigo_producto', 'moneda', 'tiempo_entrega_dias', 'observaciones'] as $campo) {
            if ($request->has($campo)) {
                $valores[$campo] = $request->input($campo);
            }
        }

        RequisicionCotizacionPrecio::updateOrCreate(
            [
                'requisicion_detalle_id' => $detalle->id,
                'opcion_id' => $opcion->id,
            ],
            $valores,
        );

        $this->registrarHistoricoPrecio($detalle, $opcion->proveedor_id, $request->float('precio_unitario'), (string) $request->input('moneda', 'mxn'));

        $this->promoverACotizada($detalle->requisicion);

        return back()->with('success', 'Precio cotizado guardado.');
    }

    /**
     * Días de envío por opción: se aplica a todas las celdas de esa columna-opción
     * (el tiempo de entrega es uno por opción del proveedor).
     */
    public function tiempoEntrega(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');
        $this->ensureEditable($requisicion->estatus);

        $validated = $request->validate([
            'opcion_id' => ['required', 'integer', 'exists:costos_requisicion_cotizacion_opcion,id'],
            'tiempo_entrega_dias' => ['nullable', 'integer', 'min:0'],
        ]);

        RequisicionCotizacionPrecio::where('opcion_id', $validated['opcion_id'])
            ->whereHas('detalle', fn ($q) => $q->where('requisicion_id', $requisicion->id))
            ->update(['tiempo_entrega_dias' => $validated['tiempo_entrega_dias']]);

        return back()->with('success', 'Días de envío actualizados.');
    }

    /**
     * Crea una columna-opción para un proveedor en el comparativo. El orden es el
     * siguiente disponible para ese proveedor en la requisición.
     */
    public function opcionStore(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');
        $this->ensureEditable($requisicion->estatus);

        $validated = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'etiqueta' => ['nullable', 'string', 'max:255'],
        ]);

        $orden = ($requisicion->cotizacionOpciones()->where('proveedor_id', $validated['proveedor_id'])->max('orden') ?? 0) + 1;

        $requisicion->cotizacionOpciones()->create([
            'proveedor_id' => $validated['proveedor_id'],
            'etiqueta' => $validated['etiqueta'] ?? null,
            'orden' => $orden,
        ]);

        return back()->with('success', 'Opción agregada.');
    }

    public function opcionUpdate(Request $request, RequisicionCotizacionOpcion $opcion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $opcion->load('requisicion');
        $this->ensureEditable($opcion->requisicion->estatus);

        $validated = $request->validate([
            'etiqueta' => ['nullable', 'string', 'max:255'],
        ]);

        $opcion->update(['etiqueta' => $validated['etiqueta'] ?: null]);

        return back()->with('success', 'Opción actualizada.');
    }

    /**
     * Borra una columna-opción. Sus celdas se borran en cascada (FK); las
     * selecciones que apuntaban a esas celdas se quitan antes.
     */
    public function opcionDestroy(RequisicionCotizacionOpcion $opcion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $opcion->load('requisicion');
        $this->ensureEditable($opcion->requisicion->estatus);

        RequisicionSeleccion::whereIn('cotizacion_precio_id', $opcion->precios()->pluck('id'))->delete();

        $opcion->delete();

        return back()->with('success', 'Opción eliminada.');
    }

    /**
     * Clasificación fiscal de la partida (tipo_fiscal), que Compras captura en
     * el tab de cotización. El código de producto NO va aquí: es por línea y
     * por proveedor, se guarda en cada cotización.
     */
    public function clasificar(Request $request, RequisicionDetalle $detalle): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle->load('requisicion');
        $this->ensureEditable($detalle->requisicion->estatus);

        $validated = $request->validate([
            'tipo_fiscal' => ['required', 'in:mercancia,flete,servicio_profesional,renta'],
        ]);

        $detalle->update(['tipo_fiscal' => $validated['tipo_fiscal']]);

        return back()->with('success', 'Clasificación de la partida actualizada.');
    }

    /**
     * Agrega una partida (renglón) a la requisición desde el tab de cotización.
     * Compras puede sumar partidas mientras la requisición siga editable, sin
     * volver a la edición del solicitante. Se captura como texto libre (sin
     * producto de catálogo); el centro de costos y uso de CFDI son obligatorios
     * para poder liberar después.
     */
    public function detalleStore(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');
        $this->ensureEditable($requisicion->estatus);

        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:20'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'uso_cfdi_id' => ['required', Rule::exists('costos_usos_cfdi', 'id')->where('activo', true)],
            'notas' => ['nullable', 'string'],
            'tipo_fiscal' => ['nullable', 'in:mercancia,flete,servicio_profesional,renta'],
        ]);

        // Si la requisición es de un solo presupuesto, el centro de costos debe
        // pertenecer a ese presupuesto.
        if ($requisicion->presupuesto_id) {
            $rubro = ObraRubro::find($validated['obra_rubro_id']);
            abort_unless(
                $rubro && $rubro->presupuesto_id === $requisicion->presupuesto_id,
                422,
                'El centro de costos no pertenece al presupuesto de la requisición.',
            );
        }

        $requisicion->detalles()->create([
            'descripcion' => $validated['descripcion'],
            'unidad' => $validated['unidad'],
            'cantidad' => $validated['cantidad'],
            'obra_rubro_id' => $validated['obra_rubro_id'],
            'uso_cfdi_id' => $validated['uso_cfdi_id'],
            'tipo_fiscal' => $validated['tipo_fiscal'] ?? 'mercancia',
            'notas' => $validated['notas'] ?? null,
        ]);

        return back()->with('success', 'Partida agregada.');
    }

    /**
     * Quita una partida de la requisición. Sus cotizaciones y selecciones se
     * borran (cascada + limpieza explícita de selecciones).
     */
    public function detalleDestroy(RequisicionDetalle $detalle): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle->load('requisicion');
        $this->ensureEditable($detalle->requisicion->estatus);

        $detalle->selecciones()->delete();
        $detalle->delete();

        return back()->with('success', 'Partida eliminada.');
    }

    public function destroy(RequisicionCotizacionPrecio $precio): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $precio->load('detalle.requisicion');
        $this->ensureEditable($precio->detalle->requisicion->estatus);

        // Si el precio se uso en alguna seleccion, quitar la seleccion antes.
        $precio->detalle->selecciones()
            ->where('cotizacion_precio_id', $precio->id)
            ->delete();

        $precio->delete();

        return back()->with('success', 'Precio eliminado.');
    }

    /**
     * Compras edita el producto del catálogo (código/descripción) de una partida
     * desde el tab de cotización; sincroniza el snapshot de la partida.
     */
    public function actualizarProducto(Request $request, RequisicionDetalle $detalle): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $detalle->load('requisicion');
        $this->ensureEditable($detalle->requisicion->estatus);

        if (! $detalle->producto_id) {
            return back()->withErrors(['producto' => 'La partida no está ligada a un producto del catálogo.']);
        }

        $validated = $request->validate([
            'codigo' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('costos_productos', 'codigo')->ignore($detalle->producto_id)],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        $producto = Producto::findOrFail($detalle->producto_id);
        $producto->update([
            'codigo' => $validated['codigo'] ?: null,
            'descripcion' => $validated['descripcion'],
        ]);

        $detalle->update([
            'descripcion' => $producto->descripcion,
            'codigo_producto' => $producto->codigo,
        ]);

        return back()->with('success', 'Producto actualizado.');
    }

    /**
     * Registra el precio cotizado en el histórico del producto (uno por
     * producto/proveedor/requisición). Alimenta el histórico desde la cotización.
     */
    private function registrarHistoricoPrecio(RequisicionDetalle $detalle, int $proveedorId, float $precio, string $moneda): void
    {
        if (! $detalle->producto_id || $precio <= 0) {
            return;
        }

        ProductoPrecio::updateOrCreate(
            [
                'producto_id' => $detalle->producto_id,
                'proveedor_id' => $proveedorId,
                'requisicion_id' => $detalle->requisicion_id,
            ],
            [
                'precio' => $precio,
                'moneda' => $moneda,
                'fecha' => Carbon::today(),
            ],
        );
    }

    /**
     * Quita un proveedor completo de la matriz de cotización: borra todas sus
     * cotizaciones en la requisición y las selecciones que las usaban. Usado
     * por el botón de quitar columna en el tab de cotización.
     */
    public function destroyProveedor(Requisicion $requisicion, Proveedor $proveedor): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $this->ensureEditable($requisicion->estatus);

        $detalleIds = $requisicion->detalles()->pluck('id');

        RequisicionSeleccion::whereIn('requisicion_detalle_id', $detalleIds)
            ->where('proveedor_id', $proveedor->id)
            ->delete();

        RequisicionCotizacionPrecio::whereIn('requisicion_detalle_id', $detalleIds)
            ->where('proveedor_id', $proveedor->id)
            ->delete();

        // Borra todas las columnas-opción del proveedor en esta requisición.
        $requisicion->cotizacionOpciones()->where('proveedor_id', $proveedor->id)->delete();

        return back()->with('success', 'Proveedor eliminado de la cotización.');
    }

    /**
     * Sube un PDF como información extra de la cotización (ej. cotización del
     * proveedor, fichas técnicas). Se adjunta a la requisición vía media.
     */
    public function subirDocumento(Request $request, Requisicion $requisicion): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $this->ensureEditable($requisicion->estatus);

        $validated = $request->validate([
            'documento' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'titulo' => ['nullable', 'string', 'max:255'],
        ], [
            'documento.required' => 'Selecciona un archivo PDF.',
            'documento.mimes' => 'El archivo debe ser un PDF.',
            'documento.max' => 'El archivo no debe superar 10 MB.',
        ]);

        $file = $request->file('documento');

        $requisicion->media()->create([
            'descripcion' => $validated['titulo'] ?: $file->getClientOriginalName(),
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $file->store("costos/requisiciones/{$requisicion->id}", 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Documento de cotización agregado.');
    }

    public function eliminarDocumento(Requisicion $requisicion, Media $media): RedirectResponse
    {
        Gate::authorize('costos.requisiciones.cotizar');

        $this->ensureEditable($requisicion->estatus);

        abort_unless(
            $media->mediable_type === $requisicion->getMorphClass() && $media->mediable_id === $requisicion->id,
            404,
        );

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    private function ensureEditable(RequisicionEstatus $estatus): void
    {
        if (! in_array($estatus, [
            RequisicionEstatus::Borrador,
            RequisicionEstatus::Cotizada,
            RequisicionEstatus::Rechazada,
        ], true)) {
            abort(422, 'La requisición ya no permite editar cotizaciones.');
        }
    }

    /**
     * Si la requisicion estaba en borrador y ya tiene al menos una cotizacion,
     * promueve a cotizada. Idempotente.
     */
    private function promoverACotizada(\App\Models\Costos\Requisicion $requisicion): void
    {
        if ($requisicion->estatus === RequisicionEstatus::Borrador) {
            $requisicion->transitionTo(RequisicionEstatus::Cotizada);
        }
    }
}
