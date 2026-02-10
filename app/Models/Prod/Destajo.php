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
        'semana',
        'cerrada',
        'cantidad',
        'fecha_cierre',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semana' => 'integer',
            'cerrada' => 'boolean',
            'cantidad' => 'double',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function fabricados(): HasMany
    {
        return $this->hasMany(Fabricado::class, 'destajo_id');
    }

    public function pagosExtra(): HasMany
    {
        return $this->hasMany(PagoExtra::class, 'destajo_id');
    }
}
