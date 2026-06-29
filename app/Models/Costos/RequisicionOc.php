<?php

namespace App\Models\Costos;

use App\Enums\Costos\ModoPago;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadatos de una OC planeada de la requisición (modo de pago, fecha de
 * entrega, notas). Una fila por (requisicion, proveedor, numero_oc). Las
 * líneas y cantidades viven en RequisicionSeleccion; aquí solo lo que el
 * aprobador valida y lo que OrdenCompraGenerator necesita al liberar.
 */
class RequisicionOc extends Model
{
    protected $table = 'costos_requisicion_ocs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_id',
        'proveedor_id',
        'numero_oc',
        'modo_pago',
        'metodo_pago',
        'fecha_entrega',
        'notas',
        'pagos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero_oc' => 'integer',
            'modo_pago' => ModoPago::class,
            'fecha_entrega' => 'date',
            'pagos' => 'array',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
}
