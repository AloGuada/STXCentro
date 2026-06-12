<?php

namespace App\Models\Cotiz;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot nombrado del árbol de inputs de una obra (Cotización Fase 5.5).
 * Historial LINEAL (sin ramas); el `snapshot` guarda solo inputs (el cálculo se deriva).
 *
 * @use HasFactory<\Database\Factories\Cotiz\ObraVersionFactory>
 */
class ObraVersion extends Model
{
    use HasFactory;

    protected $table = 'cotiz_obra_versiones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'nombre',
        'nota',
        'auto',
        'creado_por',
        'snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
