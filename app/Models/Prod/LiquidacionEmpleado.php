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
        'porcentaje',
        'monto_asignado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'monto_asignado' => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class, 'liquidacion_id');
    }
}
