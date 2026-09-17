<?php

namespace App\Models\Qal;

use App\Services\Qal\Dosier\ArbolDeSecciones;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una plantilla del dosier: el árbol de secciones con que nace el dosier de
 * una obra. Al crear un dosier el árbol se copia; cambiar la plantilla después
 * no toca los dosieres que ya nacieron de ella.
 *
 * @use HasFactory<\Database\Factories\Qal\DossierPlantillaFactory>
 */
class DossierPlantilla extends Model
{
    use HasFactory;

    protected $table = 'qal_dossier_plantillas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * Todas las secciones, de cualquier nivel.
     *
     * @return HasMany<DossierPlantillaSeccion, $this>
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(DossierPlantillaSeccion::class, 'plantilla_id');
    }

    /**
     * El árbol anidado, con su número calculado por posición.
     *
     * @return list<array<string, mixed>>
     */
    public function arbol(): array
    {
        return app(ArbolDeSecciones::class)->anidar($this->secciones);
    }
}
