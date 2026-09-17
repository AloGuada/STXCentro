<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una sección de una plantilla del dosier. No guarda su número: sale de su
 * posición en el árbol (`padre_id` y `orden`).
 *
 * @use HasFactory<\Database\Factories\Qal\DossierPlantillaSeccionFactory>
 */
class DossierPlantillaSeccion extends Model
{
    use HasFactory;

    protected $table = 'qal_dossier_plantilla_secciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plantilla_id',
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
     * @return BelongsTo<DossierPlantilla, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DossierPlantilla::class, 'plantilla_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id')->orderBy('orden');
    }
}
