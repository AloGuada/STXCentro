<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'cob_eventos';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'parent_id',
        'nombre',
        'monto',
        'inicio',
        'fin',
        'marcado',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fin' => 'date',
            'monto' => 'decimal:2',
            'marcado' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Evento::class, 'parent_id');
    }
}
