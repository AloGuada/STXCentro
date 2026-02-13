<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AprobacionSolicitud extends Model
{
    protected $table = 'costos_aprobaciones_solicitud';

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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'fecha_respuesta' => 'datetime',
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
