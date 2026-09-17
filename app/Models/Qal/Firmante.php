<?php

namespace App\Models\Qal;

use App\Enums\Qal\OrigenFirmante;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un lugar de firma en los formatos de Calidad: qué dice bajo la raya y de
 * dónde sale quien firma.
 *
 * @use HasFactory<\Database\Factories\Qal\FirmanteFactory>
 */
class Firmante extends Model
{
    use HasFactory;

    protected $table = 'qal_firmantes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'orden',
        'etiqueta',
        'cargo',
        'origen',
        'usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'origen' => OrigenFirmante::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
