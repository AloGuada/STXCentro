<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Etapa extends Model
{
    use HasFactory;

    protected $table = 'qal_etapas';

    protected $fillable = [
        'descripcion',
        'obra_id',
    ];

    public function obra(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Qal\Obra::class, 'obra_id');
    }

    public function piezas(): HasMany
    {
        return $this->hasMany(Pieza::class, 'etapa_id');
    }
}
