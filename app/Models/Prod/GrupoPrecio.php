<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoPrecio extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioFactory> */
    use HasFactory;

    protected $table = 'prod_grupo_precios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'precio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    public function marcaGrupos(): HasMany
    {
        return $this->hasMany(MarcaGrupo::class, 'grupo_precio_id');
    }
}
