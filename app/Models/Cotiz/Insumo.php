<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Cotiz\InsumoFactory>
 */
class Insumo extends Model
{
    use HasFactory;

    protected $table = 'cotiz_insumos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'codigo_stumis',
        'unidad_id',
        'precio_unitario',
        'peso_lineal',
        'peso_default',
        'centro_costo_id',
        'categoria_tarjeta_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:4',
            'peso_lineal' => 'decimal:6',
            'peso_default' => 'decimal:6',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function categoriaTarjeta(): BelongsTo
    {
        return $this->belongsTo(CategoriaTarjeta::class, 'categoria_tarjeta_id');
    }

    public function factores(): HasMany
    {
        return $this->hasMany(Factor::class, 'insumo_id');
    }
}
