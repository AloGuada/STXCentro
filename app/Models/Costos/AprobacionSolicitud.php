<?php

namespace App\Models\Costos;

use App\Enums\Costos\AprobacionEstatus;
use App\Models\Concerns\HasStateMachine;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AprobacionSolicitud extends Model
{
    use HasStateMachine;

    protected $table = 'costos_aprobaciones_solicitud';

    protected static string $stateEnum = AprobacionEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
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

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobador_id');
    }
}
