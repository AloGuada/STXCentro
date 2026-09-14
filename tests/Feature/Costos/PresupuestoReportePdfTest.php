<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Obra;
use App\Models\User;
use App\Services\Costos\HojaReportePresupuestos;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'costos.obra-rubros.ver', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('costos.obra-rubros.ver');
});

/**
 * Catálogo de `$rubros` rubros de obra y una obra con montos grandes en cada
 * uno, para que las cifras ocupen su ancho real.
 */
function catalogoConRubros(int $rubros): void
{
    $tipo = TipoRubro::factory()->create(['descripcion' => 'Materiales']);
    $presupuesto = Presupuesto::create([
        'presupuestable_type' => Obra::class,
        'presupuestable_id' => Obra::factory()->create(['es_planta' => false])->id,
    ]);

    Rubro::factory()->count($rubros)->create(['tipo_rubro_id' => $tipo->id])
        ->each(fn (Rubro $rubro) => ObraRubro::factory()->create([
            'presupuesto_id' => $presupuesto->id,
            'rubro_id' => $rubro->id,
            'presupuestado' => 12_345_678.90,
            'acumulado' => 9_876_543.21,
        ]));
}

/**
 * Descarga el reporte y devuelve el wrapper de dompdf ya renderizado, junto con
 * el borde derecho (en pt) de la celda que llegó más lejos al dibujarse.
 *
 * @return array{0: PdfWrapper, 1: float}
 */
function descargarReporte(object $test): array
{
    $wrapper = null;
    $bordeDerecho = 0.0;

    app()->afterResolving('dompdf.wrapper', function (PdfWrapper $pdf) use (&$wrapper, &$bordeDerecho) {
        $wrapper = $pdf;
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame) use (&$bordeDerecho) {
                if (in_array($frame->get_node()->nodeName, ['td', 'th'], true)) {
                    $caja = $frame->get_border_box();
                    $bordeDerecho = max($bordeDerecho, (float) $caja['x'] + (float) $caja['w']);
                }
            },
        ]]);
    });

    $test->actingAs($test->user)
        ->get(route('admin.costos.presupuestos.reporte-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    return [$wrapper, $bordeDerecho];
}

test('el reporte de presupuestos descarga un PDF', function () {
    ObraRubro::factory()->count(3)->create();

    descargarReporte($this);
});

test('el reporte de presupuestos sube el limite de memoria de la peticion', function () {
    ini_set('memory_limit', '256M');

    descargarReporte($this);

    expect(ini_get('memory_limit'))->toBe('1024M');
});

test('con pocos rubros el reporte sale en tabloide', function () {
    catalogoConRubros(5);

    [$pdf] = descargarReporte($this);

    expect($pdf->getDomPDF()->getPaperSize())->toBe([0.0, 0.0, 1224.0, 792.0]);
});

test('con muchos rubros la hoja crece y todas las columnas caben', function (int $rubros, float $anchoEsperado) {
    catalogoConRubros($rubros);

    [$pdf, $bordeDerecho] = descargarReporte($this);
    [, , $anchoHoja] = $pdf->getDomPDF()->getPaperSize();

    expect($anchoHoja)->toBe($anchoEsperado)
        ->and($bordeDerecho)->toBeLessThanOrEqual($anchoHoja - HojaReportePresupuestos::MARGEN_PAGINA_PT);
})->with([
    '25 rubros en A1' => [25, 2383.94],
    '60 rubros en 2A0' => [60, 4767.87],
    '120 rubros a la medida' => [120, 8011.0],
]);
