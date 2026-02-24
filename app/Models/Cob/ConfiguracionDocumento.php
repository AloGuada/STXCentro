<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfiguracionDocumento extends Model
{
    use HasFactory;

    protected $table = 'cob_configuracion_documentos';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'nombre_documento',
        'descripcion',
        'obligatorio',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'obligatorio' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function documentosEstimacion(): HasMany
    {
        return $this->hasMany(DocumentoEstimacion::class, 'configuracion_documento_id');
    }
}
