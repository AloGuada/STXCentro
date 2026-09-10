<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;

/**
 * Lo que la captura deduce sola, del lado del servidor.
 *
 * El formulario adelanta estas reglas para que el inspector vea el resultado
 * mientras captura (`components/qal/captura/reglas.ts`), pero no es la
 * autoridad: una tablet con una versión vieja del front, o una petición a mano,
 * guardaría otra cosa. Lo que se guarda sale de aquí.
 */
class ReglasInspeccion
{
    /** El punto de junta que un filete bajo el nominal marca como defecto. */
    public const PUNTO_PERFIL = 'm_perfil';

    /** Barrenos de 1ª: se deducen de la posición y el diámetro, no se teclean. */
    public function barrenos(?string $posicion, ?string $diametro): ?string
    {
        if ($posicion === 'Incorrecta' || $diametro === 'Con defecto') {
            return 'Con defecto';
        }

        if ($posicion === 'OK' && in_array($diametro, ['OK', 'n/a'], true)) {
            return 'OK';
        }

        return null;
    }

    /** Dimensional de 2ª: si la longitud o la distancia entre placas falla, no está OK. */
    public function dimensional(?string $longitud, ?string $placas): ?string
    {
        if ($longitud === 'No OK' || $placas === 'No OK') {
            return 'No OK';
        }

        if ($longitud === 'OK' && in_array($placas, ['OK', 'n/a'], true)) {
            return 'OK';
        }

        return null;
    }

    /**
     * El filete medido contra el tamaño del plano. Nulo si falta alguna de las
     * dos medidas: sin ellas no se puede afirmar nada.
     *
     * Un filete por debajo del nominal es un defecto de resistencia, no de
     * aspecto; por eso lo decide la cuenta y no el ojo del inspector.
     */
    public function fileteCumple(?float $requerido, ?float $medido): ?bool
    {
        if ($requerido === null || $medido === null) {
            return null;
        }

        return round($medido - $requerido, 2) >= 0;
    }

    /**
     * En armado y vestido la pieza todavía no es producto terminado: puede
     * quedar pendiente o rechazada, pero no liberada.
     */
    public function admiteEstatus(FaseTransformacion $fase, ?Subetapa $subetapa, EstatusInspeccion $estatus): bool
    {
        return ! ($fase === FaseTransformacion::Segunda
            && $subetapa === Subetapa::ArmadoVestido
            && $estatus === EstatusInspeccion::Liberado);
    }

    /**
     * Una pieza a la que le faltan elementos por vestir no está bien: se
     * rechaza sola, diga lo que diga el botón de estatus.
     *
     * @param  array<string, mixed>  $puntos
     */
    public function estatusFinal(EstatusInspeccion $estatus, array $puntos): EstatusInspeccion
    {
        return (int) ($puntos['p2_faltavest'] ?? 0) > 0 ? EstatusInspeccion::Rechazado : $estatus;
    }
}
