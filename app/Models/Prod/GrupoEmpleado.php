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
        'porcentaje',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
        ];
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }
}
