<?php

namespace App\Models\Qal;

use App\Enums\Qal\NivelAql;
use App\Enums\Qal\VeredictoLote;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Una inspección de sublote: una entrega del lote de accesorios, revisada por
 * muestreo AQL.
 *
 * Una reinspección es otra fila del mismo grupo, con el número siguiente; el
 * grupo es la primera inspección del sublote físico. Así queda el historial de
 * que hubo un rechazo, que es lo que mide el FPY.
 *
 * @use HasFactory<\Database\Factories\Qal\SubloteFactory>
 */
class Sublote extends Model
{
    use HasFactory;

    protected $table = 'qal_sublotes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lote_id',
        'sublote_origen_id',
        'numero_inspeccion',
        'unidades',
        'fecha',
        'anio',
        'semana',
        'inspector_id',
        'linea',
        'modulo',
        'responsable_id',
        'soldador_id',
        'nivel',
        'muestra',
        'aceptacion',
        'rechazo',
        'conformes',
        'rechazadas',
        'veredicto',
        'disposicion',
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
            'nivel' => NivelAql::class,
            'veredicto' => VeredictoLote::class,
            'fecha' => 'date',
            'anio' => 'integer',
            'semana' => 'integer',
            'numero_inspeccion' => 'integer',
            'unidades' => 'integer',
            'muestra' => 'integer',
            'aceptacion' => 'integer',
            'rechazo' => 'integer',
            'conformes' => 'integer',
            'rechazadas' => 'integer',
            'capturado_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LoteAccesorio, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteAccesorio::class, 'lote_id');
    }

    /**
     * La primera inspección del sublote físico. Nula en la primera misma.
     *
     * @return BelongsTo<self, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sublote_origen_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function reinspecciones(): HasMany
    {
        return $this->hasMany(self::class, 'sublote_origen_id');
    }

    /**
     * @return BelongsTo<Inspector, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Inspector::class, 'inspector_id');
    }

    /**
     * @return BelongsTo<Responsable, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
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
     * @return HasMany<SubloteDefecto, $this>
     */
    public function defectos(): HasMany
    {
        return $this->hasMany(SubloteDefecto::class, 'sublote_id');
    }

    /**
     * Sólo la inspección que manda en su grupo: la de número más alto. Un
     * sublote reinspeccionado cuenta una vez, con su último veredicto.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUltimas(Builder $query): void
    {
        $query->whereNotExists(function (QueryBuilder $posterior): void {
            $posterior->from('qal_sublotes as posterior')
                ->whereRaw('coalesce(posterior.sublote_origen_id, posterior.id) = coalesce(qal_sublotes.sublote_origen_id, qal_sublotes.id)')
                ->whereColumn('posterior.numero_inspeccion', '>', 'qal_sublotes.numero_inspeccion');
        });
    }

    /**
     * Los filtros de Registros: obra, inspector, fecha y marca del lote.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     */
    public function scopeFiltrado(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['obra'] ?? null, fn (Builder $consulta, int $obra) => $consulta->whereHas('lote', fn (Builder $lote) => $lote->where('obra_id', $obra)))
            ->when($filtros['inspector'] ?? null, fn (Builder $consulta, int $inspector) => $consulta->where('inspector_id', $inspector))
            ->when($filtros['fecha'] ?? null, fn (Builder $consulta, string $fecha) => $consulta->whereDate('fecha', $fecha))
            ->when($filtros['buscar'] ?? null, fn (Builder $consulta, string $texto) => $consulta->whereHas('lote', fn (Builder $lote) => $lote->where('marca', 'like', "%{$texto}%")));
    }

    /** El id de la primera inspección del sublote físico. */
    public function grupoId(): int
    {
        return $this->sublote_origen_id ?? $this->id;
    }

    /**
     * Aceptado, o rechazado pero liberado bajo concesión: el material se fue a
     * obra, y para el avance eso es lo que cuenta.
     */
    public function liberado(): bool
    {
        return $this->veredicto === VeredictoLote::Aceptado
            || str_contains(mb_strtolower((string) $this->disposicion), 'concesi');
    }

    /** Material rechazado que nadie decidió qué hacer con él. */
    public function sinDisposicion(): bool
    {
        return $this->veredicto === VeredictoLote::Rechazado && ! $this->liberado() && blank($this->disposicion);
    }
}
