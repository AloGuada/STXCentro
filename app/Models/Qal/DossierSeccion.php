<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una sección del dosier de una obra. Como en la plantilla, su número sale de
 * su posición en el árbol.
 *
 * @use HasFactory<\Database\Factories\Qal\DossierSeccionFactory>
 */
class DossierSeccion extends Model
{
    use HasFactory;

    protected $table = 'qal_dossier_secciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dossier_id',
        'padre_id',
        'orden',
        'titulo',
        'nota',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class, 'dossier_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    /**
     * @return HasMany<DossierArchivo, $this>
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(DossierArchivo::class, 'seccion_id')->orderBy('orden');
    }
}
