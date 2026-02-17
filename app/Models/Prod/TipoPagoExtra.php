<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPagoExtra extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\TipoPagoExtraFactory> */
    use HasFactory;

    protected $table = 'prod_tipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'orden',
        'desgloce',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'desgloce' => 'boolean',
        ];
    }
}
