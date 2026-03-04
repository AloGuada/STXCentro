<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermisoAusencia extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\PermisoAusenciaFactory> */
    use HasFactory;

    protected $table = 'rh_permisos_ausencia';

    /** @var list<string> */
    protected $fillable = [
        'folio',
        'numero_empleado',
        'nombres',
        'apellidos',
        'departamento',
        'gerente',
        'tipo',
        'modalidad',
        'condicion',
        'razon',
        'fecha_permiso',
        'fecha_elaboracion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_permiso' => 'date',
            'fecha_elaboracion' => 'date',
        ];
    }
}
