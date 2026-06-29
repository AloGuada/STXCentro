<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catálogo de Costos (código + descripción + unidad). Lo
 * administran Compras y Almacén; se referencia desde las partidas de
 * requisición/OC y acumula un histórico de precios desde las cotizaciones.
 *
 * @use HasFactory<\Database\Factories\Costos\ProductoFactory>
 */
class Producto extends Model
{
    use HasFactory;

    protected $table = 'costos_productos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'descripcion',
        'unidad',
        'activo',
        'creado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ProductoPrecio::class)->latest('fecha');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
