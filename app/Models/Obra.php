<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'obras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'no',
        'descripcion',
    ];

    public function piezas(): HasMany
    {
        return $this->hasMany(Pieza::class, 'obra_id');
    }
}
