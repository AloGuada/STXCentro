<?php

namespace App\Models\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
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
    protected $fillable = [
        'folio',
        'referencia',
        'requisicion_id',
        'proveedor_id',
        'obra_id',
        'departamento_id',
        'creado_por',
        'moneda',
        'total',
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
            'fecha_entrega_esperada' => 'date',
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
     * Recalcula el estatus de la OC basado en el estado agregado de sus facturas.
     */
    public function recalcularEstatus(): void
    {
        $facturas = $this->facturas()->where('estatus', '!=', FacturaEstatus::Cancelada->value)->get();

        if ($facturas->isEmpty()) {
            $this->update(['estatus' => 'pendiente_factura']);

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

        if ($facturas->every(fn ($f) => in_array($f->estatus, [FacturaEstatus::PendienteAprobacion, FacturaEstatus::PendientePago, FacturaEstatus::Pagada], true))) {
            $this->update(['estatus' => 'pendiente_aprobacion']);

            return;
        }

        $this->update(['estatus' => 'pendiente_entrega']);
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
