<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\CategoriaFactory> */
    use HasFactory;

    protected $table = 'prod_categorias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
    ];

    /**
     * @return HasMany<Concepto, $this>
     */
    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class, 'categoria_id');
    }
}
