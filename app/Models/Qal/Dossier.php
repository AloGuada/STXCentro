<?php

namespace App\Models\Qal;

use App\Enums\Qal\EstatusDossier;
use App\Models\Obra as ObraDelPortal;
use App\Models\Usuario;
use App\Services\Qal\Dosier\ArbolDeSecciones;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * El dosier de calidad de una obra: su árbol de secciones, copiado de una
 * plantilla, y los PDF que se suben a cada una.
 *
 * @use HasFactory<\Database\Factories\Qal\DossierFactory>
 */
class Dossier extends Model
{
    use HasFactory;

    /** La carpeta del disco privado donde viven sus PDF. */
    public const CARPETA = 'qal/dosier';

    protected $table = 'qal_dossiers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'plantilla_id',
        'plantilla_nombre',
        'estatus',
        'entregado_at',
        'notas',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusDossier::class,
            'entregado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ObraDelPortal, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(ObraDelPortal::class, 'obra_id');
    }

    /**
     * @return BelongsTo<DossierPlantilla, $this>
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(DossierPlantilla::class, 'plantilla_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * @return HasMany<DossierSeccion, $this>
     */
    public function secciones(): HasMany
    {
        return $this->hasMany(DossierSeccion::class, 'dossier_id');
    }

    /**
     * @return HasManyThrough<DossierArchivo, DossierSeccion, $this>
     */
    public function archivos(): HasManyThrough
    {
        return $this->hasManyThrough(DossierArchivo::class, DossierSeccion::class, 'dossier_id', 'seccion_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function arbol(): array
    {
        return app(ArbolDeSecciones::class)->anidar($this->secciones);
    }

    public function carpeta(): string
    {
        return self::CARPETA.'/'.$this->id;
    }
}
