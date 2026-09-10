<?php

namespace App\Models\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Catalogo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * El modelo 3D de una obra, en una de sus versiones.
 *
 * El IFC original se guarda en el disco privado; lo que devuelve la conversión
 * (un .glb y un .json por marca) va al público, porque el visor los pide
 * directo.
 *
 * @use HasFactory<\Database\Factories\Qal\ModeloFactory>
 */
class Modelo extends Model
{
    use HasFactory;

    protected $table = 'qal_modelos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'catalogo_id',
        'version',
        'archivo_ifc',
        'nombre_original',
        'tamano_bytes',
        'estatus',
        'trabajo_externo_id',
        'welds_version',
        'resumen',
        'error',
        'procesado_at',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => EstatusModelo::class,
            'version' => 'integer',
            'tamano_bytes' => 'integer',
            'resumen' => 'array',
            'procesado_at' => 'datetime',
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
     * @return BelongsTo<Catalogo, $this>
     */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * @return HasMany<ModeloMarca, $this>
     */
    public function marcas(): HasMany
    {
        return $this->hasMany(ModeloMarca::class, 'modelo_id');
    }

    /**
     * @return HasManyThrough<ModeloCordon, ModeloMarca, $this>
     */
    public function cordones(): HasManyThrough
    {
        return $this->hasManyThrough(ModeloCordon::class, ModeloMarca::class, 'modelo_id', 'modelo_marca_id');
    }

    /** Dónde queda lo convertido, en el disco público. */
    public function carpeta(): string
    {
        return "qal/modelos/{$this->id}";
    }

    /** Si ya hay juntas capturadas sobre alguno de sus cordones. */
    public function tieneJuntas(): bool
    {
        return Junta::query()
            ->whereIn('cordon_id', $this->cordones()->select('qal_modelo_cordones.id'))
            ->exists();
    }
}
