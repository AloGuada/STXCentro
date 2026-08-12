<?php

namespace App\Models\Qal;

use App\Enums\Qal\MetodoPnd;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas pruebas de un método se pactaron con el cliente en una obra.
 *
 * Es el denominador del avance de PND: sin esto sólo se puede decir cuántas
 * pruebas se hicieron, no si se va al día.
 *
 * **La ausencia de fila significa «no entra en este contrato»**; una fila con
 * cero significa «se pactaron cero». No es lo mismo y el tablero los trata
 * distinto, así que este modelo nunca debe crear filas en cero por defecto.
 *
 * @use HasFactory<\Database\Factories\Qal\ObraPndPlanFactory>
 */
class ObraPndPlan extends Model
{
    use HasFactory;

    protected $table = 'qal_obra_pnd_plan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_obra_id',
        'metodo',
        'comprometidas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metodo' => MetodoPnd::class,
            'comprometidas' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'qal_obra_id');
    }
}
