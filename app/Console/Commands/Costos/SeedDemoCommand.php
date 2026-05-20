<?php

namespace App\Console\Commands\Costos;

use App\Enums\Costos\FacturaEstatus;
use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Models\Costos\Factura;
use App\Models\Costos\FacturaDetalle;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Pago;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoCommand extends Command
{
    protected $signature = 'costos:demo {--force : No preguntar confirmación}';

    protected $description = 'Limpia datos transaccionales de costos y siembra OCs en todos los estados (incluyendo retrasada).';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Esto BORRA todas las facturas, OCs, entregas, pagos, anticipos, notas de credito, devoluciones, requisiciones y afectaciones. ¿Continuar?')) {
            return self::FAILURE;
        }

        $this->reset();
        $this->seed();

        $this->newLine();
        $this->info('Resumen:');
        $this->table(
            ['Estatus', 'Cantidad'],
            collect(OrdenCompra::all())
                ->groupBy(fn ($oc) => $oc->estatus->value)
                ->map(fn ($g, $k) => [$k, $g->count()])
                ->values()
                ->toArray()
        );

        $retrasadas = OrdenCompra::all()->filter->retrasada->count();
        $this->line("Retrasadas (computed): {$retrasadas}");

        return self::SUCCESS;
    }

    private function reset(): void
    {
        $this->info('Limpiando datos transaccionales...');

        DB::table('costos_pagos')->delete();
        DB::table('media')->where('mediable_type', Factura::class)->delete();
        DB::table('media')->where('mediable_type', OrdenCompra::class)->delete();
        DB::table('media')->where('mediable_type', Entrega::class)->delete();

        DB::table('costos_anticipo_aplicaciones')->delete();
        DB::table('costos_notas_credito')->delete();
        DB::table('costos_factura_detalle')->delete();
        DB::table('costos_facturas')->delete();

        DB::table('costos_devoluciones')->delete();
        DB::table('costos_entrega_detalle')->delete();
        DB::table('costos_entregas')->delete();

        DB::table('costos_aprobaciones')->delete();
        DB::table('costos_aprobaciones_solicitud')->delete();
        DB::table('costos_solicitud_archivos')->delete();
        DB::table('costos_solicitudes_pago')->delete();

        DB::table('costos_anticipos')->delete();
        DB::table('costos_cancelaciones')->delete();
        DB::table('costos_rubros_afectados')->delete();
        DB::table('costos_ordenes_compra_detalle')->delete();
        DB::table('costos_ordenes_compra')->delete();

        DB::table('costos_requisicion_seleccion')->delete();
        DB::table('costos_requisicion_cotizacion_precio')->delete();
        DB::table('costos_requisicion_detalle')->delete();
        DB::table('costos_requisiciones')->delete();

        DB::table('costos_afectaciones_presupuestales')->delete();

        ObraRubro::query()->update(['acumulado' => 0]);
    }

    private function seed(): void
    {
        $this->info('Sembrando OCs en cada estado...');

        $proveedor = Proveedor::first() ?? Proveedor::factory()->create(['razon_social' => 'Proveedor Demo']);
        $obra = Obra::first() ?? Obra::factory()->create();
        $depto = Departamento::first() ?? Departamento::factory()->create();
        $user = Usuario::first() ?? Usuario::factory()->create();
        $rubro = ObraRubro::first() ?? ObraRubro::factory()->create(['presupuestado' => 1000000, 'acumulado' => 0]);

        $base = [
            'proveedor_id' => $proveedor->id,
            'obra_id' => $obra->id,
            'departamento_id' => $depto->id,
            'creado_por' => $user->id,
            'moneda' => 'mxn',
            'total' => 5000,
        ];

        $hoyMas7 = now()->addDays(7)->format('Y-m-d');
        $hoyMenos5 = now()->subDays(5)->format('Y-m-d');

        // 1. pendiente_entrega — autorizada, sin entrega aún
        $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-PEND-ENTREGA',
        ], $rubro);

        // 2. pendiente_entrega RETRASADA — fecha pasada, sin entregas
        $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMenos5,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-RETRASADA',
        ], $rubro);

        // 3. pendiente_factura — ya con entrega, esperando que el proveedor facture
        $oc3 = $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-PEND-FACTURA',
        ], $rubro);
        $this->registrarEntregaCompleta($oc3, $user);

        // 4. pendiente_aprobacion — con factura activa
        $oc4 = $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-PEND-APROB',
        ], $rubro);
        $this->registrarEntregaCompleta($oc4, $user);
        $this->crearFactura($oc4, FacturaEstatus::PendienteAprobacion);
        $oc4->recalcularEstatus();

        // 5. pendiente_pago — factura aprobada por costos
        $oc5 = $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-PEND-PAGO',
        ], $rubro);
        $this->registrarEntregaCompleta($oc5, $user);
        $factura5 = $this->crearFactura($oc5, FacturaEstatus::PendientePago);
        $factura5->update([
            'aprobada_costos' => true,
            'aprobada_costos_por' => $user->id,
            'aprobada_costos_at' => now(),
        ]);
        $oc5->recalcularEstatus();

        // 6. pagada — factura completamente pagada
        $oc6 = $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'pendiente_entrega',
            'referencia' => 'DEMO-PAGADA',
        ], $rubro);
        $this->registrarEntregaCompleta($oc6, $user);
        $factura6 = $this->crearFactura($oc6, FacturaEstatus::Pagada);
        $factura6->update([
            'aprobada_costos' => true,
            'aprobada_costos_por' => $user->id,
            'aprobada_costos_at' => now()->subDays(3),
            'aceptada_contabilidad' => true,
            'aceptada_contabilidad_por' => $user->id,
            'aceptada_contabilidad_at' => now()->subDays(2),
        ]);
        Pago::create([
            'pagable_type' => Factura::class,
            'pagable_id' => $factura6->id,
            'monto_pago' => $factura6->total,
            'moneda' => 'mxn',
            'tipo_pago' => 'contado',
            'fecha_pago_programada' => now()->subDay(),
            'fecha_pago' => now()->subDay(),
            'estatus' => 'pagado',
        ]);
        $oc6->recalcularEstatus();

        // 7. cancelada
        $this->createOcConPartida([
            ...$base,
            'fecha_entrega_esperada' => $hoyMas7,
            'estatus' => 'cancelada',
            'referencia' => 'DEMO-CANCELADA',
        ], $rubro);
    }

    private function createOcConPartida(array $attrs, ObraRubro $rubro): OrdenCompra
    {
        $oc = OrdenCompra::create($attrs);
        OrdenCompraDetalle::create([
            'orden_compra_id' => $oc->id,
            'obra_rubro_id' => $rubro->id,
            'descripcion' => 'Material demo para '.$attrs['referencia'],
            'unidad' => 'pza',
            'cantidad' => 10,
            'precio_unitario' => 500,
            'subtotal' => 5000,
        ]);

        return $oc->fresh('detalles');
    }

    private function registrarEntregaCompleta(OrdenCompra $oc, Usuario $user): void
    {
        $entrega = Entrega::create([
            'orden_compra_id' => $oc->id,
            'recibido_por' => $user->id,
            'fecha_entrega' => now()->format('Y-m-d'),
            'tipo' => 'completa',
        ]);
        foreach ($oc->detalles as $d) {
            EntregaDetalle::create([
                'entrega_id' => $entrega->id,
                'orden_compra_detalle_id' => $d->id,
                'cantidad_recibida' => $d->cantidad,
            ]);
        }
    }

    private function crearFactura(OrdenCompra $oc, FacturaEstatus $estatus): Factura
    {
        $factura = Factura::create([
            'orden_compra_id' => $oc->id,
            'proveedor_id' => $oc->proveedor_id,
            'subtotal' => $oc->total,
            'iva' => round($oc->total * 0.16, 2),
            'iva_trasladado' => round($oc->total * 0.16, 2),
            'iva_retenido' => 0,
            'isr_retenido' => 0,
            'total' => round($oc->total * 1.16, 2),
            'moneda' => $oc->moneda,
            'fecha_factura' => now()->format('Y-m-d'),
            'estatus' => $estatus,
        ]);
        foreach ($oc->detalles as $d) {
            FacturaDetalle::create([
                'factura_id' => $factura->id,
                'orden_compra_detalle_id' => $d->id,
                'cantidad' => $d->cantidad,
                'precio_unitario' => $d->precio_unitario,
                'subtotal' => $d->subtotal,
            ]);
        }

        return $factura->fresh();
    }
}
