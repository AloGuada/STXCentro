<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadgeConfig extends Model
{
    /** @use HasFactory<\Database\Factories\BadgeConfigFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tabla',
        'campo_estatus',
        'operador',
        'valor_estatus',
        'condiciones_extra',
        'rol',
        'nav_href',
        'filter_href',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condiciones_extra' => 'array',
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  Builder<BadgeConfig>  $query
     */
    public function scopeActivo(Builder $query): void
    {
        $query->where('activo', true);
    }
}
