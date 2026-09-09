<?php

namespace App\Models\Alm;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón del resguardo: una pieza con serie (cantidad 1) o N de un activo
 * por cantidad. Vuelve entero o por partes; `pendiente()` es lo que sigue
 * afuera.
 */
class PrestamoDetalle extends Model
{
    protected $table = 'alm_prestamo_detalle';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'prestamo_id',
        'articulo_id',
        'activo_id',
        'cantidad',
        'cantidad_devuelta',
        'condicion_salida',
        'condicion_retorno',
        'devuelto_en',
        'recibido_por',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'cantidad_devuelta' => 'decimal:4',
            'devuelto_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Prestamo, $this>
     */
    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return BelongsTo<Activo, $this>
     */
    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function receptor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function esPorPieza(): bool
    {
        return $this->activo_id !== null;
    }

    public function pendiente(): float
    {
        return max(0.0, (float) $this->cantidad - (float) $this->cantidad_devuelta);
    }

    public function estaDevuelto(): bool
    {
        return $this->pendiente() <= (float) config('costos.epsilon_cantidad');
    }
}
