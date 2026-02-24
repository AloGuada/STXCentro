<?php

namespace App\Models\Cob;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimacionEstadoHistorial extends Model
{
    use HasFactory;

    protected $table = 'cob_estimacion_estado_historial';

    /** @var list<string> */
    protected $fillable = [
        'estimacion_id',
        'estado_anterior',
        'estado_nuevo',
        'folio',
        'usuario_id',
        'comentario',
        'fecha_cambio',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_cambio' => 'datetime',
        ];
    }

    public function estimacion(): BelongsTo
    {
        return $this->belongsTo(Estimacion::class, 'estimacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
