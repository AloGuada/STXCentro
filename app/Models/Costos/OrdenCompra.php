<?php

namespace App\Models\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\ModoPago;
use App\Enums\Costos\OrdenCompraEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\OrdenCompraFactory>
 */
class OrdenCompra extends Model
{
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_ordenes_compra';

    protected static string $folioPrefix = 'OC';

    protected static string $stateEnum = OrdenCompraEstatus::class;

    /**
     * @var list<string>
     */
    protected $appends = [
        'retrasada',
        'etapa_proceso',
        'monto_recibido',
        'total_facturado',
        'total_pagado',
        'porcentaje_recepcion',
        'porcentaje_facturacion',
        'porcentaje_pago',
        'pago_vencido',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'referencia',
        'requisicion_id',
        'proveedor_id',
        'obra_id',
        'departamento_id',
        'creado_por',
        'moneda',
        'tipo_pago',
        'dias_credito',
        'forma_pago',
        'total',
        'envio',
        'fecha_entrega_esperada',
        'notas',
        'estatus',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'envio' => 'decimal:2',
            'dias_credito' => 'integer',
            'fecha_entrega_esperada' => 'date',
            'tipo_pago' => ModoPago::class,
            'estatus' => OrdenCompraEstatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'orden_compra_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'orden_compra_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'orden_compra_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function archivo(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', DocumentoTipo::OcArchivo->value);
    }

    public function pdfFormato(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', DocumentoTipo::OcPdfFormato->value);
    }

    public function pdfFirmado(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', DocumentoTipo::OcPdfFirmado->value);
    }

    public function rubrosAfectados(): MorphMany
    {
        return $this->morphMany(RubroAfectado::class, 'entrada');
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    /**
     * Suma de facturas activas (no canceladas) ligadas a esta orden.
     */
    public function getTotalFacturadoAttribute(): float
    {
        return (float) $this->facturas()
            ->where('estatus', '!=', FacturaEstatus::Cancelada->value)
            ->sum('total');
    }

    /**
     * Suma de pagos realizados (estatus = pagado) sobre facturas de esta orden.
     * Solo cuenta pagos raíz (sin pago_padre_id) para no duplicar con parcialidades.
     */
    public function getTotalPagadoAttribute(): float
    {
        return (float) Pago::query()
            ->where('pagable_type', Factura::class)
            ->whereIn('pagable_id', $this->facturas()->pluck('id'))
            ->where('estatus', 'pagado')
            ->whereNull('pago_padre_id')
            ->sum('monto_pago');
    }

    /**
     * Saldo pendiente contra el total de la orden (total − total_pagado).
     */
    public function getSaldoPendienteAttribute(): float
    {
        return (float) $this->total - $this->total_pagado;
    }

    /**
     * Aplica el impacto presupuestal: incrementa acumulado en obra_rubros y crea rubros afectados.
     * Pasa por ValidadorPresupuesto antes de incrementar — si bloquear_sobregiro=true
     * y el monto excede disponible, lanza SobregiroPresupuestalException y aborta.
     */
    public function aplicarImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $validador = app(\App\Services\Costos\ValidadorPresupuesto::class);

        foreach ($this->detalles as $detalle) {
            $obraRubro = ObraRubro::find($detalle->obra_rubro_id);
            $validador->validar($obraRubro, (float) $detalle->subtotal, $this);

            ObraRubro::where('id', $detalle->obra_rubro_id)
                ->increment('acumulado', (float) $detalle->subtotal);

            $obraRubro->refresh();
            $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;

            $this->rubrosAfectados()->create([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'monto' => $detalle->subtotal,
                'sobre_giro' => $disponible < 0,
                'descripcion' => $obraRubro->rubro?->descripcion,
                'tipo_movimiento' => 'cargo',
                'estatus' => 'aplicado',
                'usuario_aplica_id' => $userId,
                'fecha_aplicacion' => now(),
            ]);
        }
    }

    /**
     * Recalcula el estatus de la OC basado en el estado agregado de sus facturas
     * y la presencia de recepciones del almacén.
     *
     * Reglas (en orden):
     *  - cancelada                        → no cambia
     *  - sin facturas activas, sin entregas → pendiente_entrega
     *  - sin facturas activas, con entregas → pendiente_factura
     *  - todas facturas pagadas           → pagada
     *  - todas facturas en pago o pagadas → pendiente_pago
     *  - default (con facturas activas)   → pendiente_aprobacion
     */
    public function recalcularEstatus(): void
    {
        if ($this->estatus === OrdenCompraEstatus::Cancelada) {
            return;
        }

        $facturas = $this->facturas()->where('estatus', '!=', FacturaEstatus::Cancelada->value)->get();

        if ($facturas->isEmpty()) {
            $estado = $this->entregas()->exists() ? 'pendiente_factura' : 'pendiente_entrega';
            $this->update(['estatus' => $estado]);

            return;
        }

        if ($facturas->every(fn ($f) => $f->estatus === FacturaEstatus::Pagada)) {
            $this->update(['estatus' => 'pagada']);

            return;
        }

        if ($facturas->every(fn ($f) => in_array($f->estatus, [FacturaEstatus::PendientePago, FacturaEstatus::Pagada], true))) {
            $this->update(['estatus' => 'pendiente_pago']);

            return;
        }

        $this->update(['estatus' => 'pendiente_aprobacion']);
    }

    /**
     * Indica si la fecha de entrega esperada ya pasó y aún no hay ninguna
     * recepción de almacén registrada. Se usa como bandera UI en portal y admin.
     */
    public function getRetrasadaAttribute(): bool
    {
        if (! $this->fecha_entrega_esperada) {
            return false;
        }

        $fecha = $this->fecha_entrega_esperada instanceof Carbon
            ? $this->fecha_entrega_esperada
            : Carbon::parse($this->fecha_entrega_esperada);

        if (! $fecha->isPast()) {
            return false;
        }

        return ! $this->entregas()->exists();
    }

    /**
     * Monto recibido por almacén = sumatoria de (cantidad_neta_recibida * precio_unitario)
     * por cada partida. Descuenta devoluciones vigentes via EntregaDetalle::cantidad_neta_recibida.
     */
    public function getMontoRecibidoAttribute(): float
    {
        $this->loadMissing(['entregas.detalles.ordenCompraDetalle', 'entregas.detalles.devoluciones']);

        $total = 0.0;
        foreach ($this->entregas as $entrega) {
            foreach ($entrega->detalles as $ed) {
                $precio = (float) ($ed->ordenCompraDetalle?->precio_unitario ?? 0);
                $total += $ed->cantidad_neta_recibida * $precio;
            }
        }

        return round($total, 2);
    }

    public function getPorcentajeRecepcionAttribute(): float
    {
        return $this->ratio($this->monto_recibido);
    }

    public function getPorcentajeFacturacionAttribute(): float
    {
        return $this->ratio($this->total_facturado);
    }

    public function getPorcentajePagoAttribute(): float
    {
        return $this->ratio($this->total_pagado);
    }

    private function ratio(float $monto): float
    {
        $total = (float) $this->total;
        if ($total <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, ($monto / $total) * 100)), 1);
    }

    /**
     * Etapa derivada para la UI del portal — reagrupa el estatus real para
     * comunicar al proveedor en qué paso está hoy la OC.
     */
    public function getEtapaProcesoAttribute(): string
    {
        if ($this->estatus === OrdenCompraEstatus::Cancelada) {
            return 'cancelada';
        }

        if ($this->monto_recibido > $this->total_facturado + 0.01) {
            return 'espera_factura';
        }

        if (! $this->entregas()->exists()) {
            return 'recepcion';
        }

        $facturas = $this->facturas->where('estatus', '!=', FacturaEstatus::Cancelada);

        if ($facturas->contains(fn ($f) => $f->estatus === FacturaEstatus::PendienteAprobacion)) {
            return 'validacion_documentos';
        }

        if ($facturas->isNotEmpty() && $this->total_pagado + 0.01 < $this->total) {
            return 'pago_programado';
        }

        if ($this->porcentaje_recepcion >= 100 && $this->total_pagado + 0.01 >= $this->total) {
            return 'completada';
        }

        return 'recepcion';
    }

    /**
     * True si existe al menos un Pago de cualquier factura de la OC con
     * fecha_pago_programada vencida y estatus en {Programado, Parcial}.
     */
    public function getPagoVencidoAttribute(): bool
    {
        $facturaIds = $this->facturas()->pluck('id');
        if ($facturaIds->isEmpty()) {
            return false;
        }

        return Pago::query()
            ->where('pagable_type', Factura::class)
            ->whereIn('pagable_id', $facturaIds)
            ->whereDate('fecha_pago_programada', '<', now()->toDateString())
            ->whereIn('estatus', ['programado', 'parcial'])
            ->exists();
    }

    /**
     * Revierte el impacto presupuestal.
     */
    public function revertirImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        foreach ($this->detalles as $detalle) {
            ObraRubro::where('id', $detalle->obra_rubro_id)
                ->decrement('acumulado', (float) $detalle->subtotal);
        }

        $this->rubrosAfectados()->create([
            'obra_rubro_id' => $this->detalles->first()?->obra_rubro_id ?? 0,
            'monto' => $this->total,
            'descripcion' => 'Cancelación de orden de compra',
            'tipo_movimiento' => 'abono',
            'estatus' => 'cancelado',
            'usuario_aplica_id' => $userId,
            'fecha_aplicacion' => now(),
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'total', 'proveedor_id', 'obra_id', 'departamento_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Orden de compra {$this->folio}: {$event}");
    }
}
