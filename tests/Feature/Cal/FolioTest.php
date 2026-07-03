<?php

use App\Models\Cal\PiezaPlano;
use App\Models\Cal\Reporte;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plano = PiezaPlano::factory()->create();
});

test('reporte no-plantilla autogenera folio si viene null', function () {
    $reporte = Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
    ]);

    expect($reporte->folio)->not->toBeNull();
    expect($reporte->folio)->toMatch('/^IV\d{4}\d{2,}$/');
});

test('plantilla NO recibe folio (queda null)', function () {
    $reporte = Reporte::factory()->plantilla()->create([
        'plano_id' => $this->plano->id,
    ]);

    expect($reporte->folio)->toBeNull();
});

test('folio respeta valor explicito si se pasa', function () {
    $reporte = Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV99990199',
    ]);

    expect($reporte->folio)->toBe('IV99990199');
});

test('folio incrementa correctamente para multiples reportes del mismo mes', function () {
    $r1 = Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-04-15 10:00:00']);
    $r2 = Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-04-15 11:00:00']);
    $r3 = Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-04-15 12:00:00']);

    expect($r1->folio)->toBe('IV260401');
    expect($r2->folio)->toBe('IV260402');
    expect($r3->folio)->toBe('IV260403');
});

test('folio se segmenta por mes', function () {
    Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-04-15 10:00:00']);
    $abril2 = Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-04-20 10:00:00']);
    $mayo = Reporte::factory()->create(['plano_id' => $this->plano->id, 'es_plantilla' => false, 'created_at' => '2026-05-01 10:00:00']);

    expect($abril2->folio)->toBe('IV260402');
    expect($mayo->folio)->toBe('IV260501');
});

test('siguienteFolio retoma desde el max existente del mes', function () {
    Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV260315',
        'created_at' => '2026-03-12 10:00:00',
    ]);

    $siguiente = Reporte::siguienteFolio(now()->parse('2026-03-15'));

    expect($siguiente)->toBe('IV260316');
});

test('siguienteFolio compara sufijo numericamente, no lexicograficamente', function () {
    // Caso real: el bug del orden lexicografico haria que 'IV260399' >
    // 'IV2603105' por comparacion de chars, asignando un folio que ya existe.
    Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV260399',
        'created_at' => '2026-03-15 10:00:00',
    ]);
    Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV2603105',
        'created_at' => '2026-03-15 12:00:00',
    ]);

    $siguiente = Reporte::siguienteFolio(now()->parse('2026-03-20'));

    expect($siguiente)->toBe('IV2603106');
});

test('copiar reporte regenera folio en lugar de duplicarlo', function () {
    $original = Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'created_at' => '2026-04-15 10:00:00',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/reportes/{$original->id}/copiar", [
            'consecutivo' => '2',
        ]);

    $response->assertCreated();
    $copia = Reporte::find($response->json('id'));

    expect($copia->folio)->not->toBe($original->folio);
    expect($copia->folio)->toMatch('/^IV\d{4}\d{2,}$/');
});

test('constraint unique impide folios duplicados', function () {
    Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV20260499',
    ]);

    expect(fn () => Reporte::factory()->create([
        'plano_id' => $this->plano->id,
        'es_plantilla' => false,
        'folio' => 'IV20260499',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

test('constraint unique permite multiples plantillas con folio null', function () {
    $p1 = Reporte::factory()->plantilla()->create(['plano_id' => $this->plano->id]);
    $p2 = Reporte::factory()->plantilla()->create(['plano_id' => $this->plano->id]);
    $p3 = Reporte::factory()->plantilla()->create(['plano_id' => $this->plano->id]);

    expect($p1->folio)->toBeNull();
    expect($p2->folio)->toBeNull();
    expect($p3->folio)->toBeNull();
});
