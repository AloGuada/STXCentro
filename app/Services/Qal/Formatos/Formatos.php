<?php

namespace App\Services\Qal\Formatos;

/**
 * Los formatos que existen, por su clave de URL. Primero los del dosier y
 * luego los internos, que es como los ofrece la pantalla.
 */
class Formatos
{
    /** @var array<string, class-string<Formato>> */
    private const CLASES = [
        'visual-soldadura' => VisualSoldadura::class,
        'mapeo' => MapeoDeJuntas::class,
        'espesores' => Espesores::class,
        'visual-pintura' => VisualPintura::class,
        'adherencia' => PruebaDeAdherencia::class,
        'soldadura' => Soldadura::class,
        'armado' => ArmadoVestido::class,
        'control-pintura' => ControlPintura::class,
        'primera' => PrimeraTransformacion::class,
    ];

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::CLASES);
    }

    /** @return list<Formato> */
    public function todos(): array
    {
        $formatos = array_map(fn (string $clase): Formato => app($clase), array_values(self::CLASES));

        usort($formatos, fn (Formato $a, Formato $b): int => $b->paraElDosier() <=> $a->paraElDosier());

        return $formatos;
    }

    public function de(string $clave): ?Formato
    {
        $clase = self::CLASES[$clave] ?? null;

        return $clase ? app($clase) : null;
    }
}
