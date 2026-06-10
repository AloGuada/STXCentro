<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\FleteViaticoCatalogoFactory>
 */
class FleteViaticoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'cotiz_fletes_viaticos_catalogo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo',
        'orden',
        'concepto',
        'unidad',
        'p_unit_default',
        'notas',
        'clave',
        'formula_cantidad',
        'formula_p_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grupo' => GrupoFlete::class,
            'orden' => 'integer',
            'p_unit_default' => 'decimal:4',
        ];
    }
}
