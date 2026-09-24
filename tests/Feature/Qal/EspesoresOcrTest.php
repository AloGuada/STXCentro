<?php

use App\Models\User;
use App\Services\Ocr\OcrClient;
use App\Services\Qal\LecturasDelCalibre;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

/**
 * Leer los espesores de una foto de la pantalla del calibre.
 *
 * Lo que se comprueba es el criterio con el que se separa el espesor del resto
 * de la pantalla: el número de renglón es entero y la hora lleva dos puntos,
 * así que lo único con decimales es la lectura. El OCR en sí no se prueba aquí
 * —es un servicio aparte— sino lo que se hace con lo que devuelve.
 */
function usuarioDeCaptura(): User
{
    Permission::firstOrCreate(['name' => 'qal.inspecciones.crear', 'guard_name' => 'web']);

    $usuario = User::factory()->create();
    $usuario->givePermissionTo('qal.inspecciones.crear');

    return $usuario;
}

test('de la pantalla salen los espesores y no los renglones ni las horas', function () {
    // Tal cual lo devuelve el sidecar con una pantalla de 10 lecturas.
    $lineas = [
        'Lote 3', 'mils',
        '1', '2.85', '10:31',
        '2', '3.10', '10:31',
        '3', '2.97', '10:32',
    ];

    expect(app(LecturasDelCalibre::class)->deLineas($lineas))->toBe([2.85, 3.10, 2.97]);
});

test('se descarta lo que no puede ser un espesor', function () {
    $lineas = [
        '10:31',    // hora
        '4',        // número de renglón
        '0.00',     // una lectura en cero no es una medición
        '1250.75',  // fuera de rango: el OCR leyó mal
        '2,60',     // coma decimal: es válido
        'mils',
    ];

    expect(app(LecturasDelCalibre::class)->deLineas($lineas))->toBe([2.60]);
});

test('la foto devuelve las lecturas reconocidas', function () {
    $this->mock(OcrClient::class)
        ->shouldReceive('lineas')
        ->once()
        ->andReturn(['1', '2.85', '10:31', '2', '3.10', '10:31']);

    $this->actingAs(usuarioDeCaptura())
        ->postJson(route('admin.qal.espesores.ocr'), ['foto' => UploadedFile::fake()->image('pantalla.jpg')])
        ->assertOk()
        ->assertExactJson(['lecturas' => [2.85, 3.10]]);
});

test('sin el sidecar levantado se avisa en vez de fallar en silencio', function () {
    $this->mock(OcrClient::class)
        ->shouldReceive('lineas')
        ->andThrow(new RuntimeException('El servicio de OCR no responde.'));

    $this->actingAs(usuarioDeCaptura())
        ->postJson(route('admin.qal.espesores.ocr'), ['foto' => UploadedFile::fake()->image('pantalla.jpg')])
        ->assertStatus(503)
        ->assertJson(['message' => 'El servicio de OCR no responde.']);
});

test('leer espesores pide permiso de captura', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.qal.espesores.ocr'), ['foto' => UploadedFile::fake()->image('pantalla.jpg')])
        ->assertForbidden();
});
