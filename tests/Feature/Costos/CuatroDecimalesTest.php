<?php

use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Producto;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionDetalle;
use App\Models\Costos\UsoCfdi;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Cantidad;
use Spatie\Permission\Models\Permission;

/**
 * Cantidades y precios unitarios se capturan hasta con 4 decimales en la
 * requisición y la cotización, igual que en la orden de compra. Los importes
 * siguen a 2.
 */
beforeEach(function (): void {
    foreach (['costos.requisiciones.ver', 'costos.requisiciones.crear'] as $permiso) {
        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['costos.requisiciones.ver', 'costos.requisiciones.crear']);

    $this->depto = Departamento::factory()->create();
    $this->presupuesto = Presupuesto::factory()->paraObra()->create();
    $this->rubro = ObraRubro::factory()->create(['presupuesto_id' => $this->presupuesto->id]);
    $this->uso = UsoCfdi::factory()->create();
    $this->producto = Producto::factory()->create();
});

function requisicionConCantidad(mixed $cantidad): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->user)
        ->post('/admin/costos/requisiciones', [
            'departamento_id' => test()->depto->id,
            'presupuesto_id' => test()->presupuesto->id,
            'detalles' => [[
                'producto_id' => test()->producto->id,
                'cantidad' => $cantidad,
                'descripcion' => 'lo que diga el catalogo',
                'unidad' => 'pza',
                'obra_rubro_id' => test()->rubro->id,
                'uso_cfdi_id' => test()->uso->id,
            ]],
        ]);
}

it('guarda la cantidad de la partida con 4 decimales', function (): void {
    requisicionConCantidad('12.3456')->assertSessionHasNoErrors();

    expect(RequisicionDetalle::query()->sole()->cantidad)->toBe('12.3456');
});

it('rechaza una cantidad con más de 4 decimales en vez de redondearla en silencio', function (): void {
    requisicionConCantidad('1.23456')->assertSessionHasErrors('detalles.0.cantidad');

    expect(RequisicionDetalle::query()->count())->toBe(0);
});

it('acepta cantidades menores a un centésimo', function (): void {
    requisicionConCantidad('0.0005')->assertSessionHasNoErrors();

    expect(RequisicionDetalle::query()->sole()->cantidad)->toBe('0.0005');
});

it('el precio cotizado conserva 4 decimales', function (): void {
    $precio = RequisicionCotizacionPrecio::factory()->create(['precio_unitario' => 12.3456]);

    expect($precio->fresh()->precio_unitario)->toBe('12.3456');
});

it('imprime con 2 decimales cuando alcanza y con 4 cuando los trae', function (): void {
    expect(Cantidad::formatear(10))->toBe('10.00')
        ->and(Cantidad::formatear('45.5'))->toBe('45.50')
        ->and(Cantidad::formatear(1.2345))->toBe('1.2345')
        ->and(Cantidad::formatear(1234.5678))->toBe('1,234.5678')
        ->and(Cantidad::formatear(null))->toBe('0.00');
});
