<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\PlanFactory> */
    use HasFactory;

    protected $table = 'sti_planes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'equipo_id',
        'descripcion',
        'periodicidad',
        'fecha_inicial',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicial' => 'date',
            'periodicidad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class, 'plan_id')->orderBy('orden');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'plan_id');
    }
}
