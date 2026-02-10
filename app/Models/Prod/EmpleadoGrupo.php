<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoGrupo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\EmpleadoGrupoFactory> */
    use HasFactory;

    protected $table = 'prod_empleados_grupo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_id',
        'nombre',
        'no_empleado',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }
}
