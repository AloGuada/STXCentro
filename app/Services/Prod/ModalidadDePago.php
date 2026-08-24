<?php

namespace App\Services\Prod;

use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\Pieza;

/**
 * Con qué regla se paga una marca: por kilo o por subproceso, y a qué precio.
 *
 * Vive aparte porque las tres puertas que escriben o valoran producción —la
 * captura manual, el import de CSV y el cierre del destajo— tienen que contestar
 * exactamente lo mismo. Cuando esto era un método privado del generador, la
 * captura no tenía forma de saber si debía pedir un subproceso.
 *
 * El precio se resuelve por marca pero se pregunta una vez por pieza: una semana
 * de 500 QS serían 500 consultas iguales, así que se cachea por corrida. Quien
 * cambie un grupo de precios a media petición debe pedir una instancia nueva.
 */
class ModalidadDePago
{
    /** @var array<string, GrupoPrecioConcepto|null> */
    private array $asignaciones = [];

    public function asignacion(int $conceptoId, int $obraId): ?GrupoPrecioConcepto
    {
        $clave = $conceptoId.'|'.$obraId;

        // Con `??=` la marca sin grupo asignado nunca quedaba cacheada —null es
        // justo lo que el operador toma por "todavía no calculado"— y volvía a
        // consultar en cada pregunta: capturar 20 QS eran 40 consultas iguales.
        if (! array_key_exists($clave, $this->asignaciones)) {
            $this->asignaciones[$clave] = GrupoPrecioConcepto::query()
                ->whereHas('grupoPrecio', fn ($q) => $q->where('obra_id', $obraId))
                ->where('concepto_id', $conceptoId)
                ->with(['grupoPrecio.precios', 'grupoPrecio.subprocesos'])
                ->first();
        }

        return $this->asignaciones[$clave];
    }

    public function grupo(int $conceptoId, int $obraId): ?GrupoPrecio
    {
        return $this->asignacion($conceptoId, $obraId)?->grupoPrecio;
    }

    /**
     * El grupo de precios de una pieza física. La obra sale de la marca, que es
     * quien la ancla al presupuesto.
     */
    public function grupoDePieza(Pieza $pieza): ?GrupoPrecio
    {
        $marca = $pieza->marca;

        if ($marca === null) {
            return null;
        }

        return $this->grupo((int) $marca->id, (int) $marca->obra_id);
    }

    /**
     * Una marca sin grupo asignado se paga en cero, pero por kilo: es la
     * modalidad histórica y la que no pide capturar nada extra.
     */
    public function pagaPorSubproceso(int $conceptoId, int $obraId): bool
    {
        return $this->grupo($conceptoId, $obraId)?->pagaPorSubproceso() ?? false;
    }

    public function piezaPagaPorSubproceso(Pieza $pieza): bool
    {
        return $this->grupoDePieza($pieza)?->pagaPorSubproceso() ?? false;
    }

    public function precioKilo(int $conceptoId, int $obraId, int $procesoId): float
    {
        return (float) ($this->grupo($conceptoId, $obraId)?->precioKilo($procesoId) ?? 0);
    }

    public function precioSubproceso(int $conceptoId, int $obraId, int $subprocesoId): float
    {
        return (float) ($this->grupo($conceptoId, $obraId)?->precioSubproceso($subprocesoId) ?? 0);
    }

    /**
     * Los pasos que se pueden capturar para una pieza en un proceso: los del
     * grupo de la marca, activos y de ese proceso.
     *
     * @return \Illuminate\Support\Collection<int, GrupoPrecioSubproceso>
     */
    public function subprocesosDePieza(Pieza $pieza, ?int $procesoId = null): \Illuminate\Support\Collection
    {
        return ($this->grupoDePieza($pieza)?->subprocesos ?? collect())
            ->filter(fn (GrupoPrecioSubproceso $sub): bool => $sub->activo)
            ->filter(fn (GrupoPrecioSubproceso $sub): bool => $procesoId === null || (int) $sub->proceso_id === $procesoId)
            ->sortBy('orden')
            ->values();
    }

    /**
     * Por qué esta pieza no puede capturarse con este subproceso, o null si sí.
     *
     * Es la validación que impide cobrar el "Armado" de un grupo en una pieza de
     * otro: el subproceso pone el precio, así que capturarlo cruzado pagaría una
     * tarifa que nadie autorizó para esa marca.
     */
    public function errorDeSubproceso(Pieza $pieza, int $procesoId, ?GrupoPrecioSubproceso $subproceso): ?string
    {
        $grupo = $this->grupoDePieza($pieza);
        $etiqueta = $pieza->etiqueta();

        if ($grupo === null || ! $grupo->pagaPorSubproceso()) {
            return $subproceso === null
                ? null
                : "La pieza {$etiqueta} se paga por kilo; no lleva subproceso.";
        }

        if ($subproceso === null) {
            return "La pieza {$etiqueta} pertenece a un grupo que paga por subproceso: elige cuál se hizo.";
        }

        if ((int) $subproceso->grupo_precio_id !== (int) $grupo->id) {
            return "El subproceso \"{$subproceso->nombre}\" no es del grupo de precios de la pieza {$etiqueta}.";
        }

        if ((int) $subproceso->proceso_id !== $procesoId) {
            return "El subproceso \"{$subproceso->nombre}\" no pertenece al proceso seleccionado.";
        }

        if (! $subproceso->activo) {
            return "El subproceso \"{$subproceso->nombre}\" está desactivado.";
        }

        return null;
    }
}
