<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoExtra extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\PagoExtraFactory> */
    use HasFactory;

    protected $table = 'prod_pagos_extra';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'destajo_id',
        'dest_grupo_id',
        'tipo_id',
        'descripcion',
        'precio',
        'dias',
        'personas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'dias' => 'integer',
            'personas' => 'integer',
        ];
    }

    public function destajo(): BelongsTo
    {
        return $this->belongsTo(Destajo::class, 'destajo_id');
    }

    public function destGrupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'dest_grupo_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(Tipo::class, 'tipo_id');
    }
}
