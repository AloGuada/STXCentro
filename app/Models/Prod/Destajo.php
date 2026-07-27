<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destajo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\DestajoFactory> */
    use HasFactory;

    protected $table = 'prod_destajos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'anio',
        'semana',
        'fecha_inicio',
        'fecha_fin',
        'cerrado',
        'fecha_cierre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'semana' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'cerrado' => 'boolean',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'destajo_id');
    }

    public function pagosExtra(): HasMany
    {
        return $this->hasMany(PagoExtra::class, 'destajo_id');
    }
}
