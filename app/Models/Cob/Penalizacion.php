<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penalizacion extends Model
{
    use HasFactory;

    protected $table = 'cob_penalizaciones';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'descripcion',
        'monto',
        'moneda',
        'tipo',
        'fecha',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
