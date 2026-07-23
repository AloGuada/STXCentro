<?php

namespace App\Services\Costos;

use App\Models\Costos\TipoCambio;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Resuelve el tipo de cambio de referencia (MXN por 1 unidad de la divisa) y
 * lo cachea por día en `costos_tipos_cambio`.
 *
 * Fuentes: USD desde el FIX oficial de Banxico (serie SF43718); EUR desde el
 * XML diario del ECB (MXN por EUR directo). Si Banxico no responde o falta el
 * token, USD cae al cross-rate del ECB (MXN_por_EUR / USD_por_EUR). Si ninguna
 * fuente responde, usa la última tasa cacheada de esa divisa (stale) antes de
 * fallar.
 */
class TipoCambioService
{
    private const TIMEOUT = 8;

    /**
     * MXN por 1 unidad de `$moneda` en `$fecha` (hoy por defecto). `mxn` → 1.
     */
    public function mxnPorUnidad(string $moneda, ?CarbonInterface $fecha = null): float
    {
        $moneda = strtolower($moneda);
        $fecha = ($fecha ? Carbon::parse($fecha) : Carbon::today())->startOfDay();

        if ($moneda === 'mxn') {
            return 1.0;
        }

        $cache = TipoCambio::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->where('moneda', $moneda)
            ->first();

        if ($cache !== null) {
            return (float) $cache->tasa;
        }

        [$tasa, $fuente] = $this->obtener($moneda, $fecha);

        TipoCambio::updateOrCreate(
            ['fecha' => $fecha->toDateString(), 'moneda' => $moneda],
            ['fuente' => $fuente, 'tasa' => $tasa],
        );

        return $tasa;
    }

    /**
     * @return array{0: float, 1: string} [tasa, fuente]
     */
    private function obtener(string $moneda, Carbon $fecha): array
    {
        try {
            return match ($moneda) {
                'usd' => $this->obtenerUsd($fecha),
                'eur' => [$this->obtenerEcb('MXN', $fecha), 'ecb'],
                default => throw new RuntimeException("Moneda no soportada: {$moneda}"),
            };
        } catch (RuntimeException $e) {
            $ultima = TipoCambio::query()
                ->where('moneda', $moneda)
                ->orderByDesc('fecha')
                ->first();

            if ($ultima !== null) {
                return [(float) $ultima->tasa, $ultima->fuente];
            }

            throw $e;
        }
    }

    /**
     * USD por Banxico FIX; respaldo cross-rate del ECB.
     *
     * @return array{0: float, 1: string}
     */
    private function obtenerUsd(Carbon $fecha): array
    {
        $token = config('services.banxico.token');

        if ($token) {
            try {
                return [$this->obtenerBanxicoUsd($fecha, $token), 'banxico'];
            } catch (RuntimeException) {
                // Cae al respaldo del ECB.
            }
        }

        $mxnPorEur = $this->obtenerEcb('MXN', $fecha);
        $usdPorEur = $this->obtenerEcb('USD', $fecha);

        if ($usdPorEur <= 0.0) {
            throw new RuntimeException('Cross-rate USD/MXN inválido desde el ECB.');
        }

        return [round($mxnPorEur / $usdPorEur, 6), 'ecb'];
    }

    private function obtenerBanxicoUsd(Carbon $fecha, string $token): float
    {
        $base = rtrim((string) config('costos.tipo_cambio.banxico.url'), '/');
        $serie = config('costos.tipo_cambio.banxico.serie_usd');
        $endpoint = $fecha->isToday()
            ? "{$base}/series/{$serie}/datos/oportuno"
            : "{$base}/series/{$serie}/datos/{$fecha->toDateString()}/{$fecha->toDateString()}";

        $respuesta = Http::timeout(self::TIMEOUT)
            ->withHeaders(['Bmx-Token' => $token])
            ->acceptJson()
            ->get($endpoint);

        if (! $respuesta->successful()) {
            throw new RuntimeException("Banxico respondió {$respuesta->status()}.");
        }

        $dato = data_get($respuesta->json(), 'bmx.series.0.datos.0.dato');

        if (! is_numeric($dato)) {
            throw new RuntimeException('Banxico no devolvió un dato numérico.');
        }

        return round((float) $dato, 6);
    }

    /**
     * MXN (u otra divisa) por 1 EUR desde el XML diario del ECB.
     */
    private function obtenerEcb(string $divisa, Carbon $fecha): float
    {
        $respuesta = Http::timeout(self::TIMEOUT)->get((string) config('costos.tipo_cambio.ecb.url'));

        if (! $respuesta->successful()) {
            throw new RuntimeException("ECB respondió {$respuesta->status()}.");
        }

        $tasa = $this->tasaDesdeXmlEcb($respuesta->body(), $divisa);

        if ($tasa === null || $tasa <= 0.0) {
            throw new RuntimeException("El ECB no publicó la divisa {$divisa}.");
        }

        return round($tasa, 6);
    }

    private function tasaDesdeXmlEcb(string $xmlString, string $divisa): ?float
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlString);
            if ($xml === false) {
                throw new RuntimeException('XML del ECB inválido.');
            }

            foreach ($this->cubosEcb($xml) as $cubo) {
                if ((string) ($cubo['currency'] ?? '') === strtoupper($divisa)) {
                    return (float) $cubo['rate'];
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * Aplana los <Cube currency=.. rate=..> del XML del ECB, ignorando el
     * namespace por defecto que trae el documento.
     *
     * @return iterable<SimpleXMLElement>
     */
    private function cubosEcb(SimpleXMLElement $xml): iterable
    {
        $xml->registerXPathNamespace('ecb', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
        $nodos = $xml->xpath('//ecb:Cube[@currency]');

        return $nodos === false ? [] : $nodos;
    }
}
