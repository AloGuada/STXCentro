<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarifa por kilo de un proceso dentro de un grupo de precios.
 */
class GrupoPrecioProceso extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\GrupoPrecioProcesoFactory> */
    use HasFactory;

    protected $table = 'prod_grupo_precio_procesos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'grupo_precio_id',
        'proceso_id',
        'precio_kilo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_kilo' => 'decimal:4',
        ];
    }

    public function grupoPrecio(): BelongsTo
    {
        return $this->belongsTo(GrupoPrecio::class, 'grupo_precio_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class, 'proceso_id');
    }
}
