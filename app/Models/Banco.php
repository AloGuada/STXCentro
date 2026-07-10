<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<\Database\Factories\BancoFactory>
 */
class Banco extends Model
{
    use HasFactory;

    protected $table = 'bancos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'digitos_cuenta',
        'es_pagador',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digitos_cuenta' => 'integer',
            'es_pagador' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class);
    }

    /**
     * @param  Builder<Banco>  $query
     */
    public function scopePagador(Builder $query): void
    {
        $query->where('es_pagador', true);
    }

    /**
     * El banco pagador de la empresa (con el que se emiten los pagos). Solo uno
     * puede estar marcado como pagador a la vez.
     */
    public static function pagadorActual(): ?self
    {
        return static::pagador()->first();
    }

    /**
     * Garantiza el invariante de pagador único: al marcar este banco como
     * pagador, desmarca cualquier otro.
     */
    public function asegurarPagadorUnico(): void
    {
        if ($this->es_pagador) {
            static::where('id', '!=', $this->id)
                ->where('es_pagador', true)
                ->update(['es_pagador' => false]);
        }
    }
}
