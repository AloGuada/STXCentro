<?php

use App\Models\Departamento;
use App\Models\Media;
use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Equipo;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->firma = 'data:image/png;base64,'.base64_encode('firma-de-prueba');
});

describe('enlace de firma', function () {
    test('store generates the firma token', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.sti.asignacion-activos.store'), [
                'departamento_id' => Departamento::factory()->create()->id,
                'equipo_id' => Equipo::factory()->create()->id,
                'no_empleado' => 'EMP001',
                'empleado' => 'Juan Perez',
                'no_ti' => 'TI001',
                'nombre_ti' => 'Soporte TI',
                'fecha_inicial' => '2026-10-01',
                'estado' => 'activo',
            ]);

        $response->assertRedirect(route('admin.sti.asignacion-activos.index'));

        $asignacion = AsignacionActivo::sole();

        expect($asignacion->firma_token)->toBeString()->toHaveLength(48)
            ->and($asignacion->firma_url)->toBe(route('sti.firmas.create', $asignacion->firma_token));
    });

    test('firma url is null without token', function () {
        $asignacion = AsignacionActivo::factory()->create();

        expect($asignacion->firma_url)->toBeNull();
    });

    test('firma url uses the configured base instead of the request host', function () {
        config(['sti.firma_url_base' => 'https://centro.empresa.com/']);

        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        expect($asignacion->firma_url)->toBe('https://centro.empresa.com/sti/firmas/'.$asignacion->firma_token);

        $this->actingAs($this->user)
            ->get('http://localhost/admin/sti/asignacion-activos')
            ->assertInertia(fn ($page) => $page
                ->where('asignaciones.data.0.firma_url', 'https://centro.empresa.com/sti/firmas/'.$asignacion->firma_token)
            );
    });

    test('enlace can be generated for an existing asignacion', function () {
        $asignacion = AsignacionActivo::factory()->create();

        $this->actingAs($this->user)
            ->post(route('admin.sti.asignacion-activos.enlace-firma', $asignacion))
            ->assertRedirect();

        expect($asignacion->fresh()->firma_token)->toBeString()->toHaveLength(48);
    });

    test('generating the enlace again keeps the same token', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();
        $token = $asignacion->firma_token;

        $this->actingAs($this->user)
            ->post(route('admin.sti.asignacion-activos.enlace-firma', $asignacion));

        expect($asignacion->fresh()->firma_token)->toBe($token);
    });

    test('guests cannot generate the enlace', function () {
        $asignacion = AsignacionActivo::factory()->create();

        $this->post(route('admin.sti.asignacion-activos.enlace-firma', $asignacion))
            ->assertRedirect(route('login'));

        expect($asignacion->fresh()->firma_token)->toBeNull();
    });

    test('index sends the firma url of each asignacion', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->actingAs($this->user)
            ->get(route('admin.sti.asignacion-activos.index'))
            ->assertInertia(fn ($page) => $page
                ->where('asignaciones.data.0.firma_url', $asignacion->firma_url)
            );
    });

    test('edit sends the firma url of the asignacion', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->actingAs($this->user)
            ->get(route('admin.sti.asignacion-activos.edit', $asignacion))
            ->assertInertia(fn ($page) => $page
                ->where('asignacion.firma_url', $asignacion->firma_url)
            );
    });
});

describe('pagina publica de firma', function () {
    test('guest can open the firma page with the token', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create(['firma_ti' => $this->firma]);

        $response = $this->get(route('sti.firmas.create', $asignacion->firma_token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('sti/firma-asignacion')
            ->where('token', $asignacion->firma_token)
            ->where('asignacion.empleado', $asignacion->empleado)
            ->where('asignacion.equipo', $asignacion->equipo->descripcion)
            ->where('asignacion.firmada', false)
            ->missing('asignacion.firma_ti')
            ->missing('asignacion.firma_empleado')
        );
    });

    test('firma page shows only the images of the asignacion', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();
        $imagen = Media::factory()->image()->for($asignacion, 'mediable')->create(['descripcion' => 'Laptop de frente']);
        Media::factory()->pdf()->for($asignacion, 'mediable')->create();
        Media::factory()->image()->for(AsignacionActivo::factory()->conEnlaceFirma(), 'mediable')->create();

        $this->get(route('sti.firmas.create', $asignacion->firma_token))
            ->assertInertia(fn ($page) => $page
                ->has('imagenes', 1)
                ->where('imagenes.0', [
                    'id' => $imagen->id,
                    'descripcion' => 'Laptop de frente',
                    'url' => '/storage/'.$imagen->path,
                ])
            );
    });

    test('firma page has no images when none were uploaded', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->get(route('sti.firmas.create', $asignacion->firma_token))
            ->assertInertia(fn ($page) => $page->has('imagenes', 0));
    });

    test('firma page returns not found for an unknown token', function () {
        AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->get('/sti/firmas/token-que-no-existe')->assertNotFound();
    });

    test('firma page cannot be opened with the asignacion id', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->get('/sti/firmas/'.$asignacion->id)->assertNotFound();
    });

    test('guest can sign the asignacion', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $response = $this->post(route('sti.firmas.store', $asignacion->firma_token), [
            'firma_empleado' => $this->firma,
        ]);

        $response->assertRedirect(route('sti.firmas.create', $asignacion->firma_token));

        expect($asignacion->fresh()->firma_empleado)->toBe($this->firma);
    });

    test('signed asignacion is shown as firmada', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create(['firma_empleado' => $this->firma]);

        $this->get(route('sti.firmas.create', $asignacion->firma_token))
            ->assertInertia(fn ($page) => $page->where('asignacion.firmada', true));
    });

    test('asignacion cannot be signed twice', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create(['firma_empleado' => $this->firma]);
        $otraFirma = 'data:image/png;base64,'.base64_encode('otra-firma');

        $response = $this->post(route('sti.firmas.store', $asignacion->firma_token), [
            'firma_empleado' => $otraFirma,
        ]);

        $response->assertSessionHasErrors(['firma_empleado' => 'Esta asignacion ya fue firmada.']);

        expect($asignacion->fresh()->firma_empleado)->toBe($this->firma);
    });

    test('firma is validated', function (?string $firma) {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->post(route('sti.firmas.store', $asignacion->firma_token), ['firma_empleado' => $firma])
            ->assertSessionHasErrors('firma_empleado');

        expect($asignacion->fresh()->firma_empleado)->toBeNull();
    })->with([
        'vacia' => null,
        'texto plano' => 'Juan Perez',
        'otro tipo de archivo' => 'data:text/html;base64,PHNjcmlwdD4=',
        'demasiado grande' => 'data:image/png;base64,'.str_repeat('A', 60000),
    ]);

    test('signing does not change other fields', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create(['empleado' => 'Juan Perez']);

        $this->post(route('sti.firmas.store', $asignacion->firma_token), [
            'firma_empleado' => $this->firma,
            'empleado' => 'Otro Nombre',
            'firma_ti' => $this->firma,
            'estado' => 'devuelto',
        ]);

        expect($asignacion->fresh())
            ->empleado->toBe('Juan Perez')
            ->firma_ti->toBeNull()
            ->estado->toBe('activo');
    });
});

describe('pdf publico', function () {
    test('pdf is not available before signing', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create();

        $this->get(route('sti.firmas.pdf', $asignacion->firma_token))->assertNotFound();
    });

    test('pdf can be downloaded once signed', function () {
        $asignacion = AsignacionActivo::factory()->conEnlaceFirma()->create([
            'firma_empleado' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        ]);

        $response = $this->get(route('sti.firmas.pdf', $asignacion->firma_token));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    });
});
