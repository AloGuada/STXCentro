<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Cancelacion extends Model
{
    protected $table = 'costos_cancelaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cancelable_type',
        'cancelable_id',
        'motivo',
        'usuario_id',
        'cancelado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelado_at' => 'datetime',
        ];
    }

    public function cancelable(): MorphTo
    {
        return $this->morphTo();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
