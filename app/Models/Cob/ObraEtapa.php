<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa PMO planificada de una obra: descripción libre + fechas plan. Una obra
 * puede tener varias.
 */
class ObraEtapa extends Model
{
    use HasFactory;

    protected $table = 'cob_obra_etapas';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'descripcion',
        'fecha_inicio_plan',
        'fecha_fin_plan',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio_plan' => 'date',
            'fecha_fin_plan' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
