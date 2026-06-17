<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entidad comercial que agrupa una o más obras (centros de costo/ejecución).
 * Las condiciones contractuales (anticipo, garantía, IVA) viven aquí; las
 * estimaciones se cobran a nivel proyecto sumando las partidas de sus obras.
 */
class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';

    /** @var list<string> */
    protected $fillable = [
        'no',
        'descripcion',
        'cliente_id',
        'tipo_contrato',
        'monto',
        'monto_iva',
        'anticipo',
        'garantia',
        'estatus',
        'activa',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_iva' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'garantia' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** Todas las obras del proyecto (base + sub-obras/adicionales). */
    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id');
    }

    public function obrasBase(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id')->where('tipo', 'base');
    }

    public function subObras(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id')->where('tipo', 'adicional');
    }

    public function estimaciones(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\Estimacion::class, 'proyecto_id');
    }
}
