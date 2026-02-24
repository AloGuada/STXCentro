<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimacionPago extends Model
{
    use HasFactory;

    protected $table = 'cob_estimaciones_pagos';

    /** @var list<string> */
    protected $fillable = [
        'estimacion_id',
        'monto_pagado',
        'fecha_pago',
        'folio',
        'comprobante',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto_pagado' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    public function estimacion(): BelongsTo
    {
        return $this->belongsTo(Estimacion::class, 'estimacion_id');
    }
}
