<?php

namespace App\Models\Costos;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<\Database\Factories\Costos\ObraRubroFactory>
 */
class ObraRubro extends Model
{
    use HasFactory;

    protected $table = 'costos_obra_rubros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'rubro_id',
        'presupuestado',
        'acumulado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presupuestado' => 'decimal:2',
            'acumulado' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }
}
