<?php

namespace App\Models\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @use HasFactory<\Database\Factories\Costos\FacturaFactory>
 */
class Factura extends Model
{
    use HasFactory, HasMonthlyFolio, HasStateMachine;

    protected $table = 'costos_facturas';

    protected static string $folioPrefix = 'FA';

    protected static string $stateEnum = FacturaEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'orden_compra_id',
        'proveedor_id',
        'uuid_fiscal',
        'folio_fiscal',
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
            'estatus' => FacturaEstatus::class,
        ];
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function mediaXml(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'xml');
    }

    public function mediaPdf(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable')->where('descripcion', 'pdf');
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
