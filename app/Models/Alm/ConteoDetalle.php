<?php

namespace App\Models\Alm;

use App\Models\Concerns\LlenaLlavesDeItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón de la hoja: un artículo que toca contar, en el orden en que se
 * imprime. Lo contado y el saldo del sistema nacen vacíos y se llenan al
 * capturar (ver la migración).
 */
class ConteoDetalle extends Model
{
    use LlenaLlavesDeItem;

    /**
     * @return list<string>
     */
    protected static function llavesLegado(): array
    {
        return ['articulo_id'];
    }

    protected $table = 'alm_conteo_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conteo_id',
        'item_id',
        'articulo_id',
        'existencia_id',
        'orden',
        'cantidad_sistema',
        'cantidad_contada',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cantidad_sistema' => 'decimal:4',
            'cantidad_contada' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Conteo, $this>
     */
    public function conteo(): BelongsTo
    {
        return $this->belongsTo(Conteo::class);
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * El renglón de saldo del que salió; de ahí se lee dónde está guardado.
     *
     * @return BelongsTo<Existencia, $this>
     */
    public function existencia(): BelongsTo
    {
        return $this->belongsTo(Existencia::class);
    }
}
