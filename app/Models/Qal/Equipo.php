<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Máquina de corte y habilitado. Se elige al capturar 1ª transformación.
 *
 * @use HasFactory<\Database\Factories\Qal\EquipoFactory>
 */
class Equipo extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_equipos';

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
