<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\PinturaFormulaFactory>
 */
class PinturaFormula extends Model
{
    use HasFactory;

    protected $table = 'cotiz_pintura_formulas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'clave',
        'nombre',
        'formula',
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
}
