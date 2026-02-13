<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfectacionHistorial extends Model
{
    protected $table = 'costos_afectaciones_historial';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'afectacion_id',
        'estatus_anterior',
        'estatus_nuevo',
        'fecha',
        'usuario_id',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function afectacion(): BelongsTo
    {
        return $this->belongsTo(AfectacionPresupuestal::class, 'afectacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
