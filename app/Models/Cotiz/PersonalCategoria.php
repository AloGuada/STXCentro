<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<\Database\Factories\Cotiz\PersonalCategoriaFactory>
 */
class PersonalCategoria extends Model
{
    use HasFactory;

    protected $table = 'cotiz_personal_categorias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'sueldo_semanal',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sueldo_semanal' => 'decimal:2',
            'orden' => 'integer',
        ];
    }
}
