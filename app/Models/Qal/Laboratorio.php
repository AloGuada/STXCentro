<?php

namespace App\Models\Qal;

use App\Models\Qal\Concerns\EsCatalogoDeCalidad;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Laboratorio que firma los informes de ensayos no destructivos.
 *
 * Las siglas son como se le nombra dentro del informe; el nombre completo sale
 * en la portada del dosier.
 *
 * @use HasFactory<\Database\Factories\Qal\LaboratorioFactory>
 */
class Laboratorio extends Model
{
    use EsCatalogoDeCalidad, HasFactory;

    protected $table = 'qal_laboratorios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'siglas',
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
