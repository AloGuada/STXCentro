<?php

namespace App\Models\Cob;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anticipo extends Model
{
    use HasFactory;

    protected $table = 'cob_anticipos';

    /** @var list<string> */
    protected $fillable = [
        'obra_id',
        'folio',
        'fecha_emision',
        'monto',
        'moneda',
        'estado',
        'comentarios',
        'fecha_pagado',
        'comprobante',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_pagado' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
