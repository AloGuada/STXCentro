<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una tarjeta: o bien importado de una generadora (`generadora_registro_id`),
 * o bien manual (`insumo_id` + `cantidad`). Invariante: exactamente uno de los dos.
 *
 * @use HasFactory<\Database\Factories\Cotiz\TarjetaRegistroFactory>
 */
class TarjetaRegistro extends Model
{
    use HasFactory;

    protected $table = 'cotiz_tarjeta_registros';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarjeta_id',
        'generadora_registro_id',
        'insumo_id',
        'cantidad',
        'importe',
        'validado',
        'tipo_pintura',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:6',
            'importe' => 'decimal:4',
            'validado' => 'boolean',
        ];
    }

    public function esManual(): bool
    {
        return $this->generadora_registro_id === null;
    }

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function generadoraRegistro(): BelongsTo
    {
        return $this->belongsTo(GeneradoraRegistro::class, 'generadora_registro_id');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }
}
