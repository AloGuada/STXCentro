<?php

namespace App\Models\Intra;

use App\Enums\TipoDocumento;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'intra_documentos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'area_id',
        'descripcion',
        'codigo',
        'tipo',
        'order',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumento::class,
            'order' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function media(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable');
    }
}
