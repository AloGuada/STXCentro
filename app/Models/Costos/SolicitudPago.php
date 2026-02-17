<?php

namespace App\Models\Costos;

use App\Models\Departamento;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @use HasFactory<\Database\Factories\Costos\SolicitudPagoFactory>
 */
class SolicitudPago extends Model
{
    use HasFactory;

    protected $table = 'costos_solicitudes_pago';

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
        'comprobante_aprobacion_presupuesto',
        'estatus',
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
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $solicitud) {
            if (empty($solicitud->folio)) {
                $year = now()->year;
                $last = DB::table('costos_solicitudes_pago')
                    ->where('folio', 'like', "SP-{$year}-%")
                    ->max('folio');

                $next = $last
                    ? ((int) substr($last, -4)) + 1
                    : 1;

                $solicitud->folio = sprintf('SP-%d-%04d', $year, $next);
            }
        });
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
