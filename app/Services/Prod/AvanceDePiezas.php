<?php

namespace App\Services\Prod;

use App\Models\Prod\Catalogo;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use App\Models\Prod\Registro;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cuánto se ha pagado de cada pieza en cada proceso, y en cada subproceso
 * cuando el grupo de precios paga por pasos.
 *
 * La unidad es la **pieza equivalente**: una pieza vale 1 en cada proceso por el
 * que pasa, y pagarla al 60% consume 0.6 dejando 0.4 para una semana posterior.
 * Así el mismo QS se puede pagar en parcialidades sin rebasar nunca su tope, y
 * soldarlo no consume nada de lo que le toca por pintarlo.
 *
 * El subproceso abre un tope propio dentro del proceso: armar una pieza al 100%
 * no consume nada de lo que le toca por puntearla. Sin esa dimensión en la
 * llave, el primer paso agotaría el tope y los demás se rechazarían.
 *
 * El acumulado se cuenta **por linaje de pieza dentro de la obra, sumando todas
 * las versiones del catálogo**: al versionar, las piezas copiadas son filas
 * nuevas sin registros propios, así que contarlas por `pieza_id` a secas dejaría
 * el avance en cero y permitiría volver a pagar lo ya fabricado. Cada copia
 * recuerda de qué pieza viene (`pieza_origen_id`) y todas comparten la misma
 * raíz.
 *
 * **El servicio cachea lo que lee y vive lo que dure el request.** Calcular el
 * avance de una obra cuesta un recorrido de sus piezas, sus liquidaciones y sus
 * registros; hacerlo una vez por QS —como pasaba al capturar 50 piezas de golpe—
 * es lo que volvía lentas las pantallas. Quien escriba registros y necesite
 * releer el avance actualizado debe pedir una instancia nueva del servicio.
 */
class AvanceDePiezas
{
    /** Lo máximo que se puede pagar de una pieza en un proceso. */
    private const TOPE_POR_PIEZA = 1.0;

    /** Margen para no rechazar por ruido de redondeo del porcentaje. */
    private const EPSILON = 0.0001;

    /**
     * Avance ya calculado, por obra. Ver el docblock de la clase.
     *
     * @var array<int, AvanceDeObra>
     */
    private array $mapas = [];

    /**
     * De qué obra es cada catálogo. Resolverlo pieza por pieza era un N+1 de
     * cientos de consultas en un catálogo grande.
     *
     * @var array<int, int>
     */
    private array $obraPorCatalogo = [];

    /**
     * Avance ya comprometido en la obra, agrupado por (linaje de pieza, proceso,
     * subproceso).
     *
     * Se suman dos fuentes que no se solapan:
     *  - lo **pagado**, leído del snapshot inmutable de las liquidaciones, que
     *    no se mueve aunque después se edite, renombre o borre la pieza;
     *  - lo **capturado en semanas todavía abiertas**, que sigue siendo
     *    editable y por eso se lee de los registros.
     *
     * Cuando la pieza ya no existe se cae al QR del snapshot, para no perder lo
     * pagado ni mezclarlo con otra pieza. Se usa el QR y no el QS porque el QS
     * se repite entre lotes y juntaría el avance de dos piezas distintas.
     */
    public function mapaDeObra(int $obraId): AvanceDeObra
    {
        return $this->mapas[$obraId] ??= $this->calcularMapaDeObra($obraId);
    }

    private function calcularMapaDeObra(int $obraId): AvanceDeObra
    {
        $raices = $this->raicesDeLinaje($obraId);

        $totales = [];

        $detalles = LiquidacionDetalle::query()
            ->where('obra_id', $obraId)
            ->get(['pieza_id', 'qr', 'proceso_id', 'subproceso_id', 'porcentaje']);

        foreach ($detalles as $detalle) {
            $clave = $this->clave($raices, $detalle->pieza_id, $detalle->qr, $detalle->proceso_id, $detalle->subproceso_id);
            $totales[$clave] = ($totales[$clave] ?? 0) + (float) $detalle->porcentaje / 100;
        }

        foreach ($this->registrosNoLiquidados($obraId) as $registro) {
            $clave = $this->clave($raices, $registro->pieza_id, $registro->pieza?->qr, $registro->proceso_id, $registro->subproceso_id);
            $totales[$clave] = ($totales[$clave] ?? 0) + $registro->piezasEquivalentes();
        }

        return new AvanceDeObra($raices, $totales);
    }

    /**
     * Número de avance y porcentaje acumulado de las piezas de la obra, vistos
     * desde un destajo: lo de semanas anteriores define el número, y el
     * acumulado suma además lo de la propia semana. Lo de semanas posteriores
     * no cuenta, para que reimprimir una semana vieja diga lo que dijo entonces.
     *
     * Mismas fuentes que el tope: el snapshot de las liquidaciones para las
     * semanas cerradas y los registros para las abiertas. Un registro suelto,
     * sin destajo que lo cubra, cuenta como su propia semana por fecha.
     */
    public function historialHasta(int $obraId, Destajo $destajo): HistorialDeAvance
    {
        $raices = $this->raicesDeLinaje($obraId);
        $inicio = $destajo->fecha_inicio->toDateString();
        $semanasPrevias = [];
        $acumulados = [];

        $anotar = function (string $clave, string $semana, bool $esPrevia, float $equivalentes) use (&$semanasPrevias, &$acumulados): void {
            if ($esPrevia) {
                $semanasPrevias[$clave][$semana] = true;
            }

            $acumulados[$clave] = ($acumulados[$clave] ?? 0) + $equivalentes;
        };

        $detalles = LiquidacionDetalle::query()
            ->with('liquidacion.destajo:id,fecha_inicio')
            ->where('obra_id', $obraId)
            ->whereHas('liquidacion.destajo', fn ($q) => $q->where('fecha_inicio', '<=', $destajo->fecha_inicio))
            ->get(['id', 'liquidacion_id', 'pieza_id', 'qr', 'proceso_id', 'subproceso_id', 'porcentaje']);

        foreach ($detalles as $detalle) {
            $destajoDelPago = $detalle->liquidacion->destajo;

            $anotar(
                $this->clave($raices, $detalle->pieza_id, $detalle->qr, $detalle->proceso_id, $detalle->subproceso_id),
                'destajo:'.$destajoDelPago->id,
                $destajoDelPago->id !== $destajo->id,
                (float) $detalle->porcentaje / 100,
            );
        }

        $destajos = Destajo::query()->get(['id', 'fecha_inicio', 'fecha_fin']);

        foreach ($this->registrosNoLiquidados($obraId, $destajo->fecha_fin) as $registro) {
            $fecha = $registro->fecha->toDateString();
            $semana = $destajos->first(fn (Destajo $d) => $d->fecha_inicio->toDateString() <= $fecha && $d->fecha_fin->toDateString() >= $fecha);

            $anotar(
                $this->clave($raices, $registro->pieza_id, $registro->pieza?->qr, $registro->proceso_id, $registro->subproceso_id),
                $semana !== null ? 'destajo:'.$semana->id : 'fecha:'.$fecha,
                $fecha < $inicio,
                $registro->piezasEquivalentes(),
            );
        }

        return new HistorialDeAvance($raices, $semanasPrevias, $acumulados);
    }

    /**
     * Mapa piezaId => id de la pieza raíz de su linaje, para toda la obra.
     *
     * @return array<int, int>
     */
    private function raicesDeLinaje(int $obraId): array
    {
        $origenes = Pieza::query()
            ->whereHas('catalogo', fn ($q) => $q->where('obra_id', $obraId))
            ->pluck('pieza_origen_id', 'id')
            ->all();

        $raices = [];

        foreach (array_keys($origenes) as $id) {
            $actual = $id;
            $vistos = [];

            // El `isset($vistos)` corta cualquier ciclo por datos corruptos.
            while (! empty($origenes[$actual]) && ! isset($vistos[$actual])) {
                $vistos[$actual] = true;
                $actual = $origenes[$actual];
            }

            $raices[$id] = $actual;
        }

        return $raices;
    }

    /**
     * @param  array<int, int>  $raices
     */
    private function clave(array $raices, ?int $piezaId, ?string $qr, ?int $procesoId, ?int $subprocesoId = null): string
    {
        $pieza = isset($raices[$piezaId])
            ? 'raiz:'.$raices[$piezaId]
            : 'qr:'.($qr ?? '');

        return $pieza.'|proceso:'.($procesoId ?? 0).'|sub:'.($subprocesoId ?? 0);
    }

    /**
     * Registros que aún no están respaldados por una liquidación: los de
     * semanas abiertas y los sueltos (capturados en un destajo que se borró).
     * Se cuentan por prudencia: mejor topar de más que pagar dos veces.
     *
     * @return Collection<int, Registro>
     */
    private function registrosNoLiquidados(int $obraId, ?CarbonInterface $hasta = null): Collection
    {
        return Registro::query()
            ->with('pieza:id,qr')
            ->whereHas('pieza.catalogo', fn ($q) => $q->where('obra_id', $obraId))
            ->when($hasta !== null, fn ($q) => $q->where('fecha', '<=', $hasta))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('prod_destajos')
                ->where('prod_destajos.cerrado', true)
                ->whereColumn('prod_destajos.fecha_inicio', '<=', 'prod_registros.fecha')
                ->whereColumn('prod_destajos.fecha_fin', '>=', 'prod_registros.fecha'))
            ->get(['id', 'fecha', 'pieza_id', 'proceso_id', 'subproceso_id', 'porcentaje']);
    }

    /**
     * Fracción de pieza que todavía se puede pagar de este QS en este proceso, o
     * en este subproceso si el grupo paga por pasos. Nunca negativo.
     */
    public function disponible(Pieza $pieza, int $procesoId, ?int $subprocesoId = null): float
    {
        return round(max(0, self::TOPE_POR_PIEZA - $this->capturado($pieza, $procesoId, $subprocesoId)), 4);
    }

    /** Fracción ya pagada o comprometida de esta pieza en este proceso. */
    public function capturado(Pieza $pieza, int $procesoId, ?int $subprocesoId = null): float
    {
        return $this->mapaDeObra($this->obraDe($pieza))->capturadoDe($pieza, $procesoId, $subprocesoId);
    }

    /** ¿Cabe pagar esta pieza a este porcentaje sin rebasar su tope? */
    public function cabe(Pieza $pieza, int $procesoId, float $porcentaje, ?int $subprocesoId = null): bool
    {
        return round($porcentaje / 100, 4) <= $this->disponible($pieza, $procesoId, $subprocesoId) + self::EPSILON;
    }

    /**
     * Explica el tope: una pieza vale 1 en cada proceso, así que lo que queda es
     * una fracción. Las piezas rehechas se pagan como pago extra.
     *
     * Vive aquí y no en quien captura para que la captura manual y la revisión
     * del CSV digan exactamente lo mismo.
     */
    public function mensajeDeTope(Pieza $pieza, Proceso $proceso, float $disponible, ?GrupoPrecioSubproceso $subproceso = null): string
    {
        $salida = ' Si es una pieza rehecha, regístrala como pago extra.';
        $etiqueta = $pieza->etiqueta();
        // El tope es del paso, no del proceso: decir "en Soldadura" cuando lo
        // que se agotó fue "Armado" manda a revisar la pieza equivocada.
        $donde = $subproceso !== null
            ? "{$proceso->nombre} / {$subproceso->nombre}"
            : $proceso->nombre;

        if ($disponible <= 0) {
            return "La pieza {$etiqueta} ya está pagada al 100% en {$donde}.".$salida;
        }

        $pendiente = rtrim(rtrim(number_format($disponible * 100, 2, '.', ''), '0'), '.');

        return "La pieza {$etiqueta} sólo tiene {$pendiente}% por pagar en {$donde}.".$salida;
    }

    /**
     * Decora una colección de piezas con su avance por proceso, sin consultar la
     * base una vez por pieza. Cada pieza recibe `avance`: procesoId => capturado.
     *
     * Si se pasan subprocesos, la pieza recibe además `avance_subprocesos`:
     * subprocesoId => capturado. Se piden aparte y no se derivan del proceso
     * porque cada paso lleva su propio tope, y sólo el grupo de precios de la
     * marca sabe cuáles de ellos aplican.
     *
     * @param  Collection<int, Pieza>  $piezas
     * @param  list<int>  $procesoIds
     * @param  iterable<GrupoPrecioSubproceso>  $subprocesos
     * @return Collection<int, Pieza>
     */
    public function decorar(Collection $piezas, array $procesoIds, iterable $subprocesos = []): Collection
    {
        $this->precargarObras($piezas->pluck('catalogo_id'));

        $porObra = $piezas
            ->map(fn (Pieza $pieza) => $this->obraDe($pieza))
            ->unique()
            ->mapWithKeys(fn (int $obraId) => [$obraId => $this->mapaDeObra($obraId)]);

        return $piezas->each(function (Pieza $pieza) use ($porObra, $procesoIds, $subprocesos): void {
            $mapa = $porObra->get($this->obraDe($pieza));

            $avance = [];
            foreach ($procesoIds as $procesoId) {
                $avance[$procesoId] = $this->celdaDeAvance($mapa?->capturadoDe($pieza, $procesoId) ?? 0.0);
            }

            $porSubproceso = [];
            foreach ($subprocesos as $subproceso) {
                $capturado = $mapa?->capturadoDe($pieza, (int) $subproceso->proceso_id, (int) $subproceso->id) ?? 0.0;
                $porSubproceso[$subproceso->id] = $this->celdaDeAvance($capturado);
            }

            $pieza->setAttribute('avance', $avance);
            $pieza->setAttribute('avance_subprocesos', $porSubproceso);
        });
    }

    /**
     * @return array{capturado: float, disponible: float}
     */
    private function celdaDeAvance(float $capturado): array
    {
        return [
            'capturado' => $capturado,
            'disponible' => round(max(0, self::TOPE_POR_PIEZA - $capturado), 4),
        ];
    }

    /**
     * La obra sale del catálogo de la pieza, que es quien la ancla. Se resuelve
     * contra la caché; si el catálogo no está, se carga en bloque junto con los
     * demás que falten.
     */
    private function obraDe(Pieza $pieza): int
    {
        $catalogoId = (int) $pieza->catalogo_id;

        if (! array_key_exists($catalogoId, $this->obraPorCatalogo)) {
            $this->precargarObras([$catalogoId]);
        }

        return $this->obraPorCatalogo[$catalogoId] ?? 0;
    }

    /**
     * Carga de una sola consulta la obra de los catálogos que aún no estén en
     * caché. Es lo que convierte el recorrido de N piezas en O(1) consultas.
     *
     * @param  iterable<int>  $catalogoIds
     */
    private function precargarObras(iterable $catalogoIds): void
    {
        $faltantes = collect($catalogoIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => array_key_exists($id, $this->obraPorCatalogo));

        if ($faltantes->isEmpty()) {
            return;
        }

        $encontrados = Catalogo::query()
            ->whereIn('id', $faltantes)
            ->pluck('obra_id', 'id')
            ->map(fn ($obraId) => (int) $obraId)
            ->all();

        // Los que no existan se cachean en 0 para no volver a preguntar por ellos.
        foreach ($faltantes as $id) {
            $this->obraPorCatalogo[$id] = $encontrados[$id] ?? 0;
        }
    }
}
