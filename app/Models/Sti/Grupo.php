<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grupo extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\GrupoFactory> */
    use HasFactory;

    protected $table = 'sti_grupos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'equipo_id',
        'item_id',
    ];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
