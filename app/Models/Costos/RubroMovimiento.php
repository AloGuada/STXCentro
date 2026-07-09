<?php

namespace App\Models\Costos;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del libro de movimientos del acumulado presupuestal: registra cada
 * cambio (cargo o reverso) al `acumulado` de un centro de costos, con el saldo
 * antes y después. Escrito exclusivamente por {@see \App\Services\Costos\AcumuladoLedger}.
 */
class RubroMovimiento extends Model
{
    protected $table = 'costos_rubro_movimientos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_rubro_id',
        'rubro_afectado_id',
        'tipo',
        'monto',
        'saldo_antes',
        'saldo_despues',
        'motivo',
        'usuario_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'saldo_antes' => 'decimal:2',
            'saldo_despues' => 'decimal:2',
        ];
    }

    public function obraRubro(): BelongsTo
    {
        return $this->belongsTo(ObraRubro::class, 'obra_rubro_id');
    }

    public function rubroAfectado(): BelongsTo
    {
        return $this->belongsTo(RubroAfectado::class, 'rubro_afectado_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
