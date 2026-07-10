<?php

namespace App\Models\Costos;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una columna-opción de un proveedor dentro del comparativo de una requisición.
 * Un proveedor puede cotizar varias opciones (ej. distintas marcas); cada opción
 * es una columna con sus precios por partida.
 *
 * @use HasFactory<\Database\Factories\Costos\RequisicionCotizacionOpcionFactory>
 */
class RequisicionCotizacionOpcion extends Model
{
    use HasFactory;

    protected $table = 'costos_requisicion_cotizacion_opcion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requisicion_id',
        'proveedor_id',
        'etiqueta',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(RequisicionCotizacionPrecio::class, 'opcion_id');
    }

    public function nombreMostrar(): string
    {
        return $this->etiqueta ?: "Opción {$this->orden}";
    }
}
