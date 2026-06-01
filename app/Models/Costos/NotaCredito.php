<?php

namespace App\Models\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\NotaCreditoEstatus;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Nota de credito emitida por el proveedor sobre una factura previa.
 * Reduce el saldo pendiente de la factura cuando esta `vigente`. Si se
 * cancela (estatus=cancelada), deja de afectar el saldo.
 *
 * @use HasFactory<\Database\Factories\Costos\NotaCreditoFactory>
 */
class NotaCredito extends Model
{
    use HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_notas_credito';

    protected static string $folioPrefix = 'NC';

    protected static string $stateEnum = NotaCreditoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'factura_id',
        'uuid_fiscal',
        'folio_fiscal',
        'subtotal',
        'iva_trasladado',
        'monto',
        'impuestos_detalle',
        'concepto',
        'fecha_emision',
        'estatus',
        'motivo_cancelacion',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva_trasladado' => 'decimal:2',
            'monto' => 'decimal:2',
            'impuestos_detalle' => 'array',
            'fecha_emision' => 'date',
            'estatus' => NotaCreditoEstatus::class,
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function mediaXml(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('descripcion', DocumentoTipo::XmlNotaCredito->value);
    }

    public function mediaPdf(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('descripcion', DocumentoTipo::PdfNotaCredito->value);
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'monto', 'factura_id', 'uuid_fiscal'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Nota de crédito {$this->folio}: {$event}");
    }
}
