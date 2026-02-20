<?php

namespace App\Models\Infra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoDia extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TurnoDiaFactory> */
    use HasFactory;

    protected $table = 'infra_turnos_dia';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'infra_turno_id',
        'dia_semana',
    ];

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'infra_turno_id');
    }
}
