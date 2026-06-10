<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\Cotiz\CategoriaTarjetaFactory>
 */
class CategoriaTarjeta extends Model
{
    use HasFactory;

    protected $table = 'cotiz_categorias_tarjeta';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'orden',
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

    public function insumos(): HasMany
    {
        return $this->hasMany(Insumo::class, 'categoria_tarjeta_id');
    }

    public function factores(): HasMany
    {
        return $this->hasMany(Factor::class, 'categoria_tarjeta_id');
    }
}
