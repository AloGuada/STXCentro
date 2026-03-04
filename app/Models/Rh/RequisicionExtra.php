<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisicionExtra extends Model
{
    /** @use HasFactory<\Database\Factories\Rh\RequisicionExtraFactory> */
    use HasFactory;

    protected $table = 'rh_requisicion_extra';

    /** @var list<string> */
    protected $fillable = [
        'requisicion_id',
        'salario_mensual',
        'salario_diario',
        'periodicidad_pago',
        'prestaciones',
        'bonos',
        'horario',
        'tipo_jornada',
        'beneficios_adicionales',
        'observaciones',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'salario_mensual' => 'decimal:2',
            'salario_diario' => 'decimal:2',
        ];
    }

    public function requisicion(): BelongsTo
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }
}
