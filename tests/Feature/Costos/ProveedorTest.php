<?php

use App\Models\Proveedor;
use App\Models\RegimenFiscal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'costos.proveedores.ver',
        'costos.proveedores.crear',
        'costos.proveedores.editar',
        'costos.proveedores.eliminar',
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'costos.proveedores.ver',
        'costos.proveedores.crear',
        'costos.proveedores.editar',
        'costos.proveedores.eliminar',
    ]);

    $this->regimen = RegimenFiscal::factory()->create();
});

function datosProveedorValidos(array $overrides = []): array
{
    return array_merge([
        'codigo' => 'PROV001',
        'razon_social' => 'Test SA de CV',
        'rfc' => 'TST123456AB0',
        'tipo_persona' => 'moral',
        'regimen_fiscal_id' => test()->regimen->id,
        'codigo_postal' => '64000',
        'domicilio_fiscal' => 'Calle 1',
        'email' => 'test@test.mx',
        'banco' => 'Banorte',
        'titular_cuenta' => 'Test',
        'numero_cuenta' => '1234567890',
        'moneda_cuenta' => 'MXN',
        'constancia' => UploadedFile::fake()->image('constancia.jpg'),
        'caratula' => UploadedFile::fake()->image('caratula.jpg'),
        'tiene_acceso_portal' => false,
        'maneja_credito' => false,
    ], $overrides);
}

describe('admin proveedores', function () {
    test('index page can be rendered', function () {
        Proveedor::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/index')
            ->has('proveedores.data', 3)
        );
    });

    test('index supports search filter', function () {
        Proveedor::factory()->create(['razon_social' => 'Acme Corp']);
        Proveedor::factory()->create(['razon_social' => 'Beta Inc']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.index', ['search' => 'Acme']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('proveedores.data', 1)
        );
    });

    test('create page can be rendered', function () {
        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/create')
            ->has('departamentos')
            ->has('regimenes')
        );
    });

    test('proveedor can be stored', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), datosProveedorValidos());

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'codigo' => 'PROV001',
            'razon_social' => 'Test SA de CV',
            'estatus' => 'pendiente_validacion',
            'activo' => false,
        ]);
    });

    test('edit page can be rendered', function () {
        $proveedor = Proveedor::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.proveedores.edit', $proveedor));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/proveedores/edit')
            ->has('proveedor')
            ->has('departamentos')
            ->has('regimenes')
        );
    });

    test('proveedor can be updated', function () {
        $proveedor = Proveedor::factory()->create(['razon_social' => 'Vieja SA']);

        $response = $this->actingAs($this->user)
            ->put(route('admin.proveedores.update', $proveedor), datosProveedorValidos([
                'codigo' => $proveedor->codigo,
                'rfc' => $proveedor->rfc,
                'razon_social' => 'Updated SA',
                'titular_cuenta' => 'Updated',
                'constancia' => null,
                'caratula' => null,
            ]));

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'razon_social' => 'Updated SA',
        ]);
    });

    test('proveedor can be deleted', function () {
        $proveedor = Proveedor::factory()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.proveedores.destroy', $proveedor));

        $response->assertRedirect(route('admin.proveedores.index'));
        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    });

    test('validation requires codigo and razon_social and rfc', function () {
        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), []);

        $response->assertSessionHasErrors(['codigo', 'razon_social', 'rfc']);
    });

    test('codigo must be unique', function () {
        Proveedor::factory()->create(['codigo' => 'DUP001']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.proveedores.store'), datosProveedorValidos([
                'codigo' => 'DUP001',
                'razon_social' => 'Test',
                'titular_cuenta' => 'Test',
                'rfc' => 'UNIQUE12345AB',
            ]));

        $response->assertSessionHasErrors(['codigo']);
    });
});
