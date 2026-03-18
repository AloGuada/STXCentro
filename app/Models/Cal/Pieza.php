<?php

namespace App\Models\Cal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pieza extends Model
{
    use HasFactory;

    protected $table = 'cal_piezas';

    protected $fillable = [
        'marca',
        'cantidad',
        'etapa_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(Etapa::class, 'etapa_id');
    }

    public function planos(): HasMany
    {
        return $this->hasMany(PiezaPlano::class, 'pieza_id');
    }
}
