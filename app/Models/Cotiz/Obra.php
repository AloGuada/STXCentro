<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Obra/proyecto de cotización (tabla propia del módulo; no reutiliza `obras` del mono).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraFactory>
 */
class Obra extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'op',
        'factor_contratista',
        'num_grupos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_contratista' => 'decimal:4',
            'num_grupos' => 'integer',
        ];
    }

    public function generadoras(): HasMany
    {
        return $this->hasMany(Generadora::class, 'obra_id');
    }
}
