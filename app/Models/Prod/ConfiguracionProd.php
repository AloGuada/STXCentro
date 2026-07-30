<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración (fila única) del módulo de Producción, editable desde el admin.
 *
 * El salario mínimo diario es la base del pago garantizado: cada día asistido
 * lo cobra el trabajador aunque el destajo del grupo no alcance.
 */
class ConfiguracionProd extends Model
{
    protected $table = 'prod_configuracion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'salario_minimo_diario',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salario_minimo_diario' => 'decimal:2',
        ];
    }

    /** Fila única de configuración, creándola vacía si aún no existe. */
    public static function actual(): self
    {
        return static::firstOrCreate([], ['salario_minimo_diario' => 0]);
    }
}
