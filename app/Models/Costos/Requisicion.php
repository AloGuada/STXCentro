<?php

namespace App\Models\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\RequisicionEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Costos\Concerns\VerificaPresupuestoReservado;
use App\Models\Departamento;
use App\Models\Media;
use App\Models\Obra;
use App\Models\Usuario;
use App\Services\Costos\BuscadorMejorProveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionFactory>
 */
class Requisicion extends Model implements Aprobable
{
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity, VerificaPresupuestoReservado;

    public const TIPO_APROBACION = 'requisicion';

    protected $table = 'costos_requisiciones';

    protected static string $folioPrefix = 'REQ';

    protected static string $stateEnum = RequisicionEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'solicitante_id',
        'firma_adicional_aprobador_id',
        'departamento_id',
        'obra_id',
        'presupuesto_id',
        'justificacion',
        'fecha_requerida',
        'estatus',
        'control_verificado',
        'control_por',
        'control_at',
        'modo_dedazo',
        'sobre_obra_cerrada',
        'motivo_rechazo',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_requerida' => 'date',
            'estatus' => RequisicionEstatus::class,
            'control_verificado' => 'boolean',
            'control_at' => 'datetime',
            'modo_dedazo' => 'boolean',
            'sobre_obra_cerrada' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function firmaAdicionalAprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'firma_adicional_aprobador_id');
    }

    public function firmaAdicionalAprobadorId(): ?string
    {
        return $this->firma_adicional_aprobador_id;
    }

    /**
     * Total neto a pagar (subtotal + IVA - retenciones) agrupando las
     * selecciones por (proveedor, OC) con {@see \App\Services\Costos\RetencionCalculator}.
     * Es 0 mientras no haya selecciones (antes de definir la OC).
     */
    public function getTotalNetoAttribute(): float
    {
        $calculador = new \App\Services\Costos\RetencionCalculator;

        /** @var array<string, array{proveedor: \App\Models\Proveedor, lineas: list<array{tipo_fiscal: ?string, subtotal: float}>}> $grupos */
        $grupos = [];
        foreach ($this->detalles as $detalle) {
            foreach ($detalle->selecciones as $seleccion) {
                if (! $seleccion->proveedor) {
                    continue;
                }

                $clave = $seleccion->proveedor_id.'|'.($seleccion->numero_oc ?? 1);
                $subtotal = (float) ($seleccion->cotizacionPrecio?->precio_unitario ?? 0) * (float) $seleccion->cantidad;

                $grupos[$clave]['proveedor'] ??= $seleccion->proveedor;
                $grupos[$clave]['lineas'][] = [
                    'tipo_fiscal' => $detalle->tipo_fiscal?->value,
                    'subtotal' => $subtotal,
                ];
            }
        }

        $neto = 0.0;
        foreach ($grupos as $grupo) {
            $neto += $calculador->calcular($grupo['proveedor'], $grupo['lineas'])['total_neto'];
        }

        return round($neto, 2);
    }

    public function controlador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'control_por');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * @deprecated Se conserva durante la transición. Usar presupuesto().
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Presupuesto (proyecto/obra/partida) al que se carga la requisición.
     * Nulo cuando es multipresupuesto (cada partida define el suyo por rubro).
     */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class, 'requisicion_id');
    }

    public function cotizaciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            RequisicionCotizacionPrecio::class,
            RequisicionDetalle::class,
            'requisicion_id',
            'requisicion_detalle_id',
        );
    }

    public function selecciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            RequisicionSeleccion::class,
            RequisicionDetalle::class,
            'requisicion_id',
            'requisicion_detalle_id',
        );
    }

    public function ocs(): HasMany
    {
        return $this->hasMany(RequisicionOc::class, 'requisicion_id');
    }

    public function cotizacionOpciones(): HasMany
    {
        return $this->hasMany(RequisicionCotizacionOpcion::class, 'requisicion_id')
            ->orderBy('proveedor_id')
            ->orderBy('orden');
    }

    public function aprobaciones(): MorphMany
    {
        return $this->morphMany(Aprobacion::class, 'aprobable');
    }

    public function ordenesGeneradas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'requisicion_id');
    }

    public function rubrosAfectados(): MorphMany
    {
        return $this->morphMany(RubroAfectado::class, 'entrada');
    }

    /**
     * Archivos adjuntos a la requisición (ej. PDFs de cotización como
     * información extra durante el cotizado).
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    // --- Aprobable ---------------------------------------------------------

    public function tipoAprobacion(): string
    {
        return self::TIPO_APROBACION;
    }

    public function saltaVerificacionCostos(): bool
    {
        return false;
    }

    public function cadenaAprobacion(): MorphMany
    {
        return $this->aprobaciones();
    }

    public function onAprobacionCompleta(?string $userId = null): void
    {
        $this->transitionTo(RequisicionEstatus::Aprobada);
    }

    public function onAprobacionRechazada(string $motivo, ?string $userId = null): void
    {
        $this->update(['motivo_rechazo' => $motivo]);
        app(\App\Services\Costos\ApartadoPresupuestal::class)->cancelarApartadosDe($this, 'rechazada en aprobación');
        $this->transitionTo(RequisicionEstatus::Rechazada);
    }

    /**
     * Mejor proveedor: aquel que cotizó TODAS las partidas y cuya suma de
     * (cantidad × precio_unitario) por partida es la menor. La lógica vive en
     * {@see BuscadorMejorProveedor}.
     *
     * @return array{id: int, razon_social: string, nombre_comercial: string|null, total: float}|null
     */
    public function getMejorProveedorAttribute(): ?array
    {
        return app(BuscadorMejorProveedor::class)->buscar($this);
    }

    /**
     * Cantidad de proveedores distintos que han cotizado al menos una partida.
     */
    public function getProveedoresCotizadoresCountAttribute(): int
    {
        $this->loadMissing('detalles.cotizaciones');

        $ids = [];
        foreach ($this->detalles as $detalle) {
            foreach ($detalle->cotizaciones as $cot) {
                $ids[(int) $cot->proveedor_id] = true;
            }
        }

        return count($ids);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'departamento_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Requisición {$this->folio}: {$event}");
    }
}
