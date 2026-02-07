<?php

namespace App\Models\Sti;

use App\Models\Departamento;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AsignacionActivo extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\AsignacionActivoFactory> */
    use HasFactory;

    protected $table = 'sti_asignacion_activos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'departamento_id',
        'equipo_id',
        'no_empleado',
        'empleado',
        'firma_empleado',
        'no_ti',
        'nombre_ti',
        'firma_ti',
        'fecha_inicial',
        'fecha_termino',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicial' => 'date',
            'fecha_termino' => 'date',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
