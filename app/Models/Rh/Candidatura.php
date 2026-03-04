<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidatura extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\CandidaturaFactory> */
    use HasFactory;

    protected $table = 'rh_candidaturas';

    /** @var list<string> */
    protected $fillable = [
        'requisicion_id',
        'persona_id',
        'fecha_aplicacion',
        'porcentaje_match',
        'porcentaje_skills',
        'porcentaje_requisitos',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_aplicacion' => 'date',
            'porcentaje_match' => 'decimal:2',
            'porcentaje_skills' => 'decimal:2',
            'porcentaje_requisitos' => 'decimal:2',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
