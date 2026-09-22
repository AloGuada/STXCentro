<?php

use App\Enums\Alm\DocumentoAlm;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Pedido;
use App\Models\Alm\Transferencia;
use App\Models\User;
use App\Services\Alm\FirmasDelFormato;
use Spatie\Permission\Models\Permission;

/**
 * Los formatos impresos de Almacén y sus espacios de firma.
 *
 * El módulo no tiene flujo de aprobación —nadie autoriza nada en pantalla y
 * ningún documento queda detenido— así que la autorización se resuelve firmando
 * la hoja. Estos tests comprueban que la hoja exista y que traiga sus rayas: si
 * un formato sale sin ellas, no sirve para lo único que se imprime.
 *
 * Las firmas se comprueban sobre la vista y no sobre el PDF: dompdf comprime la
 * salida, así que buscar un texto en el binario no probaría nada.
 *
 * Los rótulos ya no viven en el blade: salen de lo configurado en Almacén >
 * Aprobaciones, y un almacén sin configurar cae en la plantilla del enum. Estos
 * tests pasan por el mismo servicio que usa el controlador, así que también
 * comprueban que esa plantilla siga diciendo lo que la hoja necesita.
 */
function firmasDe(DocumentoAlm $tipo, ?int $almacenId, $documento): array
{
    return app(FirmasDelFormato::class)->para($tipo, $almacenId, $documento);
}

function usuarioQuePuede(string $permiso): User
{
    $usuario = User::factory()->create();

    // Ver el formato exige, además del permiso del documento, poder ver su
    // almacén: un vale de otra bodega no es asunto de quien lo pide.
    foreach ([$permiso, 'alm.almacenes.ver-todos'] as $nombre) {
        Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        $usuario->givePermissionTo($nombre);
    }

    return $usuario;
}

describe('el pedido', function () {
    test('se imprime', function () {
        $pedido = Pedido::factory()->create(['almacen_id' => Almacen::factory()]);

        $this->actingAs(usuarioQuePuede('alm.pedidos.ver'))
            ->get(route('admin.alm.pedidos.pdf', $pedido))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('lleva las tres firmas de sus tres responsabilidades', function () {
        // Quien lo necesita, quien dice que sí, y quien lo surte. La de en
        // medio es la que sustituye al flujo de aprobación que no existe.
        $pedido = Pedido::factory()->create(['almacen_id' => Almacen::factory()]);

        $html = view('pdf.alm.formato-pedido', [
            'pedido' => $pedido->load('detalles'),
            'firmas' => firmasDe(DocumentoAlm::Pedido, $pedido->almacen_id, $pedido),
        ])->render();

        expect($html)
            ->toContain('Solicitó')
            ->toContain('Autorizó')
            ->toContain('Surtió - Almacén')
            ->toContain('firma-linea');
    });

    test('quien no puede verlo tampoco puede imprimirlo', function () {
        $pedido = Pedido::factory()->create(['almacen_id' => Almacen::factory()]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.alm.pedidos.pdf', $pedido))
            ->assertForbidden();
    });
});

describe('el ajuste', function () {
    test('se imprime', function () {
        $ajuste = Ajuste::factory()->create(['almacen_id' => Almacen::factory()]);

        $this->actingAs(usuarioQuePuede('alm.ajustes.ver'))
            ->get(route('admin.alm.ajustes.pdf', $ajuste))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('el acta del conteo lleva quien contó, quien autorizó y el jefe', function () {
        // Un ajuste mueve saldo sin que entre ni salga nada: alguien tiene que
        // responder por esa diferencia.
        $ajuste = Ajuste::factory()->create(['almacen_id' => Almacen::factory()]);

        $html = view('pdf.alm.formato-ajuste', [
            'ajuste' => $ajuste->load('detalles'),
            'firmas' => firmasDe(DocumentoAlm::Ajuste, $ajuste->almacen_id, $ajuste),
        ])->render();

        expect($html)
            ->toContain('Contó')
            ->toContain('Autorizó')
            ->toContain('Jefe de almacén');
    });
});

describe('la transferencia', function () {
    test('se imprime', function () {
        $transferencia = Transferencia::factory()->create([
            'almacen_origen_id' => Almacen::factory(),
            'almacen_destino_id' => Almacen::factory(),
        ]);

        $this->actingAs(usuarioQuePuede('alm.transferencias.recibir'))
            ->get(route('admin.alm.transferencias.pdf', $transferencia))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    test('la hoja que viaja lleva las firmas de los dos extremos', function () {
        // Despacha uno y recibe otro, casi nunca el mismo día: la hoja vuelve
        // firmada por el destino y ése es el comprobante de que llegó.
        $transferencia = Transferencia::factory()->create([
            'almacen_origen_id' => Almacen::factory(),
            'almacen_destino_id' => Almacen::factory(),
        ]);

        $html = view('pdf.alm.formato-transferencia', [
            'transferencia' => $transferencia->load('detalles'),
            'firmas' => firmasDe(DocumentoAlm::Transferencia, $transferencia->almacen_origen_id, $transferencia),
        ])->render();

        expect($html)
            ->toContain('Autorizó')
            ->toContain('Despachó - Origen')
            ->toContain('Recibió - Destino');
    });
});
