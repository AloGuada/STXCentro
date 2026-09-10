<?php

namespace App\Models\Qal;

use App\Models\Obra as ObraDelPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * La obra vista desde Calidad: la extensión 1:1 de la obra del portal.
 *
 * Calidad no tiene padrón propio. La obra es la misma que usa Producción, y el
 * número, la descripción y si está activa se leen de ahí; aquí sólo vive lo que
 * es de Calidad —responsable, la nota de dónde sale el plan de PND, las piezas
 * totales— y es de donde cuelgan PND, montaje e incidencias.
 *
 * Una obra entra a Calidad cuando Producción abre su catálogo (ver
 * AppServiceProvider), o cuando Calidad la necesita por primera vez
 * (`paraObra`).
 */
class Obra extends Model
{
    use HasFactory;

    protected $table = 'qal_obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'responsable_calidad',
        'pnd_nota',
        'pz_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pz_total' => 'integer',
        ];
    }

    /**
     * La ficha de Calidad de una obra del portal; se crea si todavía no existe.
     */
    public static function paraObra(ObraDelPortal|int $obra): self
    {
        return static::query()->firstOrCreate([
            'obra_id' => $obra instanceof ObraDelPortal ? $obra->getKey() : $obra,
        ]);
    }

    /**
     * Las obras de Calidad como opciones de un selector. La llave es la de la
     * obra del portal, que es de donde cuelgan inspecciones y lotes.
     *
     * La captura sólo ofrece las activas; una consulta de auditoría también
     * necesita las cerradas.
     *
     * @return SupportCollection<int, array{id: int, no: string|null, descripcion: string|null}>
     */
    public static function opcionesDeSelector(bool $soloActivas = true): SupportCollection
    {
        return static::query()
            ->conDatosDeLaObra()
            ->when($soloActivas, fn (Builder $consulta) => $consulta->where('obras.activa', true))
            ->orderByDesc('obras.activa')
            ->orderBy('obras.no')
            ->get()
            ->map(fn (self $obra): array => [
                'id' => $obra->obra_id,
                'no' => $obra->no,
                'descripcion' => $obra->descripcion,
            ]);
    }

    /**
     * Trae número, descripción y estado de la obra del portal como columnas
     * propias, para poder ordenar y listar sin una consulta por obra.
     *
     * @param  Builder<self>  $query
     */
    public function scopeConDatosDeLaObra(Builder $query): void
    {
        $query->join('obras', 'obras.id', '=', 'qal_obras.obra_id')
            ->select('qal_obras.*', 'obras.no', 'obras.descripcion', 'obras.activa');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->whereHas('obra', fn (Builder $obra) => $obra->where('activa', true));
    }

    /**
     * La obra del portal. De ahí salen número, descripción, cliente y contrato;
     * aquí no se copian, porque duplicarlos es garantizar que dejen de coincidir.
     *
     * @return BelongsTo<ObraDelPortal, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(ObraDelPortal::class, 'obra_id');
    }

    /**
     * Lo lee de la columna que trae `conDatosDeLaObra` y, si no se usó, de la
     * obra del portal.
     */
    protected function no(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $value ?? $this->obra?->no);
    }

    protected function descripcion(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $value ?? $this->obra?->descripcion);
    }

    protected function activa(): Attribute
    {
        return Attribute::get(fn (mixed $value): bool => (bool) ($value ?? $this->obra?->activa));
    }

    /**
     * Herencia de la aplicación de mapeo 2D. Se retira junto con `qal_etapas`.
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(Etapa::class, 'obra_id');
    }

    /**
     * @return HasMany<ObraPndPlan, $this>
     */
    public function pndPlan(): HasMany
    {
        return $this->hasMany(ObraPndPlan::class, 'qal_obra_id');
    }

    /**
     * Los informes de laboratorio de la obra. Contra el plan de arriba se mide
     * el avance de PND.
     *
     * @return HasMany<PndReporte, $this>
     */
    public function pndReportes(): HasMany
    {
        return $this->hasMany(PndReporte::class, 'qal_obra_id');
    }

    /**
     * El avance de montaje semana por semana: el denominador con el que se
     * miden las incidencias en obra.
     *
     * @return HasMany<ObraMontaje, $this>
     */
    public function montaje(): HasMany
    {
        return $this->hasMany(ObraMontaje::class, 'qal_obra_id');
    }

    /**
     * Lo que falló durante el montaje. Es circuito aparte de la inspección de
     * taller: mide lo que aparece en sitio, no lo que se rechaza en planta.
     *
     * @return HasMany<ObraIncidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(ObraIncidencia::class, 'qal_obra_id');
    }
}
