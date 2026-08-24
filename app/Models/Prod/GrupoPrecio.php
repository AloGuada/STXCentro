<?php

namespace App\Models\Prod;

use App\Enums\Prod\TipoPago;
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
        'tipo_pago',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_pago' => TipoPago::class,
        ];
    }

    /**
     * Las dos modalidades son excluyentes: preguntar por esta bandera es la
     * unica forma valida de saber de donde sale el importe de un renglon.
     */
    public function pagaPorSubproceso(): bool
    {
        return $this->tipo_pago === TipoPago::Subproceso;
    }

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

    /**
     * Pasos con precio fijo por pieza. Solo tienen sentido si el grupo paga por
     * subproceso; en un grupo por kilo la relacion viene vacia.
     */
    public function subprocesos(): HasMany
    {
        return $this->hasMany(GrupoPrecioSubproceso::class, 'grupo_precio_id');
    }

    /** Precio fijo de un subproceso; 0 si ya no pertenece a este grupo. */
    public function precioSubproceso(int $subprocesoId): float
    {
        return (float) ($this->subprocesos->firstWhere('id', $subprocesoId)?->precio ?? 0);
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
