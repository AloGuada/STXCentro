<?php

namespace App\Models;

use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * La marca: el modelo del catálogo, con su descripción, peso, longitud y
 * categoría.
 *
 * La identifica el par (marca, lote): la misma marca puede repetirse en varios
 * lotes de la obra.
 *
 * No se paga contra esta fila. `cantidad` dice cuántas piezas pide el modelo y
 * de aquí cuelgan esas unidades como `Pieza`, una por QR; el destajo se paga
 * pieza por pieza. Cuando la marca entra por layout, `cantidad` es el número de
 * piezas que trajo el archivo. La tabla conserva el nombre `conceptos` por su
 * historia.
 */
class Concepto extends Model
{
    /** @use HasFactory<\Database\Factories\ConceptoFactory> */
    use HasFactory;

    protected $table = 'conceptos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'catalogo_id',
        'concepto_origen_id',
        'marca',
        'lote',
        'descripcion',
        'cantidad',
        'peso_unitario',
        'longitud',
        'categoria_id',
        'version',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'peso_unitario' => 'decimal:3',
            'longitud' => 'integer',
            'version' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    /**
     * Marca de la que se copió ésta al versionar el catálogo. Mantiene atado el
     * histórico cuando la marca cambia de nombre entre versiones.
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'concepto_origen_id');
    }

    /** Las unidades físicas del modelo, una por QS del layout. */
    public function piezas(): HasMany
    {
        return $this->hasMany(Pieza::class, 'concepto_id');
    }

    /**
     * Sólo las marcas del catálogo vigente de su obra. Indispensable en la
     * asignación de precios: tras copiar una versión la misma marca existe en
     * varios catálogos y sin este filtro queda ambigua.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeDeCatalogoVigente($query)
    {
        return $query->whereHas('catalogo', fn ($q) => $q->where('vigente', true));
    }

    /**
     * Lote comparable: mayúsculas, sin espacios de más y con la cadena vacía
     * tratada como "sin lote". Se normaliza al guardar para que "lote a" y
     * "LOTE A" no acaben siendo dos modelos distintos.
     */
    public static function normalizarLote(?string $lote): ?string
    {
        $limpia = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $lote))) ?? '';

        return $limpia === '' ? null : $limpia;
    }

    /**
     * Identidad del modelo dentro de un catálogo. La marca sola no basta: una
     * misma marca puede repetirse en varios lotes de la obra. Estática porque
     * también se usa sobre el snapshot de las liquidaciones, donde ya no hay
     * concepto vivo que consultar.
     */
    public static function claveDeModelo(?string $marca, ?string $lote): string
    {
        return trim((string) $marca).'|'.(self::normalizarLote($lote) ?? '');
    }

    /** Cómo se nombra la pieza en pantalla y en los mensajes de error. */
    public static function etiquetaDeModelo(?string $marca, ?string $lote): string
    {
        $marca = trim((string) $marca);
        $lote = trim((string) $lote);

        return $lote === '' ? $marca : "{$marca} · {$lote}";
    }

    public function claveModelo(): string
    {
        return self::claveDeModelo($this->marca, $this->lote);
    }

    public function etiquetaModelo(): string
    {
        return self::etiquetaDeModelo($this->marca, $this->lote);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function grupoPrecioConceptos(): HasMany
    {
        return $this->hasMany(GrupoPrecioConcepto::class, 'concepto_id');
    }

    /** Producción capturada de cualquiera de sus piezas, en cualquier proceso. */
    public function registros(): HasManyThrough
    {
        return $this->hasManyThrough(Registro::class, Pieza::class, 'concepto_id', 'pieza_id');
    }
}
