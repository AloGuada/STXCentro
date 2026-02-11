<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoPrecio extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioFactory> */
    use HasFactory;

    protected $table = 'prod_grupos_precio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'descripcion',
        'precio_kilo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_kilo' => 'decimal:4',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function grupoPrecioConceptos(): HasMany
    {
        return $this->hasMany(GrupoPrecioConcepto::class, 'grupo_precio_id');
    }

    public function conceptos(): BelongsToMany
    {
        return $this->belongsToMany(Concepto::class, 'prod_grupo_precio_conceptos', 'grupo_precio_id', 'concepto_id');
    }
}
