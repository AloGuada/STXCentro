<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // Tasas de cambio deterministas: las pruebas de presupuesto en divisa
        // no deben pegar a Banxico/ECB por red. TipoCambioServiceTest re-liga el
        // servicio real en su propio beforeEach para probar la integración.
        $this->app->bind(\App\Services\Costos\TipoCambioService::class, fn () => new class extends \App\Services\Costos\TipoCambioService
        {
            public function mxnPorUnidad(string $moneda, ?\Carbon\CarbonInterface $fecha = null): float
            {
                return match (strtolower($moneda)) {
                    'usd' => 18.5,
                    'eur' => 20.0,
                    default => 1.0,
                };
            }
        });
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Otorga al usuario los permisos CRUD de solicitudes de pago (costos).
 * Útil en tests que actúan sobre rutas admin.costos.solicitudes-pago.*
 */
function darPermisosSolicitudesPago(\App\Models\User $user): \App\Models\User
{
    $permisos = [
        'costos.solicitudes-pago.ver',
        'costos.solicitudes-pago.crear',
        'costos.solicitudes-pago.editar',
        'costos.solicitudes-pago.eliminar',
    ];

    foreach ($permisos as $name) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permisos);

    return $user;
}

/**
 * Una marca del catálogo con sus piezas físicas (QS), que es como llega el
 * layout: un renglón por pieza repitiendo el modelo.
 *
 * @param  array<string, mixed>  $atributos  de la marca
 */
function marcaConPiezas(int $piezas = 1, array $atributos = []): \App\Models\Concepto
{
    $marca = \App\Models\Concepto::factory()->create([...$atributos, 'cantidad' => $atributos['cantidad'] ?? $piezas]);

    \App\Models\Prod\Pieza::factory()->count($piezas)->create([
        'concepto_id' => $marca->id,
        'catalogo_id' => $marca->catalogo_id,
    ]);

    return $marca->load('piezas');
}

/**
 * El proceso sembrado por la migración. Los tests casi siempre quieren
 * soldadura; pintura sirve para probar que los topes no se mezclan.
 */
function proceso(string $nombre = 'Soldadura'): \App\Models\Prod\Proceso
{
    return \App\Models\Prod\Proceso::where('nombre', $nombre)->firstOrFail();
}

/**
 * Marca la obra como pagadora de esos procesos. Sin esto la captura los rechaza,
 * que es justo lo que se quiere en producción pero estorba al montar un test.
 */
function obraPagaProcesos(int $obraId, \App\Models\Prod\Proceso ...$procesos): void
{
    $procesos = $procesos ?: [proceso()];

    \App\Models\Obra::findOrFail($obraId)->procesos()->syncWithoutDetaching(
        collect($procesos)->pluck('id')->all()
    );
}

/**
 * Grupo de precios de la obra con tarifa para un proceso, ya asignado a la
 * marca. Es el mínimo que necesita una liquidación para no pagar en cero.
 */
function tarifaDeMarca(
    \App\Models\Concepto $marca,
    float $precioKilo,
    ?\App\Models\Prod\Proceso $proceso = null,
    ?\App\Models\Prod\GrupoPrecio $grupoPrecio = null,
): \App\Models\Prod\GrupoPrecio {
    $grupoPrecio ??= \App\Models\Prod\GrupoPrecio::factory()->create(['obra_id' => $marca->obra_id]);

    \App\Models\Prod\GrupoPrecioProceso::updateOrCreate(
        ['grupo_precio_id' => $grupoPrecio->id, 'proceso_id' => ($proceso ?? proceso())->id],
        ['precio_kilo' => $precioKilo],
    );

    \App\Models\Prod\GrupoPrecioConcepto::firstOrCreate([
        'grupo_precio_id' => $grupoPrecio->id,
        'concepto_id' => $marca->id,
    ]);

    return $grupoPrecio->load('precios');
}

/**
 * Captura producción de varias piezas de golpe: un renglón por QS, que es como
 * queda el destajo desde que se paga pieza por pieza.
 *
 * @param  iterable<\App\Models\Prod\Pieza>  $piezas
 */
function capturarPiezas(
    iterable $piezas,
    \App\Models\Prod\GrupoTrabajo $grupo,
    string $fecha,
    float $porcentaje = 100,
    ?\App\Models\Prod\Proceso $proceso = null,
    ?\App\Models\Prod\GrupoPrecioSubproceso $subproceso = null,
): void {
    $proceso ??= proceso();

    foreach ($piezas as $pieza) {
        \App\Models\Prod\Registro::create([
            'fecha' => $fecha,
            'pieza_id' => $pieza->id,
            'proceso_id' => $proceso->id,
            'subproceso_id' => $subproceso?->id,
            'grupo_trabajo_id' => $grupo->id,
            'porcentaje' => $porcentaje,
        ]);
    }
}

/**
 * Grupo de precios que paga por subproceso, con sus pasos ya capturados y la
 * marca asignada. El espejo de `tarifaDeMarca` para la otra modalidad.
 *
 * @param  array<string, float>  $pasos  nombre del paso => precio fijo por pieza
 */
function grupoPorSubprocesos(
    \App\Models\Concepto $marca,
    array $pasos,
    ?\App\Models\Prod\Proceso $proceso = null,
    ?\App\Models\Prod\GrupoPrecio $grupoPrecio = null,
): \App\Models\Prod\GrupoPrecio {
    $proceso ??= proceso();
    $grupoPrecio ??= \App\Models\Prod\GrupoPrecio::factory()->create([
        'obra_id' => $marca->obra_id,
        'tipo_pago' => \App\Enums\Prod\TipoPago::Subproceso,
    ]);

    $orden = 0;

    foreach ($pasos as $nombre => $precio) {
        \App\Models\Prod\GrupoPrecioSubproceso::updateOrCreate(
            ['grupo_precio_id' => $grupoPrecio->id, 'proceso_id' => $proceso->id, 'nombre' => $nombre],
            ['precio' => $precio, 'orden' => $orden++, 'activo' => true],
        );
    }

    \App\Models\Prod\GrupoPrecioConcepto::firstOrCreate([
        'grupo_precio_id' => $grupoPrecio->id,
        'concepto_id' => $marca->id,
    ]);

    return $grupoPrecio->load('subprocesos');
}

/** El paso de un grupo de precios, por su nombre. */
function subproceso(
    \App\Models\Prod\GrupoPrecio $grupoPrecio,
    string $nombre,
): \App\Models\Prod\GrupoPrecioSubproceso {
    return $grupoPrecio->subprocesos()->where('nombre', $nombre)->firstOrFail();
}

function darPermisoVerTodasSolicitudes(\App\Models\User $user): \App\Models\User
{
    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'costos.solicitudes-pago.ver-todas',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('costos.solicitudes-pago.ver-todas');

    return $user;
}

/**
 * El almacén al que recibe un usuario en las pruebas de recepción. La captura
 * vive en Almacén desde que se unificó la entrada, así que quien recibe
 * necesita el permiso y un almacén visible; se memoriza uno por usuario para no
 * sembrar un almacén por cada POST.
 */
function almacenParaRecibir(\App\Models\User $usuario): \App\Models\Alm\Almacen
{
    static $almacenes = [];

    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'alm.entradas.crear',
        'guard_name' => 'web',
    ]);

    if (! $usuario->hasPermissionTo('alm.entradas.crear')) {
        $usuario->givePermissionTo('alm.entradas.crear');
    }

    $clave = (string) $usuario->getKey();

    if (! isset($almacenes[$clave]) || ! \App\Models\Alm\Almacen::whereKey($almacenes[$clave])->exists()) {
        $almacenes[$clave] = \App\Models\Alm\Almacen::factory()
            ->create(['responsable_id' => $usuario->getKey()])
            ->getKey();
    }

    return \App\Models\Alm\Almacen::findOrFail($almacenes[$clave]);
}

/*
|--------------------------------------------------------------------------
| CFDI
|--------------------------------------------------------------------------
|
| Los CFDI de mentiras con los que se prueban el portal del proveedor y la
| recepcion de almacen. Viven aqui y no dentro de un archivo de pruebas porque
| las funciones globales de Pest solo existen si su archivo se cargo, y correr
| una sola carpeta dejaba estas sin definir.
|
*/

function cfdiXml(array $overrides = []): string
{
    $attrs = array_merge([
        'Fecha' => '2026-05-20T10:00:00',
        'Folio' => 'F1',
        'SubTotal' => '10000.00',
        'Total' => '11600.00',
        'Moneda' => 'MXN',
        'Uuid' => '11111111-1111-1111-1111-111111111111',
        'RfcEmisor' => 'EME000101AAA',
        'RfcReceptor' => 'REC000101BBB',
        'IvaTrasladado' => '1600.00',
        'IvaRetenido' => '0.00',
        'IsrRetenido' => '0.00',
    ], $overrides);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante
    xmlns:cfdi="http://www.sat.gob.mx/cfd/4"
    xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
    Version="4.0"
    Fecha="{$attrs['Fecha']}"
    Folio="{$attrs['Folio']}"
    SubTotal="{$attrs['SubTotal']}"
    Total="{$attrs['Total']}"
    Moneda="{$attrs['Moneda']}">
  <cfdi:Emisor Rfc="{$attrs['RfcEmisor']}" Nombre="Emisor SA" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="{$attrs['RfcReceptor']}" Nombre="Receptor SA" UsoCFDI="G03"/>
  <cfdi:Impuestos TotalImpuestosTrasladados="{$attrs['IvaTrasladado']}" TotalImpuestosRetenidos="{$attrs['IvaRetenido']}">
    <cfdi:Retenciones>
      <cfdi:Retencion Impuesto="002" Importe="{$attrs['IvaRetenido']}"/>
      <cfdi:Retencion Impuesto="001" Importe="{$attrs['IsrRetenido']}"/>
    </cfdi:Retenciones>
    <cfdi:Traslados>
      <cfdi:Traslado Base="{$attrs['SubTotal']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$attrs['IvaTrasladado']}"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital UUID="{$attrs['Uuid']}" FechaTimbrado="{$attrs['Fecha']}"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;
}

function uploadXml(array $overrides = []): \Illuminate\Http\UploadedFile
{
    return \Illuminate\Http\UploadedFile::fake()->createWithContent('factura.xml', cfdiXml($overrides));
}

/**
 * Las lineas fiscales de una captura de recepcion, como las arma
 * EntradaController::lineasRecibidas(): precio capturado si lo hay, el de la
 * partida si no.
 *
 * @param  list<array<string, mixed>>  $detalles
 * @return list<array{tipo_fiscal: string, subtotal: float, sin_impuestos: bool}>
 */
function lineasDeRecepcion(array $detalles): array
{
    $lineas = [];

    foreach ($detalles as $renglon) {
        $partida = \App\Models\Costos\OrdenCompraDetalle::find($renglon['orden_compra_detalle_id']);

        if ($partida === null) {
            continue;
        }

        $precio = isset($renglon['precio_unitario']) && $renglon['precio_unitario'] !== ''
            ? (float) $renglon['precio_unitario']
            : (float) $partida->precio_unitario;

        $lineas[] = [
            'tipo_fiscal' => $partida->tipo_fiscal instanceof BackedEnum
                ? (string) $partida->tipo_fiscal->value
                : (string) ($partida->tipo_fiscal ?? \App\Enums\Costos\TipoFiscalPartida::Mercancia->value),
            'subtotal' => round((float) $renglon['cantidad_recibida'] * $precio, 2),
            'sin_impuestos' => (bool) $partida->sin_impuestos,
        ];
    }

    return $lineas;
}

/**
 * El CFDI y su PDF con los que se recibe contra una orden, cuadrados de origen
 * contra lo que se esta recibiendo: los importes salen del mismo
 * RetencionCalculator que usa FacturaDeLaRecepcion, asi que la comparacion pasa
 * salvo que la prueba la rompa a proposito con $overrides.
 *
 * Fijar el UUID hace que dos recepciones parciales caigan en la misma factura.
 *
 * @param  list<array<string, mixed>>  $detalles  los mismos que van en el payload
 * @param  array<string, string>  $overrides  atributos del XML, para romperlo a proposito
 * @return array{xml: \Illuminate\Http\UploadedFile, pdf: \Illuminate\Http\UploadedFile}
 */
function cfdiParaRecibir(
    \App\Models\Costos\OrdenCompra $oc,
    array $detalles,
    ?string $uuid = null,
    array $overrides = [],
): array {
    static $consecutivo = 0;

    $importes = importesDeRecepcion($oc, $detalles);

    $uuid ??= sprintf('FEEDFACE-0000-0000-0000-%012d', ++$consecutivo);

    return [
        'xml' => uploadXml(array_merge([
            'Uuid' => $uuid,
            'SubTotal' => number_format($importes['subtotal'], 2, '.', ''),
            'Total' => number_format($importes['total'], 2, '.', ''),
            'IvaTrasladado' => number_format($importes['iva'], 2, '.', ''),
            'IvaRetenido' => number_format($importes['iva_retenido'], 2, '.', ''),
            'IsrRetenido' => number_format($importes['isr_retenido'], 2, '.', ''),
        ], $overrides)),
        'pdf' => \Illuminate\Http\UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'),
    ];
}

/**
 * Lo que deberia totalizar la factura de una captura de recepcion.
 *
 * @param  list<array<string, mixed>>  $detalles
 * @return array{subtotal: float, iva: float, iva_retenido: float, isr_retenido: float, total: float}
 */
function importesDeRecepcion(\App\Models\Costos\OrdenCompra $oc, array $detalles): array
{
    $lineas = lineasDeRecepcion($detalles);
    $calculo = app(\App\Services\Costos\RetencionCalculator::class)->calcular($oc->proveedor, $lineas);

    $retenidoPorPrefijo = fn (string $prefijo): float => round((float) collect($calculo['retenciones'])
        ->filter(fn (array $r): bool => str_starts_with($r['clave'], $prefijo))
        ->sum('monto'), 2);

    return [
        'subtotal' => (float) $calculo['subtotal'],
        'iva' => (float) $calculo['iva'],
        'iva_retenido' => $retenidoPorPrefijo('iva_'),
        'isr_retenido' => $retenidoPorPrefijo('isr_'),
        'total' => (float) $calculo['total_neto'],
    ];
}

/**
 * El payload de POST /admin/almacen/entradas, completado con el CFDI que ahora
 * exige la recepcion contra orden. Si la prueba ya dijo con que factura entra
 * —o trae su propio XML— se respeta.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function capturaDeRecepcion(array $payload): array
{
    if (empty($payload['orden_compra_id'])
        || array_key_exists('factura_id', $payload)
        || array_key_exists('xml', $payload)) {
        return $payload;
    }

    $oc = \App\Models\Costos\OrdenCompra::find($payload['orden_compra_id']);

    return $oc === null ? $payload : $payload + cfdiParaRecibir($oc, $payload['detalles'] ?? []);
}

/**
 * Deja a la factura amparando exactamente lo que esa captura va a recibir, que
 * es lo que pide FacturaDeLaRecepcion para dejarla ligar.
 *
 * @param  list<array<string, mixed>>  $detalles
 */
function facturaQueAmpara(
    \App\Models\Costos\Factura $factura,
    \App\Models\Costos\OrdenCompra $oc,
    array $detalles,
): \App\Models\Costos\Factura {
    $importes = importesDeRecepcion($oc, $detalles);

    $factura->update([
        'subtotal' => $importes['subtotal'],
        'iva' => $importes['iva'],
        'iva_trasladado' => $importes['iva'],
        'iva_retenido' => $importes['iva_retenido'],
        'isr_retenido' => $importes['isr_retenido'],
        'total' => $importes['total'],
    ]);

    return $factura;
}

/**
 * El artículo con el que Almacén guarda un producto, creándolo si es la primera
 * vez. Es lo mismo que hace la pantalla al capturar: los formularios mandan
 * `articulo_id`, no `producto_id`, desde que el catálogo de Almacén vive en su
 * propia tabla.
 */
function articuloDe(\App\Models\Costos\Producto $producto): int
{
    return (int) app(\App\Services\Alm\ResolvedorArticulo::class)->paraProducto($producto->id);
}
