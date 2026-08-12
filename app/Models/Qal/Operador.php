<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Quién opera la máquina en corte y habilitado. Se elige en 1ª transformación.
 *
 * @use HasFactory<\Database\Factories\Qal\OperadorFactory>
 */
class Operador extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_operadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
