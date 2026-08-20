<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evidencia fotográfica del informe de PND.
 *
 * `ruta` es la ruta dentro del disco `public`, igual que el resto de adjuntos
 * del mono.
 *
 * @use HasFactory<\Database\Factories\Qal\PndFotoFactory>
 */
class PndFoto extends Model
{
    use HasFactory;

    protected $table = 'qal_pnd_fotos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_pnd_reporte_id',
        'ruta',
        'nombre',
    ];

    /**
     * @return BelongsTo<PndReporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(PndReporte::class, 'qal_pnd_reporte_id');
    }
}
