<?php

namespace App\Models\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\RequisicionEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Costos\Concerns\VerificaPresupuestoReservado;
use App\Models\Departamento;
use App\Models\Media;
use App\Models\Obra;
use App\Models\Usuario;
use App\Services\Costos\BuscadorMejorProveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\RequisicionFactory>
 */
class Requisicion extends Model implements Aprobable
{
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity, VerificaPresupuestoReservado;

    public const TIPO_APROBACION = 'requisicion';

    protected $table = 'costos_requisiciones';

    protected static string $folioPrefix = 'REQ';

    protected static string $stateEnum = RequisicionEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'solicitante_id',
        'firma_adicional_aprobador_id',
        'departamento_id',
        'obra_id',
        'presupuesto_id',
        'sin_centro_costos',
        'justificacion',
        'tipo_cambio',
        'fecha_requerida',
        'estatus',
        'control_por',
        'control_at',
        'modo_dedazo',
        'sobre_obra_cerrada',
        'motivo_rechazo',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_requerida' => 'date',
            'tipo_cambio' => 'decimal:6',
            'sin_centro_costos' => 'boolean',
            'estatus' => RequisicionEstatus::class,
            'control_at' => 'datetime',
            'modo_dedazo' => 'boolean',
            'sobre_obra_cerrada' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function firmaAdicionalAprobador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'firma_adicional_aprobador_id');
    }

    public function firmaAdicionalAprobadorId(): ?string
    {
        return $this->firma_adicional_aprobador_id;
    }

    /** @var list<array<string, mixed>>|null */
    private ?array $ocsResumenCache = null;

    /**
     * Resumen de las OCs adjudicadas: un renglón por grupo (proveedor, numero_oc)
     * de las selecciones, con el proveedor elegido, su neto a pagar y —si la
     * requisición ya se liberó— el folio de la orden de compra generada.
     * Vacío mientras no haya selecciones (antes de definir la OC).
     *
     * Un grupo por (proveedor, numero_oc, moneda): una OC no puede mezclar
     * monedas ({@see \App\Services\Costos\OrdenCompraGenerator}), y sumar
     * importes de divisas distintas en un mismo `total` daría un número sin
     * unidad. `total` está expresado en `moneda`.
     *
     * @return list<array{numero_oc: int, folio: string|null, proveedor_id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float}>
     */
    public function getOcsResumenAttribute(): array
    {
        if ($this->ocsResumenCache !== null) {
            return $this->ocsResumenCache;
        }

        $calculador = new \App\Services\Costos\RetencionCalculator;

        /** @var array<string, array{numero_oc: int, folio: string|null, moneda: string, proveedor: \App\Models\Proveedor, lineas: list<array{tipo_fiscal: ?string, subtotal: float, sin_impuestos: bool}>}> $grupos */
        $grupos = [];
        foreach ($this->detalles as $detalle) {
            if ($detalle->solo_cotizacion) {
                continue;
            }

            foreach ($detalle->selecciones as $seleccion) {
                if (! $seleccion->proveedor) {
                    continue;
                }

                $numeroOc = (int) ($seleccion->numero_oc ?? 1);
                $moneda = strtolower((string) ($seleccion->cotizacionPrecio?->moneda ?? 'mxn'));
                $clave = $seleccion->proveedor_id.'|'.$numeroOc.'|'.$moneda;
                $subtotal = (float) ($seleccion->cotizacionPrecio?->precio_unitario ?? 0) * (float) $seleccion->cantidad;

                $grupos[$clave]['numero_oc'] ??= $numeroOc;
                $grupos[$clave]['moneda'] ??= $moneda;
                $grupos[$clave]['proveedor'] ??= $seleccion->proveedor;
                $grupos[$clave]['folio'] ??= $seleccion->ordenCompraDetalle?->ordenCompra?->folio;
                $grupos[$clave]['lineas'][] = [
                    'tipo_fiscal' => $detalle->tipo_fiscal?->value,
                    'subtotal' => $subtotal,
                    'sin_impuestos' => (bool) $detalle->sin_impuestos,
                ];
            }
        }

        $resumen = [];
        foreach ($grupos as $grupo) {
            $proveedor = $grupo['proveedor'];
            $resumen[] = [
                'numero_oc' => $grupo['numero_oc'],
                'folio' => $grupo['folio'] ?? null,
                'proveedor_id' => (int) $proveedor->id,
                'razon_social' => (string) $proveedor->razon_social,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'moneda' => $grupo['moneda'],
                'total' => $calculador->calcular($proveedor, $grupo['lineas'])['total_neto'],
            ];
        }

        usort($resumen, fn (array $a, array $b) => [$a['numero_oc'], $a['razon_social'], $a['moneda']] <=> [$b['numero_oc'], $b['razon_social'], $b['moneda']]);

        return $this->ocsResumenCache = $resumen;
    }

    /**
     * Total neto a pagar (subtotal + IVA - retenciones) sumando el neto de cada
     * OC adjudicada. Es 0 mientras no haya selecciones (antes de definir la OC).
     *
     * Siempre en MXN: las OCs en divisa se convierten con el tipo de cambio del
     * documento, igual que el neto combinado del comparativo. Sin sumarlas en
     * una sola unidad, una requisición con OCs en monedas distintas devolvía la
     * suma cruda de pesos con dólares.
     */
    public function getTotalNetoAttribute(): float
    {
        $tipoCambio = (float) ($this->tipo_cambio ?: 1);

        return round(array_sum(array_map(
            fn (array $oc): float => $oc['moneda'] === 'mxn'
                ? (float) $oc['total']
                : (float) $oc['total'] * $tipoCambio,
            $this->ocs_resumen,
        )), 2);
    }

    public function controlador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'control_por');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /**
     * @deprecated Se conserva durante la transición. Usar presupuesto().
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Presupuesto (proyecto/obra/partida) al que se carga la requisición.
     * Nulo cuando es multipresupuesto (cada partida define el suyo por rubro).
     */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class, 'requisicion_id');
    }

    public function cotizaciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            RequisicionCotizacionPrecio::class,
            RequisicionDetalle::class,
            'requisicion_id',
            'requisicion_detalle_id',
        );
    }

    public function selecciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            RequisicionSeleccion::class,
            RequisicionDetalle::class,
            'requisicion_id',
            'requisicion_detalle_id',
        );
    }

    public function ocs(): HasMany
    {
        return $this->hasMany(RequisicionOc::class, 'requisicion_id');
    }

    public function cotizacionOpciones(): HasMany
    {
        return $this->hasMany(RequisicionCotizacionOpcion::class, 'requisicion_id')
            ->orderBy('proveedor_id')
            ->orderBy('orden');
    }

    public function aprobaciones(): MorphMany
    {
        return $this->morphMany(Aprobacion::class, 'aprobable');
    }

    public function ordenesGeneradas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'requisicion_id');
    }

    public function rubrosAfectados(): MorphMany
    {
        return $this->morphMany(RubroAfectado::class, 'entrada');
    }

    /**
     * Archivos adjuntos a la requisición (ej. PDFs de cotización como
     * información extra durante el cotizado).
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    // --- Aprobable ---------------------------------------------------------

    public function tipoAprobacion(): string
    {
        return self::TIPO_APROBACION;
    }

    /**
     * Una requisición "sin obra" no carga a ningún centro de costos, así que no
     * hay presupuesto que verificar: se salta el punto de control de Costos
     * (primer nivel de la cadena).
     */
    public function saltaVerificacionCostos(): bool
    {
        return (bool) $this->sin_centro_costos;
    }

    public function cadenaAprobacion(): MorphMany
    {
        return $this->aprobaciones();
    }

    public function onAprobacionCompleta(?string $userId = null): void
    {
        $this->transitionTo(RequisicionEstatus::Aprobada);
    }

    public function onAprobacionRechazada(string $motivo, ?string $userId = null): void
    {
        $this->update(['motivo_rechazo' => $motivo]);
        app(\App\Services\Costos\ApartadoPresupuestal::class)->cancelarApartadosDe($this, 'rechazada en aprobación');
        $this->transitionTo(RequisicionEstatus::Rechazada);
    }

    /**
     * Mejor proveedor: aquel que cotizó TODAS las partidas y cuya suma de
     * (cantidad × precio_unitario) por partida es la menor. `total` va siempre
     * en MXN (las cotizaciones en divisa se convierten con el tipo de cambio del
     * documento) y `falta_tc` marca que hay divisa sin tipo de cambio capturado.
     * La lógica vive en {@see BuscadorMejorProveedor}.
     *
     * @return array{id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float, falta_tc: bool}|null
     */
    /** @var array{id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float, falta_tc: bool}|null */
    private ?array $mejorProveedorPrecargado = null;

    private bool $mejorProveedorResuelto = false;

    /**
     * Precarga el resultado de `mejor_proveedor` (calculado en lote con
     * BuscadorMejorProveedor::buscarLote) para que el accessor no dispare
     * queries por fila en listados.
     *
     * @param  array{id: int, razon_social: string, nombre_comercial: string|null, moneda: string, total: float, falta_tc: bool}|null  $mejor
     */
    public function precargarMejorProveedor(?array $mejor): void
    {
        $this->mejorProveedorPrecargado = $mejor;
        $this->mejorProveedorResuelto = true;
    }

    public function getMejorProveedorAttribute(): ?array
    {
        if ($this->mejorProveedorResuelto) {
            return $this->mejorProveedorPrecargado;
        }

        return app(BuscadorMejorProveedor::class)->buscar($this);
    }

    /**
     * Cantidad de proveedores distintos que han cotizado al menos una partida.
     */
    public function getProveedoresCotizadoresCountAttribute(): int
    {
        $this->loadMissing('detalles.cotizaciones');

        $ids = [];
        foreach ($this->detalles as $detalle) {
            foreach ($detalle->cotizaciones as $cot) {
                $ids[(int) $cot->proveedor_id] = true;
            }
        }

        return count($ids);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'departamento_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Requisición {$this->folio}: {$event}");
    }
}
