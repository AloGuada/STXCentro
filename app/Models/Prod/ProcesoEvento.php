<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Número de evento del export de planta que paga un proceso (75 soldadura,
 * 85 pintura).
 *
 * El evento es unique en toda la tabla: si el mismo número colgara de dos
 * procesos, un movimiento se cargaría a los dos y la pieza se pagaría doble.
 */
class ProcesoEvento extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\ProcesoEventoFactory> */
    use HasFactory;

    protected $table = 'prod_proceso_eventos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proceso_id',
        'evento',
        'descripcion',
    ];

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }
}
