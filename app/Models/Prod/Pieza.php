<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pieza física del catálogo, identificada por su QS.
 *
 * Es el último nivel de la jerarquía Obra → Catálogo → Marca → Pieza: la marca
 * (`concepto`) describe el modelo una vez y aquí cuelgan sus unidades, una por
 * cada QS que trae el layout. El destajo se paga contra esta fila, no contra la
 * marca, así se sabe con exactitud qué se pagó.
 */
class Pieza extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\PiezaFactory> */
    use HasFactory;

    protected $table = 'prod_piezas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'catalogo_id',
        'concepto_id',
        'qs',
        'pieza_origen_id',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    /** La marca: el modelo del que esta pieza es una unidad. */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    /**
     * Pieza de la que se copió ésta al versionar el catálogo. Sostiene el conteo
     * de lo pagado: sin el linaje, versionar reiniciaría el avance y se podría
     * volver a pagar lo ya fabricado.
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pieza_origen_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'pieza_id');
    }

    /**
     * Sólo las piezas del catálogo vigente de su obra. Tras versionar, el mismo
     * QS existe en varias versiones y sin este filtro queda ambiguo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeDeCatalogoVigente($query)
    {
        return $query->whereHas('catalogo', fn ($q) => $q->where('vigente', true));
    }

    /** Cómo se nombra la pieza en pantalla: el modelo más su QS. */
    public function etiqueta(): string
    {
        $modelo = $this->marca !== null
            ? Concepto::etiquetaDeModelo($this->marca->marca, $this->marca->etapa)
            : '';

        return trim($modelo === '' ? "QS {$this->qs}" : "{$modelo} · QS {$this->qs}");
    }
}
