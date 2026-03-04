<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoDemostrado extends Model
{
    protected $table = 'rh_requerimientos_demostrados';

    /** @var list<string> */
    protected $fillable = [
        'persona_id',
        'requerimiento_id',
        'cumple',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cumple' => 'boolean',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }
}
