<?php

namespace App\Models\Costos;

use App\Enums\Costos\ComplementoPagoEstatus;
use App\Enums\Costos\DocumentoTipo;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Obligación de complemento de pago (CFDI PPD): por cada pago ejecutado contra
 * una factura PPD, el proveedor debe emitir un complemento. Mientras esté
 * `pendiente` o `vencido` bloquea al proveedor; al recibirse y validarse el
 * complemento pasa a `cumplido` y se adjuntan XML/PDF vía Media.
 *
 * @use HasFactory<\Database\Factories\Costos\ComplementoPagoFactory>
 */
class ComplementoPago extends Model
{
    use HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_complementos_pago';

    protected static string $folioPrefix = 'CP';

    protected static string $stateEnum = ComplementoPagoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'factura_id',
        'pago_id',
        'proveedor_id',
        'monto_pago',
        'fecha_pago',
        'fecha_generacion',
        'fecha_limite',
        'estatus',
        'complemento_uuid',
        'recibido_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_pago' => 'decimal:2',
            'fecha_pago' => 'date',
            'fecha_generacion' => 'date',
            'fecha_limite' => 'date',
            'recibido_at' => 'datetime',
            'estatus' => ComplementoPagoEstatus::class,
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function mediaXml(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('descripcion', DocumentoTipo::XmlComplementoPago->value);
    }

    public function mediaPdf(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('descripcion', DocumentoTipo::PdfComplementoPago->value);
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'monto_pago', 'factura_id', 'complemento_uuid'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Complemento de pago {$this->folio}: {$event}");
    }
}
