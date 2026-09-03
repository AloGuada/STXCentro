<?php

namespace App\Models\Costos;

use App\Models\Alm\Almacen;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\EntregaFactory>
 */
class Entrega extends Model
{
    use HasFactory, HasMonthlyFolio, LogsActivity;

    protected static string $folioPrefix = 'REC';

    /**
     * Al crear/eliminar una entrega, recalcular el estatus de la OC: la primera
     * entrega mueve la OC de pendiente_entrega → pendiente_factura.
     */
    protected static function booted(): void
    {
        static::created(fn (self $e) => $e->ordenCompra?->recalcularEstatus());
        static::deleted(fn (self $e) => $e->ordenCompra?->recalcularEstatus());
    }

    protected $table = 'costos_entregas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'orden_compra_id',
        'almacen_id',
        'factura_id',
        'recibido_por',
        'fecha_entrega',
        'observaciones',
        'tipo',
        'completa_factura',
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
            'fecha_entrega' => 'date',
            'completa_factura' => 'boolean',
            'cancelada_at' => 'datetime',
        ];
    }

    /**
     * Solo entregas vigentes (no canceladas). Se usa en los cálculos de estatus
     * y saldo; las canceladas siguen visibles en las vistas pero no cuentan.
     */
    public function scopeActiva(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('cancelada_at');
    }

    /**
     * Filtros del listado de recepciones. Vive en el modelo (y no en el
     * controlador) porque la pantalla y su reporte en Excel deben acotar
     * exactamente igual: si divergen, el reporte enseña filas que la tabla
     * esconde.
     *
     * @param  array{search?: ?string, tipo?: ?string, fecha_inicio?: ?string, fecha_fin?: ?string, solicitante_id?: ?string}  $filtros
     */
    public function scopeFiltradas(\Illuminate\Database\Eloquent\Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('ordenCompra', fn ($oc) => $oc->where('folio', 'like', "%{$search}%"))
                        ->orWhereHas('ordenCompra.proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$search}%"));
                });
            })
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            // El rango recorta por la fecha de recepción —el sello del sistema,
            // que nadie puede mover— y no por la fecha de entrega, que el
            // almacenista captura y puede caer en otro mes.
            //
            // Los extremos se traducen a UTC desde la zona de operación en vez
            // de comparar el día crudo: si no, lo capturado después de las 18:00
            // del último día del rango se sale del reporte aunque la columna lo
            // imprima dentro. Se traduce el filtro y no la columna para que el
            // índice de `created_at` siga sirviendo.
            ->when(
                $filtros['fecha_inicio'] ?? null,
                fn ($q, $desde) => $q->where('created_at', '>=', self::inicioDelDiaLocal($desde)),
            )
            ->when(
                $filtros['fecha_fin'] ?? null,
                fn ($q, $hasta) => $q->where('created_at', '<=', self::finDelDiaLocal($hasta)),
            )
            // Quien no puede ver todas las OC solo ve las recepciones de sus
            // propias requisiciones.
            ->when(
                $filtros['solicitante_id'] ?? null,
                fn ($q, $id) => $q->whereHas('ordenCompra.requisicion', fn ($r) => $r->where('solicitante_id', $id)),
            );
    }

    /**
     * Arranque de un día de operación (00:00 en Mérida), ya en UTC, que es como
     * se guarda `created_at`.
     */
    private static function inicioDelDiaLocal(string $fecha): CarbonInterface
    {
        return Date::parse($fecha, config('app.display_timezone'))
            ->startOfDay()
            ->utc();
    }

    /** Cierre de un día de operación (23:59:59 en Mérida), ya en UTC. */
    private static function finDelDiaLocal(string $fecha): CarbonInterface
    {
        return Date::parse($fecha, config('app.display_timezone'))
            ->endOfDay()
            ->utc();
    }

    /**
     * Importe de lo recibido: cantidad por precio efectivo de cada renglón. Es el
     * mismo subtotal (sin IVA) que imprime el formato de recepción, para que la
     * pantalla, el reporte y el PDF cuenten lo mismo.
     */
    public function importeRecibido(): float
    {
        return round(
            $this->detalles->sum(
                fn (EntregaDetalle $detalle) => (float) $detalle->cantidad_recibida * $detalle->precio_unitario_efectivo,
            ),
            2,
        );
    }

    /**
     * Fecha en que se elaboró el documento, ya en la zona horaria de operación.
     * El sello se guarda en UTC, así que una recepción capturada después de las
     * 18:00 en Mérida se imprimiría con la fecha del día siguiente si se leyera
     * crudo. Es la fecha que ve el usuario en la pantalla y en el PDF.
     */
    public function fechaRecepcionLocal(): ?CarbonInterface
    {
        return $this->created_at?->setTimezone(config('app.display_timezone'));
    }

    public function estaCancelada(): bool
    {
        return $this->cancelada_at !== null;
    }

    public function cancelador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cancelada_por');
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable');
    }

    /**
     * Dónde se recibió el material. Nulo en las recepciones que no pasan por un
     * almacén: ésas no mueven existencia.
     *
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /** Material que llegó sin compra de por medio. */
    public function esSinOrden(): bool
    {
        return $this->orden_compra_id === null;
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EntregaDetalle::class, 'entrega_id');
    }

    /**
     * Quien recibió el material. La relación se llama `recibidor` y NO
     * `recibidoPor`: al serializar, Eloquent usa snake_case del nombre del
     * método, así que `recibidoPor` pisaría el atributo `recibido_por` (el UUID)
     * con el objeto del usuario, y el front terminaba mandando el nombre de
     * vuelta al backend.
     */
    public function recibidor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->activitiesAsSubject();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['orden_compra_id', 'fecha_entrega', 'tipo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Entrega ({$this->tipo}): {$event}");
    }
}
