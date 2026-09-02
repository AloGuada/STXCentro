<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un paso de un proceso dentro de un grupo de precios, con precio fijo por
 * pieza: armar, puntear y soldar no valen lo mismo aunque los tres sean
 * soldadura.
 *
 * Cuelga del grupo porque la lista de pasos depende del modelo que se fabrica,
 * y el grupo es justamente el que junta a las marcas que se trabajan igual.
 */
class GrupoPrecioSubproceso extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioSubprocesoFactory> */
    use HasFactory;

    protected $table = 'prod_grupo_precio_subprocesos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_precio_id',
        'proceso_id',
        'nombre',
        'orden',
        'precio',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function grupoPrecio(): BelongsTo
    {
        return $this->belongsTo(GrupoPrecio::class, 'grupo_precio_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'subproceso_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
