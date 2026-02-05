<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipo extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\EquipoFactory> */
    use HasFactory;

    protected $table = 'sti_equipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'serie',
        'marca',
        'factor_criticidad',
        'periodicidad_mantenimiento',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'periodicidad_mantenimiento' => 'integer',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'equipo_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'equipo_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionActivo::class, 'equipo_id');
    }
}
