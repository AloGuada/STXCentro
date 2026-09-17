<?php

namespace App\Models\Costos;

use App\Enums\Costos\CancelacionUnidadesEstatus;
use App\Models\Concerns\HasStateMachine;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Unidades que compras dio por canceladas en una partida de una orden ya
 * emitida, por ejemplo las 40 piezas que faltan de una partida de 100 con 60
 * recibidas.
 *
 * Nace pendiente y no surte ningún efecto hasta que el jefe de compras la
 * autoriza: mientras esté pendiente, la orden se reporta como pendiente de
 * aprobación. Quien aplica los efectos es
 * {@see \App\Services\Costos\CanceladorDeUnidades}.
 *
 * @use HasFactory<\Database\Factories\Costos\OrdenCompraDetalleCancelacionFactory>
 */
class OrdenCompraDetalleCancelacion extends Model
{
    use HasFactory, HasStateMachine, LogsActivity;

    protected $table = 'costos_oc_detalle_cancelaciones';

    protected static string $stateEnum = CancelacionUnidadesEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'orden_compra_detalle_id',
        'cantidad',
        'motivo',
        'estatus',
        'solicitado_por',
        'autorizado_por',
        'autorizado_at',
        'motivo_rechazo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'estatus' => CancelacionUnidadesEstatus::class,
            'autorizado_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendiente(Builder $query): Builder
    {
        return $query->where('estatus', CancelacionUnidadesEstatus::Pendiente->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAutorizada(Builder $query): Builder
    {
        return $query->where('estatus', CancelacionUnidadesEstatus::Autorizada->value);
    }

    /**
     * @return BelongsTo<OrdenCompraDetalle, $this>
     */
    public function detalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class, 'orden_compra_detalle_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['estatus', 'cantidad', 'orden_compra_detalle_id', 'autorizado_por'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Cancelación de unidades: {$event}");
    }
}
