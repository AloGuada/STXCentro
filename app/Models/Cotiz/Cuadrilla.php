<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Cotiz\CuadrillaFactory>
 */
class Cuadrilla extends Model
{
    use HasFactory;

    protected $table = 'cotiz_cuadrillas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'centro_costo_id',
        'insumo_id',
        'rendimiento',
        'formula_costo',
        'descripcion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rendimiento' => 'decimal:6',
        ];
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
