<?php

namespace App\Models\Cob;

use App\Models\Costos\ObraRubro;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partida extends Model
{
    use HasFactory;

    protected $table = 'cob_partidas';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'tipo',
        'es_adicional',
        'estatus',
        'numero_adicional',
        'descripcion',
        'monto',
        'moneda',
        'es_subobra',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'es_adicional' => 'boolean',
            'es_subobra' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /**
     * Presupuesto propio del adicional (centros de costo).
     */
    public function obraRubros(): HasMany
    {
        return $this->hasMany(ObraRubro::class, 'adicional_partida_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAdicionales(Builder $query): Builder
    {
        return $query->where('es_adicional', true);
    }

    /**
     * Etiqueta corta del adicional ("ad1", "ad2", ...). Null si no es adicional.
     */
    public function getNumeroAdicionalLabelAttribute(): ?string
    {
        return $this->numero_adicional !== null ? "ad{$this->numero_adicional}" : null;
    }
}
