<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Supervisor de pintura. Se elige en 3ª transformación, en el lugar que en 2ª
 * ocupa el responsable de módulo.
 *
 * @use HasFactory<\Database\Factories\Qal\SupervisorPinturaFactory>
 */
class SupervisorPintura extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_supervisores_pintura';

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
