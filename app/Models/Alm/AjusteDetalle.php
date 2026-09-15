<?php

namespace App\Models\Alm;

use App\Models\Concerns\LlenaLlavesDeItem;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón contado.
 *
 * Guarda lo contado, lo que decía el sistema y la diferencia que se mandó al
 * kardex, aunque la tercera sea la resta de las otras dos: recalcularla después
 * daría otro número, porque el saldo ya se movió con este mismo ajuste.
 */
class AjusteDetalle extends Model
{
    use LlenaLlavesDeItem;

    protected $table = 'alm_ajuste_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ajuste_id',
        'item_id',
        'producto_id',
        'articulo_id',
        'cantidad_contada',
        'cantidad_sistema',
        'diferencia',
        'costo_unitario',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_contada' => 'decimal:4',
            'cantidad_sistema' => 'decimal:4',
            'diferencia' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Ajuste, $this>
     */
    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(Ajuste::class);
    }

    /**
     * Con qué guarda Almacén este renglón.
     *
     * Es la relación buena: `articulo_id` es la columna que queda cuando se
     * cierre la mudanza del catálogo. `producto()` sigue aquí sólo mientras
     * conviven las dos columnas.
     *
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * El producto de Compras. **Andamio**: se va con la columna en la fase B.
     * Lo que hoy se lea de aquí debe pasar a `articulo()`.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
