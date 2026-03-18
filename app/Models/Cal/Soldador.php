<?php

namespace App\Models\Cal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Soldador extends Model
{
    use HasFactory;

    protected $table = 'cal_soldadores';

    protected $fillable = [
        'nombre',
        'certificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class, 'soldador_id');
    }
}
