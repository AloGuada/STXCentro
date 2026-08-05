<?php

namespace App\Models;

use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Registro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'qs',
        'marca',
        'etapa',
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
     * Pieza de la que se copió esta al versionar el catálogo. Sostiene el
     * conteo de lo pagado cuando la marca cambia entre versiones.
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'concepto_origen_id');
    }

    /**
     * Sólo las piezas del catálogo vigente de su obra. Indispensable en captura
     * de producción y asignación de precios: tras copiar una versión la misma
     * marca existe en varios catálogos y sin este filtro queda ambigua.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeDeCatalogoVigente($query)
    {
        return $query->whereHas('catalogo', fn ($q) => $q->where('vigente', true));
    }

    /**
     * Etapa comparable: mayúsculas, sin espacios de más y con la cadena vacía
     * tratada como "sin etapa". Se normaliza al guardar para que "fase b" y
     * "FASE B" no acaben siendo dos modelos distintos.
     */
    public static function normalizarEtapa(?string $etapa): ?string
    {
        $limpia = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $etapa))) ?? '';

        return $limpia === '' ? null : $limpia;
    }

    /**
     * Identidad del modelo dentro de un catálogo. La marca sola no basta: una
     * misma marca puede repetirse en varias etapas de la obra. Estática porque
     * también se usa sobre el snapshot de las liquidaciones, donde ya no hay
     * concepto vivo que consultar.
     */
    public static function claveDeModelo(?string $marca, ?string $etapa): string
    {
        return trim((string) $marca).'|'.(self::normalizarEtapa($etapa) ?? '');
    }

    /** Cómo se nombra la pieza en pantalla y en los mensajes de error. */
    public static function etiquetaDeModelo(?string $marca, ?string $etapa): string
    {
        $marca = trim((string) $marca);
        $etapa = trim((string) $etapa);

        return $etapa === '' ? $marca : "{$marca} · {$etapa}";
    }

    public function claveModelo(): string
    {
        return self::claveDeModelo($this->marca, $this->etapa);
    }

    public function etiquetaModelo(): string
    {
        return self::etiquetaDeModelo($this->marca, $this->etapa);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function grupoPrecioConceptos(): HasMany
    {
        return $this->hasMany(GrupoPrecioConcepto::class, 'concepto_id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(Registro::class, 'concepto_id');
    }
}
