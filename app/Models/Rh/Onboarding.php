<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Onboarding extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\OnboardingFactory> */
    use HasFactory;

    protected $table = 'rh_onboarding';

    /** @var list<string> */
    protected $fillable = [
        'periodo_id',
        'fecha_inicio',
        'progreso',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date:Y-m-d',
            'progreso' => 'integer',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLaboral::class, 'periodo_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(OnboardingTarea::class, 'onboarding_id');
    }
}
