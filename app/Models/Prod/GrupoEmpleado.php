<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoEmpleado extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoEmpleadoFactory> */
    use HasFactory;

    protected $table = 'prod_grupo_empleados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_trabajo_id',
        'nombre',
        'no_empleado',
        'categoria_empleado_id',
    ];

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }

    /** Peso con el que participa en el reparto del excedente del destajo. */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaEmpleado::class, 'categoria_empleado_id');
    }
}
