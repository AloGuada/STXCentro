<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composición de la cuadrilla global de montaje por categoría (personas por grupo).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraCuadrillaGlobalFactory>
 */
class ObraCuadrillaGlobal extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_cuadrilla_global';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'categoria_id',
        'cantidad_por_grupo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_por_grupo' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(PersonalCategoria::class, 'categoria_id');
    }
}
