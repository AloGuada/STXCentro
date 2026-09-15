<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pieza física del catálogo, identificada por su QR.
 *
 * Es el último nivel de la jerarquía Obra → Catálogo → Marca → Pieza: la marca
 * (`concepto`) describe el modelo una vez y aquí cuelgan sus unidades, una por
 * cada QR que trae el layout. El destajo se paga contra esta fila, no contra la
 * marca, así se sabe con exactitud qué se pagó.
 *
 * El QS se conserva como dato de planta, pero ya no identifica: puede repetirse
 * entre lotes. Lo único único dentro del catálogo es el QR.
 */
class Pieza extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\PiezaFactory> */
    use HasFactory;

    protected $table = 'prod_piezas';

    /**
     * Con lo que arranca el QR de una pieza que el layout trajo sin QR ni QS.
     * Planta no siempre tiene etiquetada la pieza cuando manda el layout, y
     * el modelo tiene que contarla igual: la cantidad es el conteo de
     * renglones. El identificador es provisional y determinista (marca, lote
     * y consecutivo dentro del archivo) para que recargar el mismo layout no
     * duplique; cuando planta le asigne QR, recargar lo reemplaza.
     */
    public const PREFIJO_SIN_QR = 'SIN QR #';

    /** El QR provisional de la pieza `n` de un modelo que vino sin QR. */
    public static function qrProvisional(string $marca, ?string $lote, int $n): string
    {
        $qr = self::PREFIJO_SIN_QR.$n.' '.$marca.($lote === null ? '' : ' '.$lote);

        return mb_strimwidth($qr, 0, 100);
    }

    /** Si el layout no le puso QR y trae el provisional. */
    public function sinQr(): bool
    {
        return str_starts_with((string) $this->qr, self::PREFIJO_SIN_QR);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'catalogo_id',
        'concepto_id',
        'qr',
        'qs',
        'correlativo',
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
     * QR existe en varias versiones y sin este filtro queda ambiguo.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeDeCatalogoVigente($query)
    {
        return $query->whereHas('catalogo', fn ($q) => $q->where('vigente', true));
    }

    /**
     * Cómo se nombra la pieza en pantalla: el modelo más el QS, que es el número
     * con el que la gente de planta la busca. El QR identifica pero no se lee.
     */
    public function etiqueta(): string
    {
        $modelo = $this->marca !== null
            ? Concepto::etiquetaDeModelo($this->marca->marca, $this->marca->lote)
            : '';

        $pieza = match (true) {
            $this->qs !== null && $this->qs !== '' => "QS {$this->qs}",
            $this->sinQr() => 'sin QR'.($this->correlativo !== null ? " ({$this->correlativo})" : ''),
            default => "QR {$this->qr}",
        };

        return trim($modelo === '' ? $pieza : "{$modelo} · {$pieza}");
    }
}
