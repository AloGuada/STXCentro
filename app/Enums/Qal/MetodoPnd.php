<?php

namespace App\Enums\Qal;

/**
 * Métodos de prueba no destructiva.
 *
 * Es enum y no catálogo: los define la norma, no la empresa. Nadie debe poder
 * inventar un método desde una pantalla de administración.
 */
enum MetodoPnd: string
{
    case Ut = 'UT';
    case Mt = 'MT';
    case Pt = 'PT';
    case Rt = 'RT';
    case Vt = 'VT';

    public function nombre(): string
    {
        return match ($this) {
            self::Ut => 'Ultrasonido',
            self::Mt => 'Partículas magnéticas',
            self::Pt => 'Líquidos penetrantes',
            self::Rt => 'Radiografía',
            self::Vt => 'Visual de laboratorio',
        };
    }

    /**
     * Los parámetros que el laboratorio suele reportar para este método.
     *
     * Es una ayuda de captura, no una lista cerrada: los parámetros se guardan
     * como clave/valor justamente porque cambian con el método y con el
     * laboratorio. Si el informe trae uno que no está aquí, se teclea y se
     * guarda igual.
     *
     * @return list<string>
     */
    public function parametrosSugeridos(): array
    {
        return match ($this) {
            self::Ut => ['Equipo', 'Frecuencia', 'Palpador', 'Ángulo', 'Acoplante', 'Bloque de calibración'],
            self::Mt => ['Equipo', 'Tipo de partícula', 'Técnica', 'Corriente', 'Iluminación'],
            self::Pt => ['Penetrante', 'Revelador', 'Limpiador', 'Tiempo de penetración', 'Tiempo de revelado'],
            self::Rt => ['Fuente', 'Película', 'Tiempo de exposición', 'Distancia foco-película', 'Densidad'],
            self::Vt => ['Instrumento', 'Iluminación', 'Distancia', 'Ángulo de observación'],
        };
    }

    /** Qué detecta cada uno. Es lo que decide cuál se pacta en el contrato. */
    public function detecta(): string
    {
        return match ($this) {
            self::Ut => 'Discontinuidades internas',
            self::Mt => 'Superficiales y sub-superficiales',
            self::Pt => 'Abiertas a la superficie',
            self::Rt => 'Interna, con placa',
            self::Vt => 'Inspección visual certificada',
        };
    }
}
