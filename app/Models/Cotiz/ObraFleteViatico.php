<?php

namespace App\Models\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de fletes y viáticos de una obra (instancia del catálogo global, con fórmulas M043).
 * importe = cantidad × p_unit (derivado en PHP).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraFleteViaticoFactory>
 */
class ObraFleteViatico extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_fletes_viaticos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'grupo',
        'orden',
        'concepto',
        'unidad',
        'cantidad',
        'p_unit',
        'notas',
        'clave',
        'formula_cantidad',
        'formula_p_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grupo' => GrupoFlete::class,
            'orden' => 'integer',
            'cantidad' => 'decimal:4',
            'p_unit' => 'decimal:4',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
