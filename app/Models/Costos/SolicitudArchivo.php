<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudArchivo extends Model
{
    protected $table = 'costos_solicitud_archivos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'solicitud_id',
        'archivo_id',
        'ruta_archivo',
        'nombre_original',
        'tags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudPago::class, 'solicitud_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'archivo_id');
    }
}
