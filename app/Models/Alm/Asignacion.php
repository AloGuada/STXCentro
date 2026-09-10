<?php

namespace App\Models\Alm;

use App\Models\Obra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuánto del saldo de una existencia está comprometido con una obra.
 *
 * No es un inventario aparte: es la partición del renglón de `alm_existencias`,
 * igual que `Activo` es su desglose por pieza. El saldo lo sigue llevando la
 * existencia; esto responde de quién es.
 *
 * Lo **libre** no tiene renglón aquí: es lo que sobra
 * (`existencia.cantidad − SUM(asignaciones)`). Guardarlo sería una tercera
 * verdad que mantener.
 *
 * @use HasFactory<\Database\Factories\Alm\AsignacionFactory>
 */
class Asignacion extends Model
{
    use HasFactory;

    protected $table = 'alm_asignaciones';

    /**
     * Sin `cantidad`: la escribe sólo `App\Services\Alm\AlmacenLedger`, en la
     * misma transacción que mueve el saldo. Está fuera del `$fillable` por lo
     * mismo que las tres columnas de `Existencia` — para que un `update()`
     * distraído no pueda romper el invariante `SUM(asignaciones) <= cantidad`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'existencia_id',
        'obra_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Existencia, $this>
     */
    public function existencia(): BelongsTo
    {
        return $this->belongsTo(Existencia::class);
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Las que todavía comprometen algo. Un renglón en cero no se borra —vuelve
     * a servir en cuanto esa obra reciba material otra vez, y borrarlo obligaría
     * al ledger a recrearlo— pero no cuenta como asignación viva.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVivas(Builder $query): Builder
    {
        return $query->where('cantidad', '>', 0);
    }
}
