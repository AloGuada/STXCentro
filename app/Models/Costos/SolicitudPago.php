<?php

namespace App\Models\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Costos\Concerns\AfectaPresupuesto;
use App\Models\Costos\Concerns\VerificaPresupuestoReservado;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<\Database\Factories\Costos\SolicitudPagoFactory>
 */
class SolicitudPago extends Model implements Aprobable
{
    use AfectaPresupuesto, HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity, VerificaPresupuestoReservado;

    public const TIPO_APROBACION = 'solicitud_pago';

    protected $table = 'costos_solicitudes_pago';

    protected static string $folioPrefix = 'SP';

    protected static string $stateEnum = SolicitudPagoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'solicitante_id',
        'firma_adicional_aprobador_id',
        'departamento_id',
        'proveedor_id',
        'orden_compra_id',
        'tipo_solicitud_id',
        'concepto',
        'comentarios',
        'monto_total',
        'tipo_pago',
        'tipo_moneda',
        'fecha_pago_solicitada',
        'fecha_pago_realizada',
        'referencia_pago',
        'estatus',
        'confirmada_costos',
        'confirmada_costos_por',
        'confirmada_costos_at',
        'confirmada_contabilidad',
        'confirmada_contabilidad_por',
        'confirmada_contabilidad_at',
        'afectacion_id',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'fecha_pago_solicitada' => 'date',
            'fecha_pago_realizada' => 'date',
            'confirmada_costos' => 'boolean',
            'confirmada_costos_at' => 'datetime',
            'confirmada_contabilidad' => 'boolean',
            'confirmada_contabilidad_at' => 'datetime',
            'estatus' => SolicitudPagoEstatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable');
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

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'tipo_solicitud_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SolicitudPagoDetalle::class, 'solicitud_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(SolicitudArchivo::class, 'solicitud_id');
    }

    public function aprobaciones(): MorphMany
    {
        return $this->morphMany(Aprobacion::class, 'aprobable');
    }

    public function cadenaAprobacion(): MorphMany
    {
        return $this->aprobaciones();
    }

    public function tipoAprobacion(): string
    {
        return self::TIPO_APROBACION;
    }

    public function saltaVerificacionCostos(): bool
    {
        return (bool) $this->tipoSolicitud?->saltar_verificacion_costos;
    }

    public function onAprobacionCompleta(?string $userId = null): void
    {
        $this->transitionTo(SolicitudPagoEstatus::Aprobada);

        // Las solicitudes generadas desde una OC de contado NO afectan el
        // presupuesto: la OC ya aplicó su impacto permanente al crearse. Volver
        // a afectarlo aquí duplicaría el acumulado del rubro.
        if ($this->orden_compra_id !== null) {
            // Si al aprobar la fecha de pago solicitada ya pasó, se recorre al
            // viernes inmediato siguiente: el pago no debe quedar con fecha
            // vencida por haberse firmado tarde.
            $hoy = \Carbon\CarbonImmutable::now()->startOfDay();
            if ($this->fecha_pago_solicitada !== null && $this->fecha_pago_solicitada->lt($hoy)) {
                $this->fecha_pago_solicitada = ConfiguracionCostos::actual()->proximoViernes($hoy);
                $this->save();
            }

            return;
        }

        $this->aplicarImpactoTrasFirma($userId);
    }

    /**
     * Impacto presupuestal al firmarse la solicitud. Si tiene apartados vigentes
     * (creados al PendienteFirma) los convierte a Aplicado (comprometido →
     * ejercido); si no, aplica el impacto desde cero. Las solicitudes de una OC
     * no afectan aquí: la OC ya aplicó su impacto al crearse. Idempotente
     * respecto al doble conteo: nunca deja Apartado + Aplicado sumados.
     */
    public function aplicarImpactoTrasFirma(?string $userId = null): void
    {
        if ($this->orden_compra_id !== null) {
            return;
        }

        $tieneApartado = $this->rubrosAfectados()
            ->where('estatus', \App\Enums\Costos\RubroAfectadoEstatus::Apartado->value)
            ->exists();

        if ($tieneApartado) {
            app(\App\Services\Costos\ApartadoPresupuestal::class)->convertirAPermanente($this);
        } else {
            $this->aplicarImpactoPresupuestal($userId);
        }
    }

    public function onAprobacionRechazada(string $motivo, ?string $userId = null): void
    {
        $this->transitionTo(SolicitudPagoEstatus::Cancelada);
        app(\App\Services\Costos\ApartadoPresupuestal::class)->cancelarApartadosDe($this, 'rechazada en aprobación');

        // Si la SP es el anticipo de una OC de contado (única con orden_compra_id),
        // rechazarla cancela la OC y revierte su impacto presupuestal: la OC no
        // debe quedar viva sin pago.
        $this->ordenCompra?->cancelarPorRechazoDePago("Solicitud de pago rechazada: {$motivo}", $userId);
    }

    public function pago(): MorphOne
    {
        return $this->morphOne(Pago::class, 'pagable');
    }

    public function confirmadorCostos(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmada_costos_por');
    }

    public function confirmadorContabilidad(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'confirmada_contabilidad_por');
    }

    public function rubrosAfectados(): MorphMany
    {
        return $this->morphMany(RubroAfectado::class, 'entrada');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    protected function descripcionAfectacion(object $detalle, ObraRubro $obraRubro): ?string
    {
        return $detalle->concepto;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly([
                'folio', 'estatus', 'monto_total',
                'confirmada_costos', 'confirmada_contabilidad',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Solicitud {$this->folio}: {$event}");
    }
}
