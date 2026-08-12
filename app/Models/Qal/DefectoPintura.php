<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Defecto de pintura que puede marcar el inspector de 3ª transformación.
 *
 * Misma advertencia que en soldadura: renombrar uno no reescribe los registros
 * ya guardados.
 *
 * @use HasFactory<\Database\Factories\Qal\DefectoPinturaFactory>
 */
class DefectoPintura extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_defectos_pintura';

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
