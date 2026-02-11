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

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class, 'obra_id');
    }

    public function gruposPrecios(): HasMany
    {
        return $this->hasMany(Prod\GrupoPrecio::class, 'obra_id');
    }
}
