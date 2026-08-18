<?php

namespace App\Models\Alm;

use App\Enums\Alm\UbicacionTipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lugar físico dentro de un almacén: pasillo, rack, nivel, contenedor o zona.
 *
 * Cuelgan unos de otros, así que la dirección completa de algo se lee subiendo
 * por los padres (`Pasillo A / Rack A-1 / Nivel 2`). No confundir con `Area`,
 * que clasifica el artículo y no dice dónde está.
 *
 * @use HasFactory<\Database\Factories\Alm\UbicacionFactory>
 */
class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'alm_ubicaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'almacen_id',
        'padre_id',
        'codigo',
        'nombre',
        'tipo',
        'activa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => UbicacionTipo::class,
            'activa' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRaices(Builder $query): Builder
    {
        return $query->whereNull('padre_id');
    }

    /**
     * La dirección legible, subiendo por los padres: `Pasillo A / Rack A-1`.
     *
     * Carga la cadena a mano en vez de con `with('padre.padre...')` porque la
     * profundidad no está acotada: hoy son tres niveles, y nada impide que
     * alguien cuelgue un cuarto.
     */
    public function ruta(): string
    {
        $partes = [];
        $actual = $this;

        while ($actual !== null) {
            array_unshift($partes, $actual->nombre);
            $actual = $actual->padre;
        }

        return implode(' / ', $partes);
    }
}
