<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $table = 'costos_documentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tipo_solicitud_id',
        'titulo',
        'multiple',
        'texto',
        'texto_adicional',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'texto_adicional' => 'boolean',
        ];
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'tipo_solicitud_id');
    }
}
