<?php

namespace App\Models\Alm;

use App\Enums\Alm\ConteoEstatus;
use App\Enums\Alm\ConteoOrigen;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * La hoja de conteo, folio `CIC`: qué artículos se cuentan en qué almacén y
 * qué día.
 *
 * No mueve saldo. Es la lista que se imprime, se camina y se llena; al cerrar
 * con diferencias generará un ajuste, y ése es el único documento al que el
 * kardex le permite corregir existencias.
 *
 * @use HasFactory<\Database\Factories\Alm\ConteoFactory>
 */
class Conteo extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'CIC';

    protected $table = 'alm_conteos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'programa_id',
        'almacen_id',
        'origen',
        'fecha_programada',
        'estatus',
        'responsable_id',
        'fecha_cierre',
        'ajuste_id',
        'observaciones',
        'firmado_path',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen' => ConteoOrigen::class,
            'estatus' => ConteoEstatus::class,
            'fecha_programada' => 'date',
            'fecha_cierre' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<ConteoPrograma, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(ConteoPrograma::class, 'programa_id');
    }

    /**
     * @return HasMany<ConteoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(ConteoDetalle::class)->orderBy('orden');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /**
     * @return BelongsTo<Ajuste, $this>
     */
    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(Ajuste::class);
    }

    /** Dónde abrir la hoja firmada, si la subieron al cerrar. */
    public function firmadoUrl(): ?string
    {
        return $this->firmado_path === null ? null : Storage::disk('public')->url($this->firmado_path);
    }

    /** Se le pasó la fecha y sigue abierta. */
    public function vencido(): bool
    {
        return $this->estatus->abierto() && $this->fecha_programada->lt(today());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereIn('estatus', [ConteoEstatus::Pendiente->value, ConteoEstatus::Contando->value]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVencidos(Builder $query): Builder
    {
        return $query->abiertos()->whereDate('fecha_programada', '<', today());
    }

    /**
     * Filtros del listado.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltrados(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where('almacen_id', $id))
            ->when($filtros['estatus'] ?? null, fn (Builder $q, $e) => $q->where('estatus', $e))
            ->when($filtros['programa_id'] ?? null, fn (Builder $q, $p) => $q->where('programa_id', $p))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->whereLike('folio', "%{$s}%"));
    }
}
