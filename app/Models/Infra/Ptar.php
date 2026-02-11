<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ptar extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\PtarFactory> */
    use HasFactory;

    protected $table = 'infra_ptar';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'soplador_activa',
        'bomba_activa',
        'nivel_cloro',
        'trampa_solida',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'soplador_activa' => 'boolean',
            'bomba_activa' => 'boolean',
            'trampa_solida' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
