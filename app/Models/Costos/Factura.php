<?php

namespace App\Models\Costos;

use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

/**
 * @use HasFactory<\Database\Factories\Costos\FacturaFactory>
 */
class Factura extends Model
{
    use HasFactory;

    protected $table = 'costos_facturas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'orden_compra_id',
        'proveedor_id',
        'uuid_fiscal',
        'folio_fiscal',
        'ruta_xml',
        'ruta_pdf',
        'subtotal',
        'iva',
        'total',
        'moneda',
        'fecha_factura',
        'estatus',
        'notas',
        'aprobada_costos',
        'aprobada_costos_por',
        'aprobada_costos_at',
        'aceptada_contabilidad',
        'aceptada_contabilidad_por',
        'aceptada_contabilidad_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_factura' => 'date',
            'aprobada_costos' => 'boolean',
            'aprobada_costos_at' => 'datetime',
            'aceptada_contabilidad' => 'boolean',
            'aceptada_contabilidad_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $factura) {
            if (empty($factura->folio)) {
                $prefix = sprintf('FA-%s%s', now()->format('Y'), now()->format('m'));
                $last = DB::table('costos_facturas')
                    ->where('folio', 'like', "{$prefix}%")
                    ->max('folio');

                $next = $last
                    ? ((int) substr($last, -2)) + 1
                    : 1;

                $factura->folio = sprintf('%s%02d', $prefix, $next);
            }
        });
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'factura_id');
    }

    public function pago(): MorphOne
    {
        return $this->morphOne(Pago::class, 'pagable');
    }

    public function aprobadaCostosPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobada_costos_por');
    }

    public function aceptadaContabilidadPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aceptada_contabilidad_por');
    }
}
