<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoSeccion extends Model
{
    use HasFactory;

    protected $table = 'cob_documento_secciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function carpetas(): HasMany
    {
        return $this->hasMany(DocumentoCarpeta::class, 'seccion_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(DocumentoArchivo::class, 'seccion_id');
    }

    /**
     * @param  Builder<DocumentoSeccion>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', true);
    }
}
