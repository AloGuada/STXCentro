<?php

namespace App\Models\Prod;

use App\Models\Concepto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoPrecioConcepto extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioConceptoFactory> */
    use HasFactory;

    protected $table = 'prod_grupo_precio_conceptos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_precio_id',
        'concepto_id',
    ];

    public function grupoPrecio(): BelongsTo
    {
        return $this->belongsTo(GrupoPrecio::class, 'grupo_precio_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'concepto_id');
    }
}
