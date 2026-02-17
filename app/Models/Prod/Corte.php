<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Corte extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\CorteFactory> */
    use HasFactory;

    protected $table = 'prod_cortes';

    /**
     * @var list<string>
     */
    protected $fillable = [
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
            'semana' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'cerrado' => 'boolean',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'corte_id');
    }

    public function pagosExtra(): HasMany
    {
        return $this->hasMany(PagoExtra::class, 'corte_id');
    }
}
