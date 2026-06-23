<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto_pagado' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    /** Comprobantes (uno o varios) del pago. Pagos parciales pueden adjuntar varios. */
    public function comprobantes(): MorphMany
    {
        return $this->morphMany(\App\Models\Media::class, 'mediable');
    }

    public function estimacion(): BelongsTo
    {
        return $this->belongsTo(Estimacion::class, 'estimacion_id');
    }
}
