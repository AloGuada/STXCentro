<?php

namespace App\Models\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Models\Concepto;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Pieza;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una inspección de calidad de una pieza, folio `QAL`.
 *
 * Es la cabecera común a las tres transformaciones. Lo propio de cada fase
 * cuelga de aquí: los puntos contestados, los defectos, el muestreo (1ª), las
 * juntas del mapeo (2ª en soldado), los espesores y la adherencia (pintura).
 *
 * La pieza es la de Producción: en 1ª la marca y el consecutivo, en 2ª y
 * pintura la pieza física por su QR. Marca, lote y QR se guardan también como
 * texto para que la inspección siga diciendo de qué pieza habla aunque el
 * catálogo de Producción se versione o se vacíe.
 *
 * @use HasFactory<\Database\Factories\Qal\InspeccionFactory>
 */
class Inspeccion extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'QAL';

    protected $table = 'qal_inspecciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'obra_id',
        'catalogo_id',
        'concepto_id',
        'prod_pieza_id',
        'marca',
        'lote',
        'qr',
        'tipo_pieza_id',
        'fase',
        'subetapa',
        'subtipo',
        'fecha',
        'anio',
        'semana',
        'inspector_id',
        'numero_inspeccion',
        'consecutivo',
        'cantidad_lote',
        'kg',
        'folio_strumis',
        'linea',
        'modulo',
        'equipo_id',
        'operador_id',
        'responsable_id',
        'supervisor_pintura_id',
        'soldador_id',
        'estatus',
        'avance_iv',
        'avance_is',
        'observaciones',
        'capturado_en',
        'capturista_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fase' => FaseTransformacion::class,
            'subetapa' => Subetapa::class,
            'subtipo' => SubtipoPrimera::class,
            'estatus' => EstatusInspeccion::class,
            'fecha' => 'date',
            'anio' => 'integer',
            'semana' => 'integer',
            'numero_inspeccion' => 'integer',
            'consecutivo' => 'integer',
            'cantidad_lote' => 'integer',
            'kg' => 'decimal:3',
            'capturado_en' => 'datetime',
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
     * La versión del catálogo de Producción vigente al capturar.
     *
     * @return BelongsTo<Catalogo, $this>
     */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(Catalogo::class, 'catalogo_id');
    }

    /**
     * La marca de Producción.
     *
     * @return BelongsTo<Concepto, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }

    /**
     * La pieza física, por su QR. Nula en 1ª.
     *
     * @return BelongsTo<Pieza, $this>
     */
    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'prod_pieza_id');
    }

    /**
     * @return BelongsTo<TipoPieza, $this>
     */
    public function tipoPieza(): BelongsTo
    {
        return $this->belongsTo(TipoPieza::class, 'tipo_pieza_id');
    }

    /**
     * @return BelongsTo<Inspector, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Inspector::class, 'inspector_id');
    }

    /**
     * @return BelongsTo<Equipo, $this>
     */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    /**
     * @return BelongsTo<Operador, $this>
     */
    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class, 'operador_id');
    }

    /**
     * @return BelongsTo<Responsable, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    /**
     * @return BelongsTo<SupervisorPintura, $this>
     */
    public function supervisorPintura(): BelongsTo
    {
        return $this->belongsTo(SupervisorPintura::class, 'supervisor_pintura_id');
    }

    /**
     * @return BelongsTo<Soldador, $this>
     */
    public function soldador(): BelongsTo
    {
        return $this->belongsTo(Soldador::class, 'soldador_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function capturista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'capturista_id');
    }

    /**
     * @return HasMany<InspeccionPunto, $this>
     */
    public function puntos(): HasMany
    {
        return $this->hasMany(InspeccionPunto::class, 'inspeccion_id');
    }

    /**
     * @return HasMany<InspeccionDefecto, $this>
     */
    public function defectos(): HasMany
    {
        return $this->hasMany(InspeccionDefecto::class, 'inspeccion_id');
    }

    /**
     * @return HasOne<Muestreo, $this>
     */
    public function muestreo(): HasOne
    {
        return $this->hasOne(Muestreo::class, 'inspeccion_id');
    }

    /**
     * @return HasOne<Pintura, $this>
     */
    public function pintura(): HasOne
    {
        return $this->hasOne(Pintura::class, 'inspeccion_id');
    }

    /**
     * @return HasOne<Adherencia, $this>
     */
    public function adherencia(): HasOne
    {
        return $this->hasOne(Adherencia::class, 'inspeccion_id');
    }

    /**
     * @return HasMany<Junta, $this>
     */
    public function juntas(): HasMany
    {
        return $this->hasMany(Junta::class, 'inspeccion_id');
    }

    /**
     * Los filtros de Registros. La búsqueda es por marca, folio, QR o folio de
     * Strumis: cualquiera de los cuatro es como alguien nombra una pieza.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     */
    public function scopeFiltrada(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['obra'] ?? null, fn (Builder $consulta, int $obra) => $consulta->where('obra_id', $obra))
            ->when($filtros['fase'] ?? null, fn (Builder $consulta, string $fase) => $consulta->where('fase', $fase))
            ->when($filtros['inspector'] ?? null, fn (Builder $consulta, int $inspector) => $consulta->where('inspector_id', $inspector))
            ->when($filtros['estatus'] ?? null, fn (Builder $consulta, string $estatus) => $consulta->where('estatus', $estatus))
            ->when($filtros['fecha'] ?? null, fn (Builder $consulta, string $fecha) => $consulta->whereDate('fecha', $fecha))
            ->when($filtros['buscar'] ?? null, fn (Builder $consulta, string $texto) => $consulta->where(
                fn (Builder $busqueda) => $busqueda
                    ->where('marca', 'like', "%{$texto}%")
                    ->orWhere('folio', 'like', "%{$texto}%")
                    ->orWhere('qr', 'like', "%{$texto}%")
                    ->orWhere('folio_strumis', 'like', "%{$texto}%"),
            ));
    }

    /**
     * Las inspecciones de la misma pieza en la misma etapa: las que cuentan
     * para el número de inspección.
     *
     * @return Builder<self>
     */
    public function mismaPiezaYEtapa(): Builder
    {
        $consulta = static::query()->where('obra_id', $this->obra_id)->where('fase', $this->fase->value);

        return $this->fase === FaseTransformacion::Primera
            ? $consulta->where('marca', $this->marca)->where('lote', $this->lote)->where('consecutivo', $this->consecutivo)
            : $consulta->where('qr', $this->qr)->where('subetapa', $this->subetapa?->value);
    }

    /**
     * La historia de la pieza: en 1ª, las de su marca y consecutivo; de 2ª en
     * adelante, todas las de su QR, porque ahí es la misma pieza física la que
     * pasa por armado, soldado y pintura.
     *
     * @return Builder<self>
     */
    public function historialDeLaPieza(): Builder
    {
        $consulta = static::query()->where('obra_id', $this->obra_id);

        return $this->fase === FaseTransformacion::Primera
            ? $consulta->where('fase', $this->fase->value)->where('marca', $this->marca)->where('lote', $this->lote)->where('consecutivo', $this->consecutivo)
            : $consulta->where('qr', $this->qr);
    }
}
