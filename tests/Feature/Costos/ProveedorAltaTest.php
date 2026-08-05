<?php

use App\Enums\ProveedorEstatus;
use App\Enums\TipoProveedor;
use App\Models\Banco;
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
    $this->bancoPagador = Banco::factory()->pagador(10)->create(['nombre' => 'Banorte']);
    $this->otroBanco = Banco::factory()->create(['nombre' => 'BBVA']);
});

function payloadProveedor(array $overrides = []): array
{
    return array_merge([
        'tipo_proveedor' => 'proveedor',
        'razon_social' => 'Aceros del Norte SA de CV',
        'rfc' => 'ANO123456XY0',
        'tipo_persona' => 'moral',
        'regimen_fiscal_id' => test()->regimen->id,
        'codigo_postal' => '64000',
        'domicilio_fiscal' => 'Av. Siempre Viva 123',
        'email' => 'compras@aceros.mx',
        'forma_pago' => 'transferencia',
        'banco_id' => test()->otroBanco->id,
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
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $proveedor = Proveedor::first();
    expect($proveedor)->not->toBeNull();
    expect($proveedor->estatus)->toBe(ProveedorEstatus::PendienteValidacion);
    expect($proveedor->activo)->toBeFalse();
    expect($proveedor->tipo_proveedor)->toBe(TipoProveedor::Proveedor);
    expect($proveedor->creado_por)->toBe($this->compras->id);
    expect($proveedor->media()->where('descripcion', 'constancia_fiscal')->exists())->toBeTrue();
    expect($proveedor->media()->where('descripcion', 'caratula_bancaria')->exists())->toBeTrue();
});

test('guarda el correo del contacto separado del email de acceso al portal', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'email' => 'acceso@aceros.mx',
            'contacto_nombre' => 'Juan Pérez',
            'contacto_correo' => 'juan.contacto@aceros.mx',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $proveedor = Proveedor::first();
    expect($proveedor->email)->toBe('acceso@aceros.mx');
    expect($proveedor->contacto_correo)->toBe('juan.contacto@aceros.mx');
});

test('el correo del contacto debe tener formato de email', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor(['contacto_correo' => 'no-es-correo']))
        ->assertSessionHasErrors(['contacto_correo']);

    expect(Proveedor::count())->toBe(0);
});

test('régimen, constancia y carátula son obligatorios para proveedor', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'regimen_fiscal_id' => null,
            'constancia' => null,
            'caratula' => null,
        ]))
        ->assertSessionHasErrors(['regimen_fiscal_id', 'constancia', 'caratula']);

    expect(Proveedor::count())->toBe(0);
});

test('el banco pagador exige número de cuenta de la longitud del catálogo', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'banco_id' => $this->bancoPagador->id,
            'clabe' => null,
            'numero_cuenta' => '123',
        ]))
        ->assertSessionHasErrors(['numero_cuenta']);

    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'banco_id' => $this->bancoPagador->id,
            'clabe' => null,
            'numero_cuenta' => '1234567890',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Proveedor::first()->numero_cuenta)->toBe('1234567890');
});

test('un banco distinto al pagador exige CLABE de 18 dígitos', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'banco_id' => $this->otroBanco->id,
            'clabe' => null,
            'numero_cuenta' => '1234567890',
        ]))
        ->assertSessionHasErrors(['clabe']);

    expect(Proveedor::count())->toBe(0);
});

test('cheque o efectivo no exige datos bancarios ni carátula', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'forma_pago' => 'cheque_efectivo',
            'banco_id' => null,
            'clabe' => null,
            'titular_cuenta' => null,
            'caratula' => null,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Proveedor::first()->forma_pago->value)->toBe('cheque_efectivo');
});

test('el titular debe coincidir con la razón social para proveedor', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor(['titular_cuenta' => 'Otra Empresa Distinta']))
        ->assertSessionHasErrors(['titular_cuenta']);
});

test('un tercero permite titular distinto y sin RFC', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'tipo_proveedor' => 'tercero',
            'rfc' => null,
            'regimen_fiscal_id' => null,
            'tipo_persona' => null,
            'codigo_postal' => null,
            'domicilio_fiscal' => null,
            'constancia' => null,
            'titular_cuenta' => 'Juan Pérez López',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Proveedor::first()->tipo_proveedor)->toBe(TipoProveedor::Tercero);
});

test('un servicio no requiere banca ni fiscal pero exige número de servicio y referencia', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', [
            'tipo_proveedor' => 'servicio',
            'razon_social' => 'Comisión Federal de Electricidad',
            'numero_servicio' => '1234567890',
            'referencia_servicio' => '9988',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $servicio = Proveedor::first();
    expect($servicio->tipo_proveedor)->toBe(TipoProveedor::Servicio);
    expect($servicio->numero_servicio)->toBe('1234567890');
    expect($servicio->banco_id)->toBeNull();
});

test('un servicio sin número de servicio falla', function () {
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', [
            'tipo_proveedor' => 'servicio',
            'razon_social' => 'Agua y Drenaje',
            'referencia_servicio' => '9988',
        ])
        ->assertSessionHasErrors(['numero_servicio']);

    expect(Proveedor::count())->toBe(0);
});

test('solo usuarios con permiso pueden dar de alta proveedores', function () {
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso)
        ->post('/admin/proveedores', payloadProveedor())
        ->assertForbidden();

    expect(Proveedor::count())->toBe(0);
});

test('el email solo es obligatorio si el proveedor tendrá acceso al portal', function () {
    // Sin portal: se puede dar de alta un proveedor formal sin correo.
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'email' => '',
            'rfc' => 'SIN123456XY0',
        ]))
        ->assertSessionHasNoErrors();

    expect(Proveedor::where('rfc', 'SIN123456XY0')->first()->email)->toBeNull();

    // Con portal: el correo es la cuenta, así que se exige.
    $this->actingAs($this->compras)
        ->post('/admin/proveedores', payloadProveedor([
            'email' => '',
            'rfc' => 'POR123456XY0',
            'tiene_acceso_portal' => true,
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ]))
        ->assertSessionHasErrors('email');
});
