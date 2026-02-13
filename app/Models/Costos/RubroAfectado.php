<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RubroAfectado extends Model
{
    protected $table = 'costos_rubros_afectados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entrada_type',
        'entrada_id',
        'obra_rubro_id',
        'monto',
        'sobre_giro',
        'descripcion',
        'tipo_movimiento',
        'estatus',
        'usuario_aplica_id',
        'fecha_aplicacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'sobre_giro' => 'boolean',
            'fecha_aplicacion' => 'datetime',
        ];
    }

    public function entrada(): MorphTo
    {
        return $this->morphTo();
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }

    public function usuarioAplica(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_aplica_id');
    }
}
