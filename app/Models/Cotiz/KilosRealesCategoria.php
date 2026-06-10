<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\TipoCorte;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\KilosRealesCategoriaFactory>
 */
class KilosRealesCategoria extends Model
{
    use HasFactory;

    protected $table = 'cotiz_kilos_reales_categorias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'tipo_corte',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_corte' => TipoCorte::class,
            'orden' => 'integer',
        ];
    }
}
