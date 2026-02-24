<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retencion extends Model
{
    use HasFactory;

    protected $table = 'cob_retenciones';

    /** @var list<string> */
    protected $fillable = [
        'estimacion_id',
        'tipo_retencion_id',
        'monto',
        'moneda',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function estimacion(): BelongsTo
    {
        return $this->belongsTo(Estimacion::class, 'estimacion_id');
    }

    public function tipoRetencion(): BelongsTo
    {
        return $this->belongsTo(TipoRetencion::class, 'tipo_retencion_id');
    }
}
