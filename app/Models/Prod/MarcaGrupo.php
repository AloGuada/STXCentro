<?php

namespace App\Models\Prod;

use App\Models\Pieza;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarcaGrupo extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\MarcaGrupoFactory> */
    use HasFactory;

    protected $table = 'prod_marca_grupo';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pieza_id',
        'grupo_precio_id',
    ];

    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    public function grupoPrecio(): BelongsTo
    {
        return $this->belongsTo(GrupoPrecio::class, 'grupo_precio_id');
    }
}
