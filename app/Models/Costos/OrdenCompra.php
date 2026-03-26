<?php

namespace App\Models\Costos;

use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @use HasFactory<\Database\Factories\Costos\OrdenCompraFactory>
 */
class OrdenCompra extends Model
{
    use HasFactory;

    protected $table = 'costos_ordenes_compra';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'referencia',
        'proveedor_id',
        'obra_id',
        'departamento_id',
        'creado_por',
        'moneda',
        'total',
        'fecha_entrega_esperada',
        'notas',
        'estatus',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'fecha_entrega_esperada' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $oc) {
            if (empty($oc->folio)) {
                $prefix = sprintf('OC-%s%s', now()->format('Y'), now()->format('m'));
                $last = DB::table('costos_ordenes_compra')
                    ->where('folio', 'like', "{$prefix}%")
                    ->max('folio');

                $next = $last
                    ? ((int) substr($last, -2)) + 1
                    : 1;

                $oc->folio = sprintf('%s%02d', $prefix, $next);
            }
        });
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'orden_compra_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'orden_compra_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function archivo(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'archivo');
    }

    public function pdfFormato(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'pdf_formato');
    }

    public function pdfFirmado(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'pdf_firmado');
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
                ->increment('acumulado', (float) $detalle->monto);

            $obraRubro = ObraRubro::find($detalle->obra_rubro_id);
            $disponible = (float) $obraRubro->presupuestado - (float) $obraRubro->acumulado;

            $this->rubrosAfectados()->create([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'monto' => $detalle->monto,
                'sobre_giro' => $disponible < 0,
                'descripcion' => $obraRubro->rubro?->descripcion,
                'tipo_movimiento' => 'cargo',
                'estatus' => 'aplicado',
                'usuario_aplica_id' => $userId,
                'fecha_aplicacion' => now(),
            ]);
        }
    }

    /**
     * Recalcula el estatus de la OC basado en el estado agregado de sus facturas.
     */
    public function recalcularEstatus(): void
    {
        $facturas = $this->facturas()->where('estatus', '!=', 'cancelada')->get();

        if ($facturas->isEmpty()) {
            $this->update(['estatus' => 'pendiente_factura']);

            return;
        }

        if ($facturas->every(fn ($f) => $f->estatus === 'pagada')) {
            $this->update(['estatus' => 'pagada']);

            return;
        }

        if ($facturas->every(fn ($f) => in_array($f->estatus, ['pendiente_pago', 'pagada']))) {
            $this->update(['estatus' => 'pendiente_pago']);

            return;
        }

        if ($facturas->every(fn ($f) => in_array($f->estatus, ['pendiente_aprobacion', 'pendiente_pago', 'pagada']))) {
            $this->update(['estatus' => 'pendiente_aprobacion']);

            return;
        }

        $this->update(['estatus' => 'pendiente_entrega']);
    }

    /**
     * Revierte el impacto presupuestal.
     */
    public function revertirImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        foreach ($this->detalles as $detalle) {
            ObraRubro::where('id', $detalle->obra_rubro_id)
                ->decrement('acumulado', (float) $detalle->monto);
        }

        $this->rubrosAfectados()->create([
            'obra_rubro_id' => $this->detalles->first()?->obra_rubro_id ?? 0,
            'monto' => $this->total,
            'descripcion' => 'Cancelación de orden de compra',
            'tipo_movimiento' => 'abono',
            'estatus' => 'cancelado',
            'usuario_aplica_id' => $userId,
            'fecha_aplicacion' => now(),
        ]);
    }
}
