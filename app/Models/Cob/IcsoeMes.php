<?php

namespace App\Models\Cob;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mes del desglose ICSOE: lo que el IMSS espera comprobar y lo que
 * realmente se cotizó.
 */
class IcsoeMes extends Model
{
    use HasFactory;

    protected $table = 'cob_icsoe_meses';

    /** @var list<string> */
    protected $fillable = [
        'seguimiento_id',
        'anio',
        'mes',
        'dias_proyecto',
        'sbc',
        'sbc_aplicado',
        'mo_estimada',
        'dias_cotizados',
        'mo_real',
        'fuera_de_rango',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'dias_proyecto' => 'integer',
            'sbc' => 'decimal:2',
            'sbc_aplicado' => 'decimal:2',
            'mo_estimada' => 'decimal:2',
            'dias_cotizados' => 'decimal:2',
            'mo_real' => 'decimal:2',
            'fuera_de_rango' => 'boolean',
        ];
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(IcsoeSeguimiento::class, 'seguimiento_id');
    }
}
