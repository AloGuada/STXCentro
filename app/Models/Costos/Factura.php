<?php

namespace App\Models\Costos;

use App\Enums\Costos\BaseDiasCredito;
use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Costos\Factura\Cobertura;
use App\Models\Costos\Factura\FechaPagoCalculada;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\FacturaFactory>
 */
class Factura extends Model
{
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_facturas';

    protected static string $folioPrefix = 'FA';

    protected static string $stateEnum = FacturaEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'orden_compra_id',
        'proveedor_id',
        'uuid_fiscal',
        'folio_fiscal',
        'subtotal',
        'iva',
        'iva_trasladado',
        'iva_retenido',
        'isr_retenido',
        'impuestos_detalle',
        'total',
        'moneda',
        'metodo_pago',
        'forma_pago',
        'fecha_factura',
        'estatus',
        'notas',
        'motivo_rechazo',
        'dias_credito',
        'base_dias_credito',
        'fecha_pago_calculada',
        'aprobada_costos',
        'aprobada_costos_por',
        'aprobada_costos_at',
        'aceptada_contabilidad',
        'aceptada_contabilidad_por',
        'aceptada_contabilidad_at',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'iva_trasladado' => 'decimal:2',
            'iva_retenido' => 'decimal:2',
            'isr_retenido' => 'decimal:2',
            'impuestos_detalle' => 'array',
            'total' => 'decimal:2',
            'fecha_factura' => 'date',
            'dias_credito' => 'integer',
            'fecha_pago_calculada' => 'date',
            'aprobada_costos' => 'boolean',
            'aprobada_costos_at' => 'datetime',
            'aceptada_contabilidad' => 'boolean',
            'aceptada_contabilidad_at' => 'datetime',
            'estatus' => FacturaEstatus::class,
            'base_dias_credito' => BaseDiasCredito::class,
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Fecha tentativa de pago de la factura. La lógica vive en
     * {@see FechaPagoCalculada}.
     */
    public function calcularFechaPago(): ?Carbon
    {
        return (new FechaPagoCalculada($this))->calcular();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function mediaXml(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', DocumentoTipo::XmlFactura->value);
    }

    public function mediaPdf(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', DocumentoTipo::PdfFactura->value);
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function complementosPago(): HasMany
    {
        return $this->hasMany(ComplementoPago::class, 'factura_id');
    }

    /**
     * CFDI con método de pago PPD (Pago en Parcialidades o Diferido): obliga al
     * proveedor a emitir un complemento de pago por cada pago recibido.
     */
    public function esPpd(): bool
    {
        return $this->metodo_pago === 'PPD';
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Entregas registradas contra la misma OC. La relación se resuelve
     * matcheando orden_compra_id local vs orden_compra_id de entrega.
     * Mantiene la API $factura->entregas aunque el schema las ligue a OC.
     */
    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'orden_compra_id', 'orden_compra_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class, 'factura_id');
    }

    public function pago(): MorphOne
    {
        return $this->morphOne(Pago::class, 'pagable');
    }

    public function anticiposAplicados(): HasMany
    {
        return $this->hasMany(AnticipoAplicacion::class, 'factura_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(NotaCredito::class, 'factura_id');
    }

    /**
     * Total de anticipos aplicados a esta factura.
     */
    public function getMontoAnticiposAttribute(): float
    {
        return (float) $this->anticiposAplicados()->sum('monto');
    }

    /**
     * Total de notas de credito vigentes (no canceladas) sobre esta factura.
     */
    public function getMontoNotasCreditoAttribute(): float
    {
        return (float) $this->notasCredito()
            ->where('estatus', NotaCreditoEstatus::Vigente->value)
            ->sum('monto');
    }

    /**
     * Saldo pendiente de la factura: total - anticipos - notas de credito.
     * No descuenta pagos ya aplicados (eso es responsabilidad del flujo de
     * pagos). Solo refleja lo que reduce el monto facturado.
     */
    public function getSaldoFacturadoAttribute(): float
    {
        return max(0.0, (float) $this->total - $this->monto_anticipos - $this->monto_notas_credito);
    }

    public function aprobadaCostosPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobada_costos_por');
    }

    public function aceptadaContabilidadPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aceptada_contabilidad_por');
    }

    /**
     * Objeto de cobertura: encapsula el cálculo de recepción por partida.
     * {@see Cobertura}
     */
    public function cobertura(): Cobertura
    {
        return new Cobertura($this);
    }

    /**
     * ¿Una partida de esta factura está cubierta por recepciones?
     */
    public function partidaCubierta(FacturaDetalle $fd): bool
    {
        return $this->cobertura()->partidaCubierta($fd);
    }

    /**
     * La factura está completamente cubierta si todas sus partidas tienen
     * recepción suficiente. Sirve para promover de pendiente_entrega a
     * pendiente_aprobacion automaticamente.
     */
    public function getCoberturaCompletaAttribute(): bool
    {
        return $this->cobertura()->estaCompleta();
    }

    /**
     * Snapshot para UI: por cada FacturaDetalle.id, cuánto disponible hay para
     * cubrirla y si está cubierta.
     *
     * @return array<int, array{disponible: float, cubierta: bool}>
     */
    public function getCoberturaPorPartidaAttribute(): array
    {
        return $this->cobertura()->porPartida();
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly([
                'folio', 'estatus', 'total', 'uuid_fiscal',
                'aprobada_costos', 'aceptada_contabilidad',
                'motivo_rechazo', 'fecha_pago_calculada',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Factura {$this->folio}: {$event}");
    }
}
