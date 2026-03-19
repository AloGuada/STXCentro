<?php

namespace App\Models\Cal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'cal_obras';

    protected $fillable = [
        'no',
        'descripcion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(Etapa::class, 'obra_id');
    }
}
