<?php

namespace App\Models\Costos;

use App\Enums\Alm\ProductoTipo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
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
        'tipo',
        'controla_inventario',
        'se_controla_por_pieza',
        'requiere_verificacion',
        'stock_minimo',
        'imagen',
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
            'tipo' => ProductoTipo::class,
            'controla_inventario' => 'boolean',
            'se_controla_por_pieza' => 'boolean',
            'requiere_verificacion' => 'boolean',
            'stock_minimo' => 'decimal:3',
        ];
    }

    /**
     * Los que no llevan kardex se saltan el ledger sin error: se compran y se
     * reciben, pero no hay nada que almacenar (fletes, maniobras, servicios).
     */
    public function scopeDeInventario(Builder $query): Builder
    {
        return $query->where('controla_inventario', true);
    }

    /**
     * Lo que Compras tecleó al vuelo y nadie ha clasificado: sin código y fuera
     * del inventario. Es la bandeja de entrada de la pantalla de Artículos —
     * mientras estén aquí, comprarlos no mueve existencia.
     */
    public function scopeSinClasificar(Builder $query): Builder
    {
        return $query->whereNull('codigo')->where('controla_inventario', false);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ProductoPrecio::class)->latest('fecha');
    }

    /**
     * @return HasMany<RequisicionDetalle, $this>
     */
    public function requisicionDetalles(): HasMany
    {
        return $this->hasMany(RequisicionDetalle::class, 'producto_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
