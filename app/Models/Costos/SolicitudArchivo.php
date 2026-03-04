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
        'media_id',
        'archivo_id',
        'texto_adicional',
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

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Media::class, 'media_id');
    }
}
