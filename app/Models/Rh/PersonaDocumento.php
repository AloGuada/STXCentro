<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaDocumento extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\PersonaDocumentoFactory> */
    use HasFactory;

    protected $table = 'rh_persona_documentos';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'media_id',
        'tipo_documento',
        'fecha_emision',
        'fecha_vigencia',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vigencia' => 'date',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Media::class, 'media_id');
    }
}
