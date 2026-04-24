<?php

namespace App\Models\Costos;

use App\Enums\Costos\BaseDiasCredito;
use App\Enums\Costos\FacturaEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
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
        'total',
        'moneda',
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
     * Calcula la fecha tentativa de pago aplicando los días de crédito sobre
     * la base configurada (factura, recepción o aprobación) y ajustando al
     * próximo viernes hábil si la fecha resultante no cae en viernes.
     *
     * Retorna null si falta información para calcular (ej. base=aprobacion
     * pero la factura aún no ha sido aprobada por costos).
     */
    public function calcularFechaPago(): ?Carbon
    {
        $base = $this->base_dias_credito ?? BaseDiasCredito::Factura;
        $dias = (int) ($this->dias_credito ?? $this->proveedor?->dias_credito_default ?? 0);

        $fechaBase = match ($base) {
            BaseDiasCredito::Factura => $this->fecha_factura,
            BaseDiasCredito::Recepcion => $this->entregas()->latest('fecha_entrega')->value('fecha_entrega'),
            BaseDiasCredito::Aprobacion => $this->aprobada_costos_at,
        };

        if (! $fechaBase) {
            return null;
        }

        $fecha = Carbon::parse($fechaBase)->addDays($dias);

        return $fecha->dayOfWeek === Carbon::FRIDAY
            ? $fecha
            : $fecha->next(Carbon::FRIDAY);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function mediaXml(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'xml');
    }

    public function mediaPdf(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'pdf');
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'factura_id');
    }

    public function pago(): MorphOne
    {
        return $this->morphOne(Pago::class, 'pagable');
    }

    public function aprobadaCostosPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobada_costos_por');
    }

    public function aceptadaContabilidadPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aceptada_contabilidad_por');
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
