<?php

namespace App\Models\Sti;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mantenimiento extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\MantenimientoFactory> */
    use HasFactory;

    protected $table = 'sti_mantenimientos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'equipo_id',
        'plan_id',
        'fecha_programada',
        'descripcion',
        'tecnico_id',
        'status',
        'fecha_realizado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_realizado' => 'date',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function costos(): MorphMany
    {
        return $this->morphMany(CostoMantenimiento::class, 'costeable');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function checkEjecuciones(): HasMany
    {
        return $this->hasMany(CheckEjecucion::class, 'mantenimiento_id');
    }

    public function estaCompleto(): bool
    {
        if ($this->checkEjecuciones()->count() === 0) {
            return true;
        }

        return $this->checkEjecuciones()->where('resultado', false)->count() === 0;
    }
}
