<?php

namespace App\Models\Alm;

use App\Enums\Alm\TransferenciaEstatus;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * El traslado de material entre almacenes, folio `TRA`.
 *
 * Un folio, dos firmas: el origen despacha y el destino confirma. No se captura
 * dos veces el documento, se firma dos veces el mismo.
 *
 * @use HasFactory<\Database\Factories\Alm\TransferenciaFactory>
 */
class Transferencia extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected static string $folioPrefix = 'TRA';

    protected $table = 'alm_transferencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'almacen_origen_id',
        'almacen_destino_id',
        'pedido_id',
        'estatus',
        'autorizado_por',
        'fecha_envio',
        'enviado_por',
        'fecha_recepcion',
        'recibido_por',
        'faltante_responsable_id',
        'observaciones',
        'cancelada_at',
        'cancelada_por',
        'motivo_cancelacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => TransferenciaEstatus::class,
            'fecha_envio' => 'date',
            'fecha_recepcion' => 'date',
            'cancelada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Quien autorizó el traslado. Como en el pedido, hoy se firma en la hoja.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function enviador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'enviado_por');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function responsableFaltante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'faltante_responsable_id');
    }

    /**
     * @return HasMany<TransferenciaDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(TransferenciaDetalle::class);
    }

    /**
     * @return MorphMany<Movimiento, $this>
     */
    public function movimientos(): MorphMany
    {
        return $this->morphMany(Movimiento::class, 'documento');
    }

    public function estaCancelada(): bool
    {
        return $this->cancelada_at !== null;
    }

    public function vaEnCamino(): bool
    {
        return ! $this->estaCancelada() && $this->estatus->vaEnCamino();
    }

    /**
     * Lo enviado, lo confirmado y lo que se quedó en el camino.
     *
     * El faltante se suma por renglón y nunca se compensa entre renglones: que
     * llegara un disco de más no repone el electrodo que faltó — y de hecho
     * recibir de más ni siquiera se acepta.
     *
     * @return array{enviado: float, recibido: float, faltante: float, renglones_con_faltante: int}
     */
    public function resumen(): array
    {
        $enviado = 0.0;
        $recibido = 0.0;
        $faltante = 0.0;
        $conFaltante = 0;

        foreach ($this->detalles as $detalle) {
            $enviado += (float) $detalle->cantidad_enviada;

            // Sin confirmar todavía no hay faltante: lo que va en el camión no
            // se le debe a nadie, está en tránsito.
            if ($detalle->cantidad_recibida === null) {
                continue;
            }

            $recibido += (float) $detalle->cantidad_recibida;
            $diferencia = max(0, (float) $detalle->cantidad_enviada - (float) $detalle->cantidad_recibida);
            $faltante += $diferencia;
            $conFaltante += $diferencia > 0 ? 1 : 0;
        }

        return [
            'enviado' => $enviado,
            'recibido' => $recibido,
            'faltante' => $faltante,
            'renglones_con_faltante' => $conFaltante,
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiva(Builder $query): Builder
    {
        return $query->whereNull('cancelada_at');
    }

    /**
     * Las que van en el camino. Es lo que alimenta el saldo en tránsito, que no
     * pertenece a ningún almacén.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnTransito(Builder $query): Builder
    {
        return $query->activa()->where('estatus', TransferenciaEstatus::EnTransito);
    }

    /**
     * Filtros del listado. Viven aquí para que la pantalla y su exportación
     * acoten igual.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<self>
     */
    public function scopeFiltradas(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['almacen_id'] ?? null, fn (Builder $q, $id) => $q->where(
                fn (Builder $b) => $b->where('almacen_origen_id', $id)->orWhere('almacen_destino_id', $id),
            ))
            ->when($filtros['estatus'] ?? null, fn (Builder $q, $e) => $q->where('estatus', $e))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('fecha_envio', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->whereDate('fecha_envio', '<=', $h))
            ->when(! ($filtros['ver_canceladas'] ?? false), fn (Builder $q) => $q->activa())
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where('folio', 'like', "%{$s}%"));
    }
}
