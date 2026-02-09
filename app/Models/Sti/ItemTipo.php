<?php

namespace App\Models\Sti;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemTipo extends Model
{
    /** @use HasFactory<\Database\Factories\Sti\ItemTipoFactory> */
    use HasFactory;

    protected $table = 'sti_items_tipos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'tipo_id');
    }
}
