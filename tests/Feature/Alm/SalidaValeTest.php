<?php

use App\Enums\Alm\DocumentoAlm;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Salida;
use App\Models\Alm\SalidaDetalle;
use App\Models\Costos\Producto;
use App\Models\User;
use App\Services\Alm\AlmacenLedger;
use App\Services\Alm\FirmasDelFormato;
use Spatie\Permission\Models\Permission;

/**
 * @param  list<string>  $permisos
 */
function usuarioDelVale(array $permisos = ['alm.salidas.ver', 'alm.almacenes.ver-todos']): User
{
    $user = User::factory()->create();

    foreach ($permisos as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permisos);

    return $user;
}

/** Una salida con un renglon, lista para imprimir. */
function salidaImprimible(?Almacen $almacen = null): Salida
{
    $almacen ??= Almacen::factory()->create();
    $producto = Producto::factory()->create(['codigo' => 'ART-00013', 'descripcion' => 'Electrodo 7018']);

    app(AlmacenLedger::class)->registrarPorProducto(
        $almacen->id, $producto->id, MovimientoTipo::Entrada, 50, 12.5
    );

    $salida = Salida::factory()->de($almacen)->create(['recibe_nombre' => 'Cuadrilla 3']);

    SalidaDetalle::create([
        'salida_id' => $salida->id,
        'producto_id' => $producto->id,
        'cantidad' => 4,
        'costo_unitario' => 12.5,
    ]);

    return $salida;
}

describe('el vale impreso', function () {
    it('entrega un pdf', function () {
        $salida = salidaImprimible();

        $respuesta = $this->actingAs(usuarioDelVale())
            ->get("/admin/almacen/salidas/{$salida->id}/pdf");

        $respuesta->assertOk();
        expect($respuesta->headers->get('content-type'))->toContain('application/pdf');
        expect($respuesta->getContent())->toStartWith('%PDF-');
    });

    /**
     * Es la razon de ser del sello: la hoja vuelve firmada y hay que
     * reencontrarla escaneandola.
     */
    it('dibuja el folio en codigo de barras', function () {
        $salida = salidaImprimible();
        $salida->load(['almacen', 'grupoTrabajo', 'pedido', 'entregador', 'detalles.producto']);

        $html = view('pdf.alm.formato-salida', [
            'salida' => $salida,
            'firmas' => app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id),
        ])->render();
        $esperadas = App\Support\Code39::dibujo($salida->folio)['barras'];

        expect(substr_count($html, 'background-color: #000000;'))->toBe(count($esperadas));
    });

    it('lista lo entregado con su costo y su total', function () {
        $salida = salidaImprimible();
        $salida->load(['almacen', 'grupoTrabajo', 'pedido', 'entregador', 'detalles.producto']);

        $html = view('pdf.alm.formato-salida', [
            'salida' => $salida,
            'firmas' => app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id),
        ])->render();

        expect($html)
            ->toContain('ART-00013')
            ->toContain('Electrodo 7018')
            ->toContain('Cuadrilla 3')
            // 4 x 12.50
            ->toContain('$50.00');
    });

    /**
     * El folio existe y alguien puede traer la hoja de vuelta, asi que se
     * imprime igual — pero diciendo de frente que ya no ampara nada.
     */
    it('imprime la cancelada, marcada como cancelada', function () {
        $almacen = Almacen::factory()->create();
        $salida = salidaImprimible($almacen);
        $salida->update(['cancelada_at' => now(), 'motivo_cancelacion' => 'Error de captura']);
        $salida->load(['almacen', 'grupoTrabajo', 'pedido', 'entregador', 'detalles.producto']);

        $html = view('pdf.alm.formato-salida', [
            'salida' => $salida,
            'firmas' => app(FirmasDelFormato::class)->para(DocumentoAlm::Salida, $salida->almacen_id),
        ])->render();

        expect($html)->toContain('CANCELADA')->toContain('Error de captura');

        $this->actingAs(usuarioDelVale())
            ->get("/admin/almacen/salidas/{$salida->id}/pdf")
            ->assertOk();
    });
});

describe('quien puede imprimirlo', function () {
    it('exige el permiso de ver salidas', function () {
        $salida = salidaImprimible();

        $this->actingAs(usuarioDelVale(['alm.almacenes.ver-todos']))
            ->get("/admin/almacen/salidas/{$salida->id}/pdf")
            ->assertForbidden();
    });

    /**
     * El permiso abre la pantalla; el almacen decide el documento. Sin
     * `ver-todos` y sin estar asignado, la salida es de otra bodega.
     */
    it('respeta la visibilidad por almacen', function () {
        $salida = salidaImprimible();

        $this->actingAs(usuarioDelVale(['alm.salidas.ver']))
            ->get("/admin/almacen/salidas/{$salida->id}/pdf")
            ->assertForbidden();
    });
});
