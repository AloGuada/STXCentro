<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\CheckFactory> */
    use HasFactory;

    protected $table = 'sti_checks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'descripcion',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(CheckEjecucion::class, 'check_id');
    }
}
