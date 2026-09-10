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
}
