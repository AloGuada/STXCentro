<?php

use App\Models\Costos\TipoCambio;
use App\Services\Costos\TipoCambioService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Este archivo prueba la integración real; deshace el doble falso global
    // de Pest.php para resolver el servicio concreto.
    app()->bind(TipoCambioService::class, fn () => new TipoCambioService);
});

function ecbXml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
 <gesmes:subject>Reference rates</gesmes:subject>
 <gesmes:Sender><gesmes:name>European Central Bank</gesmes:name></gesmes:Sender>
 <Cube>
  <Cube time="2026-07-22">
   <Cube currency="USD" rate="1.0800"/>
   <Cube currency="MXN" rate="20.5000"/>
  </Cube>
 </Cube>
</gesmes:Envelope>
XML;
}

/**
 * @return array<string, mixed>
 */
function banxicoJson(string $dato = '18.7500'): array
{
    return ['bmx' => ['series' => [['idSerie' => 'SF43718', 'datos' => [['fecha' => '22/07/2026', 'dato' => $dato]]]]]];
}

it('mxn siempre vale 1 sin pegar a ninguna fuente', function () {
    Http::fake();

    expect(app(TipoCambioService::class)->mxnPorUnidad('mxn'))->toBe(1.0);

    Http::assertNothingSent();
});

it('toma el USD del FIX de Banxico cuando hay token', function () {
    config()->set('services.banxico.token', 'token-test');
    Http::fake([
        'banxico.org.mx/*' => Http::response(banxicoJson('18.7500')),
        'ecb.europa.eu/*' => Http::response(ecbXml()),
    ]);

    $tasa = app(TipoCambioService::class)->mxnPorUnidad('usd');

    expect($tasa)->toBe(18.75);
    expect(TipoCambio::where('moneda', 'usd')->first())
        ->tasa->toBe('18.750000')
        ->fuente->toBe('banxico');
});

it('cae al cross-rate del ECB para USD cuando no hay token de Banxico', function () {
    config()->set('services.banxico.token', null);
    Http::fake(['ecb.europa.eu/*' => Http::response(ecbXml())]);

    $tasa = app(TipoCambioService::class)->mxnPorUnidad('usd');

    // 20.50 MXN/EUR ÷ 1.08 USD/EUR = 18.981481 MXN/USD
    expect($tasa)->toBe(18.981481);
    expect(TipoCambio::where('moneda', 'usd')->first()->fuente)->toBe('ecb');
});

it('toma el EUR directo del XML del ECB', function () {
    Http::fake(['ecb.europa.eu/*' => Http::response(ecbXml())]);

    $tasa = app(TipoCambioService::class)->mxnPorUnidad('eur');

    expect($tasa)->toBe(20.5);
    expect(TipoCambio::where('moneda', 'eur')->first()->fuente)->toBe('ecb');
});

it('reutiliza la tasa cacheada del día sin volver a consultar la fuente', function () {
    Http::fake(['ecb.europa.eu/*' => Http::response(ecbXml())]);
    $service = app(TipoCambioService::class);

    $service->mxnPorUnidad('eur');
    $service->mxnPorUnidad('eur');

    Http::assertSentCount(1);
});

it('usa la última tasa cacheada como respaldo si la fuente falla', function () {
    TipoCambio::factory()->eur()->create([
        'fecha' => now()->subDay()->toDateString(),
        'tasa' => 21.123456,
    ]);
    Http::fake(['ecb.europa.eu/*' => Http::response('', 503)]);

    $tasa = app(TipoCambioService::class)->mxnPorUnidad('eur');

    expect($tasa)->toBe(21.123456);
});
