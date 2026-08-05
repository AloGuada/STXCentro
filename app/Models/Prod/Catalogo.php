<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de piezas de una obra. Cada obra tiene un solo catálogo vigente;
 * las versiones anteriores se conservan congeladas para consulta e historia.
 */
class Catalogo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\CatalogoFactory> */
    use HasFactory;

    protected $table = 'prod_catalogos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'catalogo_origen_id',
        'nombre',
        'version',
        'vigente',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'vigente' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /** Versión de la que se copió este catálogo. */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'catalogo_origen_id');
    }

    /** Todas las piezas (QS) del catálogo, sin importar de qué marca cuelgan. */
    public function piezas(): HasMany
    {
        return $this->hasMany(Pieza::class, 'catalogo_id');
    }

    /** Las marcas del catálogo. */
    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class, 'catalogo_id');
    }

    /**
     * Todas las versiones del catálogo de la misma obra, de la más nueva a la
     * más vieja.
     *
     * @return HasMany<self, $this>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(self::class, 'obra_id', 'obra_id')->orderByDesc('version');
    }
}
