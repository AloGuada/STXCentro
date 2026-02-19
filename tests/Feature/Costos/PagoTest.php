<?php

use App\Models\Costos\Pago;
use App\Models\Costos\SolicitudPago;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('admin costos pagos', function () {
    test('index renders', function () {
        Pago::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.pagos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/pagos/index')
            ->has('pagos.data', 3)
        );
    });

    test('index does not show child pagos', function () {
        $padre = Pago::factory()->credito()->create(['estatus' => 'parcial']);
        Pago::factory()->hijo($padre, 1)->create(['monto_pago' => 5000]);
        Pago::factory()->hijo($padre, 2)->create(['monto_pago' => 5000]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.pagos.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('pagos.data', 1)
        );
    });

    test('show renders', function () {
        $pago = Pago::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.costos.pagos.show', $pago));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/costos/pagos/show')
            ->has('pago')
        );
    });

    test('folio is auto-generated with PG prefix', function () {
        $pago = Pago::factory()->create();

        expect($pago->folio)->toStartWith('PG-');
    });

    test('contado upload comprobante marks pago and solicitud as pagado', function () {
        Storage::fake('public');

        $solicitud = SolicitudPago::factory()->aprobada()->create();
        $pago = Pago::factory()->contado()->programado()->create([
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => $solicitud->id,
            'monto_pago' => $solicitud->monto_total,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.upload-comprobante', $pago), [
                'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 100),
            ]);

        $response->assertRedirect();

        $pago->refresh();
        expect($pago->estatus)->toBe('pagado');
        expect($pago->ruta_comprobante)->not->toBeNull();
        expect($pago->fecha_pago_realizada)->not->toBeNull();

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('pagada');
    });

    test('credito parcializar validates suma equals monto', function () {
        $pago = Pago::factory()->credito()->programado()->create(['monto_pago' => 10000]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $pago), [
                'parcialidades' => [
                    ['monto' => 3000, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 3000, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $response->assertSessionHasErrors(['parcialidades']);
    });

    test('credito parcializar creates child pagos', function () {
        $pago = Pago::factory()->credito()->programado()->create(['monto_pago' => 10000]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $pago), [
                'parcialidades' => [
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $response->assertRedirect();

        $pago->refresh();
        expect($pago->estatus)->toBe('parcial');
        expect($pago->pagosParciales)->toHaveCount(2);

        $hijo1 = $pago->pagosParciales->first();
        expect($hijo1->pago_padre_id)->toBe($pago->id);
        expect($hijo1->estatus)->toBe('programado');
        expect($hijo1->numero_parcialidad)->toBe(1);
        expect($hijo1->folio)->toStartWith('PG-');
    });

    test('child pagos start as programado', function () {
        $pago = Pago::factory()->credito()->programado()->create(['monto_pago' => 10000]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $pago), [
                'parcialidades' => [
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $pago->refresh();
        $pago->pagosParciales->each(function ($hijo) {
            expect($hijo->estatus)->toBe('programado');
        });
    });

    test('credito all child pagos pagados marks parent and solicitud as pagado', function () {
        Storage::fake('public');

        $solicitud = SolicitudPago::factory()->aprobada()->create();
        $pago = Pago::factory()->credito()->create([
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => $solicitud->id,
            'monto_pago' => $solicitud->monto_total,
            'estatus' => 'parcial',
        ]);

        // Crear 2 hijos, uno ya pagado
        Pago::factory()->hijo($pago, 1)->pagado()->create(['monto_pago' => 5000]);
        $hijo2 = Pago::factory()->hijo($pago, 2)->create(['monto_pago' => 5000]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.upload-comprobante', $hijo2), [
                'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 100),
            ]);

        $response->assertRedirect();

        $pago->refresh();
        expect($pago->estatus)->toBe('pagado');

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('pagada');
    });

    test('cannot parcializar pago contado', function () {
        $pago = Pago::factory()->contado()->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $pago), [
                'parcialidades' => [
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $response->assertSessionHasErrors(['tipo_pago']);
    });

    test('cannot parcializar already parcializado pago', function () {
        $pago = Pago::factory()->credito()->create(['monto_pago' => 10000, 'estatus' => 'parcial']);
        Pago::factory()->hijo($pago, 1)->create(['monto_pago' => 5000]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $pago), [
                'parcialidades' => [
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 5000, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $response->assertSessionHasErrors(['parcialidades']);
    });

    test('recursive parcializacion is allowed', function () {
        Storage::fake('public');

        $solicitud = SolicitudPago::factory()->aprobada()->create();
        $raiz = Pago::factory()->credito()->create([
            'pagable_type' => SolicitudPago::class,
            'pagable_id' => $solicitud->id,
            'monto_pago' => 10000,
            'estatus' => 'parcial',
        ]);

        // Crear hijo1 pagado y hijo2 programado (crédito)
        Pago::factory()->hijo($raiz, 1)->pagado()->create(['monto_pago' => 5000]);
        $hijo2 = Pago::factory()->hijo($raiz, 2)->create([
            'monto_pago' => 5000,
            'tipo_pago' => 'credito',
        ]);

        // Parcializar hijo2 en 2 nietos
        $response = $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.parcializar', $hijo2), [
                'parcialidades' => [
                    ['monto' => 2500, 'fecha_programada' => now()->addDays(10)->toDateString()],
                    ['monto' => 2500, 'fecha_programada' => now()->addDays(20)->toDateString()],
                ],
            ]);

        $response->assertRedirect();

        $hijo2->refresh();
        expect($hijo2->estatus)->toBe('parcial');
        expect($hijo2->pagosParciales)->toHaveCount(2);

        // Pagar ambos nietos - la cascada debe marcar hijo2, raíz y solicitud como pagados
        $nietos = $hijo2->pagosParciales;

        $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.upload-comprobante', $nietos[0]), [
                'comprobante' => UploadedFile::fake()->create('c1.pdf', 100),
            ]);

        $this->actingAs($this->user)
            ->post(route('admin.costos.pagos.upload-comprobante', $nietos[1]), [
                'comprobante' => UploadedFile::fake()->create('c2.pdf', 100),
            ]);

        $hijo2->refresh();
        expect($hijo2->estatus)->toBe('pagado');

        $raiz->refresh();
        expect($raiz->estatus)->toBe('pagado');

        $solicitud->refresh();
        expect($solicitud->estatus)->toBe('pagada');
    });
});
