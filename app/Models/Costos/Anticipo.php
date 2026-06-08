<?php

namespace App\Models\Costos;

use App\Enums\Costos\AnticipoEstatus;
use App\Models\Concerns\HasCancelacion;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Obra;
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
 * Anticipo entregado al proveedor antes de recibir factura. El saldo
 * disponible se va aplicando contra facturas via AnticipoAplicacion.
 * Cuando saldo_disponible == 0 transiciona a `agotado` automaticamente.
 *
 * @use HasFactory<\Database\Factories\Costos\AnticipoFactory>
 */
class Anticipo extends Model
{
    use HasCancelacion, HasEditLock, HasFactory, HasMonthlyFolio, HasStateMachine, LogsActivity;

    protected $table = 'costos_anticipos';

    protected static string $folioPrefix = 'AN';

    protected static string $stateEnum = AnticipoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'proveedor_id',
        'obra_id',
        'monto',
        'saldo_disponible',
        'moneda',
        'estatus',
        'referencia',
        'fecha',
        'notas',
        'creado_por',
        'locked_by',
        'locked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'saldo_disponible' => 'decimal:2',
            'fecha' => 'date',
            'estatus' => AnticipoEstatus::class,
            'locked_at' => 'datetime',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AnticipoAplicacion::class, 'anticipo_id');
    }

    /**
     * El anticipo es pagable: cuando se entrega dinero al proveedor, se
     * registra un Pago con pagable_type=Anticipo. Esto reusa toda la
     * infraestructura de pagos sin duplicar.
     */
    public function pago(): MorphOne
    {
        return $this->morphOne(Pago::class, 'pagable');
    }

    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject();
    }

    /**
     * Aplica una porción del anticipo a una factura. Decrementa
     * `saldo_disponible` y crea AnticipoAplicacion. Si llega a 0, transiciona
     * automáticamente a `agotado`.
     */
    public function aplicarAFactura(Factura $factura, float $monto, ?string $userId = null, ?string $notas = null): AnticipoAplicacion
    {
        $aplicacion = $this->aplicaciones()->create([
            'factura_id' => $factura->id,
            'monto' => $monto,
            'fecha' => now()->toDateString(),
            'usuario_id' => $userId,
            'notas' => $notas,
        ]);

        $this->decrement('saldo_disponible', $monto);

        if ((float) $this->fresh()->saldo_disponible <= config('costos.epsilon_monto')) {
            $this->refresh();
            $this->transitionTo(AnticipoEstatus::Agotado);
        }

        return $aplicacion;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('costos')
            ->logOnly(['folio', 'estatus', 'monto', 'saldo_disponible', 'proveedor_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => "Anticipo {$this->folio}: {$event}");
    }
}
