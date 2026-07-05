<?php

use App\Enums\ProveedorEstatus;
use App\Models\Proveedor;
use App\Models\RegimenFiscal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['costos.proveedores.crear', 'costos.proveedores.ver'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    Storage::fake('public');

    $this->compras = User::factory()->create();
    $this->compras->givePermissionTo('costos.proveedores.crear');

    $this->regimen = RegimenFiscal::factory()->create();
});

function payloadProveedor(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'PROV001',
        'razon_social' => 'Aceros del Norte SA de CV',
        'rfc' => 'ANO123456XY0',
        'tipo_persona' => 'moral',
        'regimen_fiscal_id' => test()->regimen->id,
        'codigo_postal' => '64000',
        'domicilio_fiscal' => 'Av. Siempre Viva 123',
        'email' => 'compras@aceros.mx',
        'banco' => 'BBVA',
        'titular_cuenta' => 'Aceros del Norte',
        'clabe' => '012345678901234567',
        'moneda_cuenta' => 'MXN',
        'constancia' => UploadedFile::fake()->image('constancia.jpg'),
        'caratula' => UploadedFile::fake()->image('caratula.jpg'),
    ], $overrides);
}

test('alta crea proveedor desactivado y pendiente de validación', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor())
        ->assertRedirect();

    $proveedor = Proveedor::first();
    expect($proveedor)->not->toBeNull();
    expect($proveedor->estatus)->toBe(ProveedorEstatus::PendienteValidacion);
    expect($proveedor->activo)->toBeFalse();
    expect($proveedor->creado_por)->toBe($this->compras->id);
    expect($proveedor->media()->where('descripcion', 'constancia_fiscal')->exists())->toBeTrue();
    expect($proveedor->media()->where('descripcion', 'caratula_bancaria')->exists())->toBeTrue();
});

test('régimen, constancia y carátula son obligatorios', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'regimen_fiscal_id' => null,
            'constancia' => null,
            'caratula' => null,
        ]))
        ->assertSessionHasErrors(['regimen_fiscal_id', 'constancia', 'caratula']);

    expect(Proveedor::count())->toBe(0);
});

test('requiere al menos un medio de depósito', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'clabe' => null,
            'tarjeta' => null,
            'numero_cuenta' => null,
        ]))
        ->assertSessionHasErrors(['clabe']);

    expect(Proveedor::count())->toBe(0);
});

test('basta el número de cuenta como medio de depósito', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'clabe' => null,
            'tarjeta' => null,
            'numero_cuenta' => '1234567890',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Proveedor::first()->numero_cuenta)->toBe('1234567890');
});

test('basta la tarjeta como medio de depósito', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'clabe' => null,
            'numero_cuenta' => null,
            'tarjeta' => '4152313412341234',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Proveedor::first()->tarjeta)->toBe('4152313412341234');
});

test('el titular debe coincidir con la razón social', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor(['titular_cuenta' => 'Otra Empresa Distinta']))
        ->assertSessionHasErrors(['titular_cuenta']);
});

test('solo usuarios con permiso pueden dar de alta proveedores', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->post('/admin/proveedores', payloadProveedor())
        ->assertForbidden();

    expect(Proveedor::count())->toBe(0);
});
