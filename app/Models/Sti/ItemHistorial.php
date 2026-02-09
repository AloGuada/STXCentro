<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ItemHistorial extends Model
{
    protected $table = 'sti_items_historial';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'equipo_id',
        'tecnico_id',
        'relacionable_type',
        'relacionable_id',
        'accion',
        'fecha',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_id');
    }

    public function relacionable(): MorphTo
    {
        return $this->morphTo();
    }
}
