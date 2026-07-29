<?php

namespace App\Models\Prod;

use App\Enums\Prod\EstadoAsistencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\AsistenciaFactory> */
    use HasFactory;

    protected $table = 'prod_asistencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'destajo_id',
        'grupo_empleado_id',
        'fecha',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoAsistencia::class,
        ];
    }

    public function destajo(): BelongsTo
    {
        return $this->belongsTo(Destajo::class, 'destajo_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(GrupoEmpleado::class, 'grupo_empleado_id');
    }
}
