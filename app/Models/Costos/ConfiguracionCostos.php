<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración (fila única) del módulo de Costos, editable desde el admin:
 * días de apartado de presupuesto y plazos de cancelación automática de
 * requisiciones y solicitudes de pago no aprobadas.
 */
class ConfiguracionCostos extends Model
{
    protected $table = 'costos_configuracion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dias_apartado',
        'dias_cancelar_requisicion',
        'dias_cancelar_solicitud',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias_apartado' => 'integer',
            'dias_cancelar_requisicion' => 'integer',
            'dias_cancelar_solicitud' => 'integer',
        ];
    }

    /**
     * Devuelve la fila única de configuración, creándola con los valores por
     * defecto si aún no existe.
     */
    public static function actual(): self
    {
        return static::firstOrCreate([], [
            'dias_apartado' => 5,
            'dias_cancelar_requisicion' => 10,
            'dias_cancelar_solicitud' => 10,
        ]);
    }
}
