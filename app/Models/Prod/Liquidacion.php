<?php

namespace App\Models\Prod;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\LiquidacionFactory> */
    use HasFactory;

    protected $table = 'prod_liquidaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'corte_id',
        'grupo_trabajo_id',
        'total_kilos',
        'total_produccion',
        'total_extras',
        'total_final',
        'generado_en',
        'generado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_kilos' => 'decimal:3',
            'total_produccion' => 'decimal:2',
            'total_extras' => 'decimal:2',
            'total_final' => 'decimal:2',
            'generado_en' => 'datetime',
        ];
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(Corte::class, 'corte_id');
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }

    public function generador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'generado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class, 'liquidacion_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(Extra::class, 'liquidacion_id');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(LiquidacionEmpleado::class, 'liquidacion_id');
    }
}
