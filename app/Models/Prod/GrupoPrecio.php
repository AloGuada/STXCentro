<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoPrecio extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioFactory> */
    use HasFactory;

    protected $table = 'prod_grupos_precio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'descripcion',
    ];

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /**
     * Tarifa por kilo de cada proceso. El grupo ya no tiene un precio único:
     * soldar y pintar la misma pieza no valen lo mismo.
     */
    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(Proceso::class, 'prod_grupo_precio_procesos', 'grupo_precio_id', 'proceso_id')
            ->withPivot('precio_kilo')
            ->withTimestamps();
    }

    public function precios(): HasMany
    {
        return $this->hasMany(GrupoPrecioProceso::class, 'grupo_precio_id');
    }

    /** Tarifa vigente para un proceso; 0 si al grupo le falta capturarla. */
    public function precioKilo(int $procesoId): float
    {
        return (float) ($this->precios->firstWhere('proceso_id', $procesoId)?->precio_kilo ?? 0);
    }

    public function grupoPrecioConceptos(): HasMany
    {
        return $this->hasMany(GrupoPrecioConcepto::class, 'grupo_precio_id');
    }

    public function conceptos(): BelongsToMany
    {
        return $this->belongsToMany(Concepto::class, 'prod_grupo_precio_conceptos', 'grupo_precio_id', 'concepto_id');
    }
}
