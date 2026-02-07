<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckEjecucion extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\CheckEjecucionFactory> */
    use HasFactory;

    protected $table = 'sti_check_ejecuciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mantenimiento_id',
        'check_id',
        'resultado',
        'observaciones',
        'tecnico_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => 'boolean',
        ];
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class, 'mantenimiento_id');
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class, 'check_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }
}
