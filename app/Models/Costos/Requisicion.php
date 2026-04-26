<?php

namespace App\Models\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\RequisicionEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Departamento;
use App\Models\Usuario;
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
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

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
        'departamento_id',
        'concepto',
        'justificacion',
        'fecha_requerida',
        'estatus',
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
            'estatus' => RequisicionEstatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
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

    public function aprobaciones(): MorphMany
    {
        return $this->morphMany(Aprobacion::class, 'aprobable');
    }

    public function ordenesGeneradas(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'requisicion_id');
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
        $this->transitionTo(RequisicionEstatus::Rechazada);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'departamento_id', 'concepto'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Requisición {$this->folio}: {$event}");
    }
}
