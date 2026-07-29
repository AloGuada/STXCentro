<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionEmpleado extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\LiquidacionEmpleadoFactory> */
    use HasFactory;

    protected $table = 'prod_liquidacion_empleados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'liquidacion_id',
        'nombre',
        'no_empleado',
        'dias_pagados',
        'categoria_nombre',
        'categoria_valor',
        'salario_diario',
        'sueldo_base',
        'monto_destajo',
        'porcentaje',
        'monto_asignado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias_pagados' => 'integer',
            'categoria_valor' => 'integer',
            'salario_diario' => 'decimal:2',
            'sueldo_base' => 'decimal:2',
            'monto_destajo' => 'decimal:2',
            'porcentaje' => 'decimal:2',
            'monto_asignado' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class, 'liquidacion_id');
    }
}
