<?php

namespace App\Models\Qal;

use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un lote de accesorios: el total de unidades de una marca en una obra, que
 * llega en entregas (sublotes) y se libera por muestreo.
 *
 * @use HasFactory<\Database\Factories\Qal\LoteAccesorioFactory>
 */
class LoteAccesorio extends Model
{
    use HasFactory;

    protected $table = 'qal_lotes_accesorios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'concepto_id',
        'marca',
        'descripcion',
        'total_unidades',
        'kg_unitario',
        'elementos_unitarios',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_unidades' => 'integer',
            'kg_unitario' => 'decimal:3',
            'elementos_unitarios' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ObraDelPortal, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(ObraDelPortal::class, 'obra_id');
    }

    /**
     * La marca de Producción, cuando el lote existe en su catálogo vigente.
     *
     * @return BelongsTo<Concepto, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * Todas las inspecciones de sublote, reinspecciones incluidas.
     *
     * @return HasMany<Sublote, $this>
     */
    public function sublotes(): HasMany
    {
        return $this->hasMany(Sublote::class, 'lote_id');
    }

    /**
     * Cuánto del lote llegó y cuánto se liberó.
     *
     * Cada sublote cuenta una vez, con su última inspección: uno rechazado y
     * luego aceptado es material liberado, no una entrega doble. Lo recibido
     * que no se liberó está detenido hasta reinspeccionarse o liberarse bajo
     * concesión. Lee `sublotes` ya cargados.
     *
     * @return array{recibidas: int, liberadas: int, detenidas: int, sublotes: int, sin_disposicion: int, inspeccionadas: int, rechazadas: int}
     */
    public function avance(): array
    {
        $ultimas = $this->sublotes
            ->groupBy(fn (Sublote $sublote): int => $sublote->grupoId())
            ->map(fn ($grupo) => $grupo->sortBy('numero_inspeccion')->last());

        $recibidas = (int) $ultimas->sum('unidades');
        $liberadas = (int) $ultimas->filter(fn (Sublote $sublote): bool => $sublote->liberado())->sum('unidades');

        return [
            'recibidas' => $recibidas,
            'liberadas' => $liberadas,
            'detenidas' => $recibidas - $liberadas,
            'sublotes' => $ultimas->count(),
            'sin_disposicion' => $ultimas->filter(fn (Sublote $sublote): bool => $sublote->sinDisposicion())->count(),
            'inspeccionadas' => (int) $this->sublotes->sum('muestra'),
            'rechazadas' => (int) $this->sublotes->sum('rechazadas'),
        ];
    }
}
