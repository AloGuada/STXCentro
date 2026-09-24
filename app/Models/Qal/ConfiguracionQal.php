<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración (fila única) del módulo de Calidad, editable desde el admin.
 *
 * `formularios_segun_avance`: encendido, Formularios sólo deja escanear y
 * registrar las piezas que Producción programó (ver PiezasHabilitadas).
 */
class ConfiguracionQal extends Model
{
    protected $table = 'qal_configuracion';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'formularios_segun_avance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'formularios_segun_avance' => 'boolean',
        ];
    }

    /** Fila única de configuración, creándola con el filtro encendido si aún no existe. */
    public static function actual(): self
    {
        return static::firstOrCreate([], ['formularios_segun_avance' => true]);
    }
}
