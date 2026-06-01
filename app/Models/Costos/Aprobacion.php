<?php

namespace App\Models\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Models\Concerns\HasStateMachine;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Aprobacion polimorfica. La columna `aprobable_type` + `aprobable_id`
 * apunta a cualquier modelo que implemente App\Contracts\Costos\Aprobable
 * (SolicitudPago, Requisicion, etc.). El campo `solicitud_id` queda como
 * legacy nullable mientras existan datos historicos; codigo nuevo solo
 * usa `aprobable`.
 */
class Aprobacion extends Model
{
    use HasStateMachine;

    protected $table = 'costos_aprobaciones';

    protected static string $stateEnum = AprobacionEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'aprobable_type',
        'aprobable_id',
        'solicitud_id',
        'nivel',
        'aprobador_id',
        'estatus',
        'fecha_respuesta',
        'observaciones',
        'motivo_rechazo',
        'ip',
        'hostname',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'fecha_respuesta' => 'datetime',
            'estatus' => AprobacionEstatus::class,
        ];
    }

    public function aprobable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Compat: si se crea con `solicitud_id` (camino legacy) y sin
     * `aprobable_type`, asumir SolicitudPago. Permite que tests y codigo
     * heredado sigan funcionando hasta migrar a la API polimorfica.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $aprobacion) {
            if (empty($aprobacion->aprobable_type) && ! empty($aprobacion->solicitud_id)) {
                $aprobacion->aprobable_type = SolicitudPago::class;
                $aprobacion->aprobable_id = $aprobacion->solicitud_id;
            }
        });
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }

    /**
     * Backwards-compat: rutas y vistas heredadas siguen pidiendo solicitud.
     * Si el aprobable es un SolicitudPago, devuelve la instancia.
     */
    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_id');
    }
}
