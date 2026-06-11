<?php

namespace App\Models\Cotiz;

use App\Models\Concerns\HasEditLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarjeta: partida/marca consolidada que reúne los registros de una o varias generadoras
 * (más insumos manuales y factores) y produce el importe facturable. Editable bajo lock
 * estricto por usuario (HasEditLock).
 *
 * Los importes/kg NO se persisten como fuente de verdad: `importe_materiales` y `kilos_reales`
 * son un cache (M039) que recalcula el motor PHP; la fuente son los inputs (registros/factores).
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaFactory>
 */
class Tarjeta extends Model
{
    use HasEditLock, HasFactory;

    protected $table = 'cotiz_tarjetas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'descripcion',
        'orden',
        'importe_materiales',
        'kilos_reales',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'importe_materiales' => 'decimal:4',
            'kilos_reales' => 'decimal:4',
            'locked_at' => 'datetime',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function generadoras(): BelongsToMany
    {
        return $this->belongsToMany(
            Generadora::class,
            'cotiz_tarjeta_generadoras',
            'tarjeta_id',
            'generadora_id',
        )->withTimestamps();
    }

    public function registros(): HasMany
    {
        return $this->hasMany(TarjetaRegistro::class, 'tarjeta_id');
    }

    public function factores(): HasMany
    {
        return $this->hasMany(TarjetaFactor::class, 'tarjeta_id');
    }

    public function estructuras(): HasMany
    {
        return $this->hasMany(TarjetaEstructura::class, 'tarjeta_id');
    }

    public function categoriasKilos(): HasMany
    {
        return $this->hasMany(TarjetaCategoriaKilos::class, 'tarjeta_id');
    }

    public function kilosReales(): HasMany
    {
        return $this->hasMany(TarjetaKilosReal::class, 'tarjeta_id');
    }

    public function insumoPrecios(): HasMany
    {
        return $this->hasMany(TarjetaInsumoPrecio::class, 'tarjeta_id');
    }
}
