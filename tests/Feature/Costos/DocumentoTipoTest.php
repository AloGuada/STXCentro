<?php

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Entrega;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

describe('DocumentoTipo enum', function () {
    test('xml_factura acepta solo xml', function () {
        expect(DocumentoTipo::XmlFactura->mimes())->toContain('xml');
        expect(DocumentoTipo::XmlFactura->mimes())->not->toContain('pdf');
    });

    test('pdf_factura y variantes de pago exigen pdf', function () {
        expect(DocumentoTipo::PdfFactura->mimes())->toBe('pdf');
        expect(DocumentoTipo::ComprobantePago->mimes())->toBe('pdf');
        expect(DocumentoTipo::SolicitudFirmada->mimes())->toBe('pdf');
        expect(DocumentoTipo::OcPdfFormato->mimes())->toBe('pdf');
        expect(DocumentoTipo::OcPdfFirmado->mimes())->toBe('pdf');
    });

    test('evidencia_recepcion acepta pdf e imagenes', function () {
        $mimes = DocumentoTipo::EvidenciaRecepcion->mimes();
        expect($mimes)->toContain('pdf');
        expect($mimes)->toContain('jpg');
        expect($mimes)->toContain('png');
    });

    test('options retorna todos los casos con label en espanol', function () {
        $opts = DocumentoTipo::options();
        expect($opts)->toHaveKey('xml_factura');
        expect($opts['xml_factura'])->toBe('XML de factura');
        expect($opts['comprobante_pago'])->toBe('Comprobante de pago');
    });
});

describe('sweep de descripciones canonicas al subir archivos', function () {
    test('OC::store guarda media con descripcion oc_archivo', function () {
        Storage::fake('public');

        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'costos.ordenes-compra.crear', 'guard_name' => 'web']);
        $user->givePermissionTo('costos.ordenes-compra.crear');
        $prov = Proveedor::factory()->create();

        $this->actingAs($user)->post('/admin/costos/ordenes-compra', [
            'proveedor_id' => $prov->id,
            'departamento_id' => \App\Models\Departamento::factory()->create()->id,
            'moneda' => 'mxn',
            'fecha_entrega_esperada' => now()->addDays(7)->format('Y-m-d'),
            'total' => 500,
            'archivo' => UploadedFile::fake()->create('oc.pdf', 200, 'application/pdf'),
            'detalles' => [[
                'obra_rubro_id' => \App\Models\Costos\ObraRubro::factory()->create()->id,
                'descripcion' => 'Test',
                'unidad' => 'pza',
                'cantidad' => 5,
                'precio_unitario' => 100,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('media', [
            'mediable_type' => OrdenCompra::class,
            'descripcion' => 'oc_archivo',
        ]);
    });

    test('Entrega::store guarda media con descripcion evidencia_recepcion', function () {
        Storage::fake('public');

        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'costos.entregas.crear', 'guard_name' => 'web']);
        $user->givePermissionTo('costos.entregas.crear');

        $oc = OrdenCompra::factory()->pendienteFactura()->create(['total' => 1000]);
        $p = OrdenCompraDetalle::factory()->create([
            'orden_compra_id' => $oc->id,
            'cantidad' => 10,
            'precio_unitario' => 100,
            'subtotal' => 1000,
        ]);

        $this->actingAs($user)->post('/admin/almacen/entradas', [
            'orden_compra_id' => $oc->id,
            'almacen_id' => almacenParaRecibir($user)->id,
            'fecha_entrega' => '2026-04-24',
            'tipo' => 'parcial',
            'archivo' => UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf'),
            'detalles' => [
                ['orden_compra_detalle_id' => $p->id, 'cantidad_recibida' => 5],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('media', [
            'mediable_type' => Entrega::class,
            'descripcion' => 'evidencia_recepcion',
        ]);
    });

    test('Pago::uploadComprobante guarda descripcion comprobante_pago', function () {
        Storage::fake('public');

        $user = User::factory()->create();
        $pago = Pago::factory()->create(['estatus' => 'programado']);

        $this->actingAs($user)->post("/admin/costos/pagos/{$pago->id}/upload-comprobante", [
            'comprobante' => UploadedFile::fake()->create('rec.pdf', 100, 'application/pdf'),
            'notas' => 'Pagado via SPEI',
        ])->assertRedirect();

        $this->assertDatabaseHas('media', [
            'mediable_type' => Pago::class,
            'descripcion' => 'comprobante_pago',
        ]);
    });
});
