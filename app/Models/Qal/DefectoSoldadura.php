<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Defecto de soldadura que puede marcar el inspector, con su contador.
 *
 * Renombrar uno no reescribe los registros ya guardados: conservan el texto
 * viejo y en los reportes saldrían como dos defectos distintos.
 *
 * @use HasFactory<\Database\Factories\Qal\DefectoSoldaduraFactory>
 */
class DefectoSoldadura extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_defectos_soldadura';

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
