<?php

namespace App\Models\Alm;

use App\Models\Alm\Concerns\LlenaArticuloId;
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
    use LlenaArticuloId;

    protected $table = 'alm_ajuste_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ajuste_id',
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
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
