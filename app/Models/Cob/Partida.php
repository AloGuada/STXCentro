<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partida extends Model
{
    use HasFactory;

    protected $table = 'cob_partidas';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'tipo',
        'es_adicional',
        'descripcion',
        'monto',
        'moneda',
        'es_subobra',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'es_adicional' => 'boolean',
            'es_subobra' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
