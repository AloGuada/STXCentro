<?php

namespace App\Models\Cotiz;

use App\Models\Concerns\HasEditLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generadora de una obra: agrupa los registros de material que producen kilos.
 * Editable bajo lock estricto por usuario (HasEditLock).
 *
 * @use HasFactory<\Database\Factories\Cotiz\GeneradoraFactory>
 */
class Generadora extends Model
{
    use HasEditLock, HasFactory;

    protected $table = 'cotiz_generadoras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'titulo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(GeneradoraRegistro::class, 'generadora_id');
    }
}
