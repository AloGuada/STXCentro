<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Cotiz\FaseMontajeFactory>
 */
class FaseMontaje extends Model
{
    use HasFactory;

    protected $table = 'cotiz_fases_montaje';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'unidad',
        'centro_costo_id',
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

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }
}
