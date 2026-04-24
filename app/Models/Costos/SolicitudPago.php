<?php

namespace App\Models\Costos;

use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;

/**
 * @use HasFactory<\Database\Factories\Costos\SolicitudPagoFactory>
 */
class SolicitudPago extends Model
{
    use HasFactory, HasMonthlyFolio;

    protected $table = 'costos_solicitudes_pago';

    protected static string $folioPrefix = 'SP';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'solicitante_id',
        'departamento_id',
        'proveedor_id',
        'tipo_solicitud_id',
        'concepto',
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

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
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

    public function aprobaciones(): HasMany
    {
        return $this->hasMany(AprobacionSolicitud::class, 'solicitud_id');
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

    /**
     * Aplica el impacto presupuestal: incrementa acumulado en obra_rubros y crea rubros afectados.
     */
    public function aplicarImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        foreach ($this->detalles as $detalle) {
            ObraRubro::where('id', $detalle->obra_rubro_id)
                ->increment('acumulado', (float) $detalle->subtotal);

            $obraRubro = ObraRubro::find($detalle->obra_rubro_id);
            $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;

            $this->rubrosAfectados()->create([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'monto' => $detalle->subtotal,
                'sobre_giro' => $disponible < 0,
                'descripcion' => $detalle->concepto,
                'tipo_movimiento' => 'cargo',
                'estatus' => 'aplicado',
                'usuario_aplica_id' => $userId,
                'fecha_aplicacion' => now(),
            ]);
        }
    }
}
