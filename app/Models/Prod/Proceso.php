<?php

namespace App\Models\Prod;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proceso por el que pasa una pieza y que se paga como destajo: soldadura,
 * pintura y los que se agreguen después.
 *
 * Cada QS se paga a lo más una vez por proceso, con su propia tarifa por kilo.
 */
class Proceso extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\ProcesoFactory> */
    use HasFactory;

    protected $table = 'prod_procesos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Eventos del export de planta que disparan el pago de este proceso. Un
     * proceso sin eventos es válido: sólo se captura a mano.
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(ProcesoEvento::class, 'proceso_id');
    }

    public function obras(): BelongsToMany
    {
        return $this->belongsToMany(Obra::class, 'prod_obra_procesos', 'proceso_id', 'obra_id')->withTimestamps();
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'proceso_id');
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
