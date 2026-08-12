<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Responsable de módulo. Se elige en 2ª transformación.
 *
 * @use HasFactory<\Database\Factories\Qal\ResponsableFactory>
 */
class Responsable extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_responsables';

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
