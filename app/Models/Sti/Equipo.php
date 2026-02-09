<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
    ];

    public const CRITICIDAD_LABELS = [
        1 => 'Bajo',
        2 => 'Medio',
        3 => 'Alto',
        4 => 'Critico',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_criticidad' => 'integer',
        ];
    }

    public function getCriticidadLabelAttribute(): string
    {
        return self::CRITICIDAD_LABELS[$this->factor_criticidad] ?? 'Desconocido';
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

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'equipo_id');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(Item::class, Grupo::class, 'equipo_id', 'id', 'id', 'item_id');
    }
}
