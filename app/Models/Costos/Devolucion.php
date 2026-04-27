<?php

namespace App\Models\Costos;

use App\Enums\Costos\DevolucionEstatus;
use App\Enums\Costos\DocumentoTipo;
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
 * Devolucion fisica al proveedor sobre una partida ya recibida. Reduce
 * la `cantidad_neta_recibida` del EntregaDetalle mientras este vigente;
 * cancelar la devolucion libera la cantidad. El ajuste contable
 * (nota de credito) se maneja por separado.
 *
 * @use HasFactory<\Database\Factories\Costos\DevolucionFactory>
 */
class Devolucion extends Model
{
    use HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_devoluciones';

    protected static string $folioPrefix = 'DV';

    protected static string $stateEnum = DevolucionEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'entrega_detalle_id',
        'cantidad',
        'motivo',
        'fecha',
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
            'cantidad' => 'decimal:2',
            'fecha' => 'date',
            'estatus' => DevolucionEstatus::class,
        ];
    }

    public function entregaDetalle(): BelongsTo
    {
        return $this->belongsTo(EntregaDetalle::class, 'entrega_detalle_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function evidencia(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')
            ->where('descripcion', DocumentoTipo::EvidenciaDevolucion->value);
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'cantidad', 'entrega_detalle_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Devolución {$this->folio}: {$event}");
    }
}
