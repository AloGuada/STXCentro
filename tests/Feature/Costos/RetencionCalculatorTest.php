<?php

use App\Models\Proveedor;
use App\Models\RegimenFiscal;
use App\Services\Costos\RetencionCalculator;

beforeEach(function () {
    $this->calc = app(RetencionCalculator::class);
    $this->resico = RegimenFiscal::factory()->create(['clave' => '626']);
    $this->otro = RegimenFiscal::factory()->create(['clave' => '612']);
});

function montoDe(array $desglose, string $clave): float
{
    foreach ($desglose['retenciones'] as $r) {
        if ($r['clave'] === $clave) {
            return $r['monto'];
        }
    }

    return 0.0;
}

test('PF RESICO aplica 1.25% a cada línea y sustituye ISR honorarios', function () {
    $prov = Proveedor::factory()->create(['tipo_persona' => 'fisica', 'regimen_fiscal_id' => $this->resico->id]);

    $desglose = $this->calc->calcular($prov, [
        ['tipo_fiscal' => 'mercancia', 'subtotal' => 1000],
        ['tipo_fiscal' => 'servicio_profesional', 'subtotal' => 1000],
    ]);

    expect(montoDe($desglose, 'isr_resico'))->toBe(25.0); // 1.25% de 2000
    expect(montoDe($desglose, 'isr_honorarios'))->toBe(0.0); // RESICO lo sustituye
    expect(montoDe($desglose, 'iva_honorarios'))->toBe(106.7); // 10.67% de 1000
});

test('PF no RESICO en servicio profesional retiene ISR 10% e IVA 10.67%', function () {
    $prov = Proveedor::factory()->create(['tipo_persona' => 'fisica', 'regimen_fiscal_id' => $this->otro->id]);

    $desglose = $this->calc->calcular($prov, [
        ['tipo_fiscal' => 'servicio_profesional', 'subtotal' => 1000],
    ]);

    expect(montoDe($desglose, 'isr_honorarios'))->toBe(100.0);
    expect(montoDe($desglose, 'iva_honorarios'))->toBe(106.7);
});

test('ISR fletes 4% aplica a PF y a PM', function () {
    $pf = Proveedor::factory()->create(['tipo_persona' => 'fisica', 'regimen_fiscal_id' => $this->otro->id]);
    $pm = Proveedor::factory()->create(['tipo_persona' => 'moral', 'regimen_fiscal_id' => $this->otro->id]);

    $linea = [['tipo_fiscal' => 'flete', 'subtotal' => 1000]];

    expect(montoDe($this->calc->calcular($pf, $linea), 'isr_fletes'))->toBe(40.0);
    expect(montoDe($this->calc->calcular($pm, $linea), 'isr_fletes'))->toBe(40.0);
});

test('IVA renta solo aplica a PF, no a PM', function () {
    $pf = Proveedor::factory()->create(['tipo_persona' => 'fisica', 'regimen_fiscal_id' => $this->otro->id]);
    $pm = Proveedor::factory()->create(['tipo_persona' => 'moral', 'regimen_fiscal_id' => $this->otro->id]);

    $linea = [['tipo_fiscal' => 'renta', 'subtotal' => 1000]];

    expect(montoDe($this->calc->calcular($pf, $linea), 'iva_renta'))->toBe(106.7);
    expect($this->calc->calcular($pm, $linea)['retenciones'])->toBe([]);
});

test('PM mercancía sin retenciones: total neto = subtotal + IVA', function () {
    $pm = Proveedor::factory()->create(['tipo_persona' => 'moral', 'regimen_fiscal_id' => $this->otro->id]);

    $desglose = $this->calc->calcular($pm, [
        ['tipo_fiscal' => 'mercancia', 'subtotal' => 1000],
    ]);

    expect($desglose['retenciones'])->toBe([]);
    expect($desglose['subtotal'])->toBe(1000.0);
    expect($desglose['iva'])->toBe(160.0);
    expect($desglose['total_neto'])->toBe(1160.0);
});

test('total neto = subtotal + IVA menos suma de retenciones', function () {
    $pf = Proveedor::factory()->create(['tipo_persona' => 'fisica', 'regimen_fiscal_id' => $this->otro->id]);

    $desglose = $this->calc->calcular($pf, [
        ['tipo_fiscal' => 'servicio_profesional', 'subtotal' => 1000],
    ]);

    // subtotal 1000 + IVA 160 - (ISR 100 + IVA hon 106.7) = 953.3
    expect($desglose['total_neto'])->toBe(953.3);
});
