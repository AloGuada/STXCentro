<?php

namespace Database\Seeders;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Enums\Costos\PresupuestoEstatus;
use App\Models\Alm\Almacen;
use App\Models\Costos\Entrega;
use App\Models\Costos\Factura;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\OrdenCompraDetalle;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Producto;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Alm\RegistradorEntradaAlmacen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ejemplos locales de entrada de almacén contra factura, con la cadena
 * completa: proveedor, presupuesto, orden de compra, factura, recepción y
 * kardex. Se apoya en {@see RegistradorEntradaAlmacen}, así que las
 * existencias, el costo promedio y los movimientos quedan como si se hubieran
 * capturado en pantalla.
 *
 *   php artisan db:seed --class=AlmEntradaDevSeeder
 *
 * Es idempotente: cada orden se identifica por su `referencia` (DEMO-ALM-n) y
 * el escenario que ya existe se salta. Solo para desarrollo.
 */
class AlmEntradaDevSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::first();

        if ($usuario === null) {
            $this->command?->error('No hay usuarios: corre primero `php artisan db:seed`.');

            return;
        }

        $almacen = $this->almacen();

        if ($almacen === null) {
            $this->command?->error('No hay almacenes: corre primero `php artisan db:seed --class=AlmDevSeeder`.');

            return;
        }

        $proveedor = $this->proveedor();
        $departamento = Departamento::firstOrCreate(['descripcion' => 'Compras']);
        $productos = $this->productos();
        $registrador = app(RegistradorEntradaAlmacen::class);

        foreach ($this->escenarios($productos) as $escenario) {
            if (OrdenCompra::where('referencia', $escenario['referencia'])->exists()) {
                $this->command?->warn("· {$escenario['referencia']} ya existe, se omite.");

                continue;
            }

            // El centro de costos de la orden es lo que decide de quién será el
            // material al recibirlo, así que cada escenario trae el suyo.
            $obraRubro = $this->obraRubro($escenario['obra']);
            $destino = $this->almacenPorClave($escenario['almacen'] ?? null) ?? $almacen;

            DB::transaction(function () use ($escenario, $proveedor, $obraRubro, $departamento, $destino, $usuario, $registrador): void {
                $almacen = $destino;
                $orden = $this->orden($escenario, $proveedor, $obraRubro, $departamento, $usuario);
                $factura = $this->factura($orden, $escenario);

                foreach ($escenario['recepciones'] as $recepcion) {
                    $entrega = $this->recepcion($orden, $factura, $almacen, $usuario, $recepcion);

                    // El kardex hasta el final, igual que en el controlador: la
                    // recepción ya tiene sus renglones cuando se aplica.
                    $registrador->aplicar($entrega->load('detalles'), $usuario->getAuthIdentifier());
                }

                if ($escenario['cierra_factura']) {
                    $factura->update(['completamente_entregada' => true]);
                    $factura->intentarPasarAAprobacion();
                }

                $this->command?->info(sprintf(
                    '· %s · OC %s · factura %s (%s) · %d recepción(es) · %s → %s',
                    $escenario['titulo'],
                    $orden->folio,
                    $factura->folio,
                    $factura->fresh()->estatus->value,
                    count($escenario['recepciones']),
                    $escenario['obra'],
                    $almacen->clave,
                ));
            });
        }

        $this->command?->info('Listo. Entradas en /admin/almacen/entradas · kardex en /admin/almacen/kardex');
    }

    /** El almacén central; si no está, el primero que haya. */
    private function almacen(): ?Almacen
    {
        return Almacen::query()->whereNull('obra_id')->where('clave', 'AG')->first()
            ?? Almacen::query()->first();
    }

    /**
     * El almacén de planta con esa clave. Devuelve `null` —y el llamador cae al
     * de siempre— cuando el escenario no pide uno o la base no lo tiene: el
     * catálogo lo siembra otro seeder y no todos los locales corren el mismo.
     */
    private function almacenPorClave(?string $clave): ?Almacen
    {
        if ($clave === null) {
            return null;
        }

        return Almacen::query()->whereNull('obra_id')->where('clave', $clave)->first();
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::firstOrCreate(
            ['rfc' => 'APB080214QK3'],
            [
                'codigo' => 'PRV-DEMO-01',
                'razon_social' => 'Aceros y Perfiles del Bajío SA de CV',
                'nombre_comercial' => 'Aceros del Bajío',
                'email' => 'ventas@acerosdelbajio.test',
                'telefono' => '4771234567',
                'tipo_persona' => 'moral',
                'tipo_proveedor' => 'proveedor',
                'maneja_credito' => true,
                'dias_credito_default' => 30,
                'activo' => true,
            ],
        );
    }

    /**
     * Un centro de costos con presupuesto suficiente para las órdenes de esa
     * obra. Reutiliza las obras que ya siembra AlmDevSeeder cuando están.
     *
     * Cada obra necesita el suyo: el rubro es del catálogo y se comparte, pero
     * `ObraRubro` es la intersección obra × rubro, y es de ahí de donde
     * {@see RegistradorEntradaAlmacen} deduce el dueño del material.
     */
    private function obraRubro(string $obraNo): ObraRubro
    {
        $obra = Obra::firstOrCreate(
            ['no' => $obraNo],
            [
                'descripcion' => $this->obrasConocidas()[$obraNo] ?? $obraNo,
                'estatus' => 'abierta',
                'presupuesto_total' => 12_000_000,
            ],
        );

        $presupuesto = Presupuesto::firstOrCreate(
            ['presupuestable_type' => Obra::class, 'presupuestable_id' => $obra->id],
            ['nombre_interno' => null, 'estatus' => PresupuestoEstatus::Activo],
        );

        $tipoRubro = TipoRubro::firstOrCreate(['descripcion' => 'Materiales']);

        $rubro = Rubro::firstOrCreate(
            ['codigo' => 'MAT-01'],
            [
                'descripcion' => 'Acero y ferretería',
                'tipo_rubro_id' => $tipoRubro->id,
                'ambito' => 'obra',
                'ocultar_en_reporte' => false,
            ],
        );

        return ObraRubro::firstOrCreate(
            ['presupuesto_id' => $presupuesto->id, 'rubro_id' => $rubro->id],
            ['obra_id' => $obra->id, 'presupuestado' => 1_500_000, 'acumulado' => 0, 'apartado' => 0],
        );
    }

    /**
     * Nombre de las obras que este seeder puede tener que dar de alta. Una obra
     * que ya exista se reutiliza por `no` y su descripción no se toca.
     *
     * @return array<string, string>
     */
    private function obrasConocidas(): array
    {
        return [
            'T4' => 'Torre 4 Corporativo',
            'MBP' => 'Mega BodegaParque Industrial',
            'sp260501' => 'Suministro',
        ];
    }

    /**
     * Artículos del catálogo. La maniobra va sin inventario a propósito: se
     * recibe y se factura, pero no mueve kardex — es el caso que la pantalla de
     * la entrada marca como "no mueve existencia".
     *
     * @return array<string, Producto>
     */
    private function productos(): array
    {
        $catalogo = [
            'varilla' => ['ART-000101', 'Varilla corrugada 3/8 x 12 m', 'pza', true],
            'alambre' => ['ART-000102', 'Alambre recocido cal. 16', 'kg', true],
            'placa' => ['ART-000103', 'Placa de acero A36 1/4 x 1.22 x 2.44 m', 'pza', true],
            'tornillo' => ['ART-000104', 'Tornillo estructural A325 3/4 x 2', 'pza', true],
            'maniobra' => ['ART-000105', 'Maniobra de descarga con grúa', 'serv', false],
            'solera' => ['ART-000106', 'Solera de acero 1/2 x 2 pulg', 'pza', true],
            // Los que ya trae la carga inicial del almacen. Se piden por su
            // codigo real para que el material con dueno caiga sobre la misma
            // existencia que el que ya estaba libre: es lo que hace que el
            // desglose de la pantalla tenga algo que desglosar.
            'tornillo_ci' => ['ART-00001', 'Tornillo A325 3/4" x 2"', 'PZA', true],
            'electrodo_ci' => ['ART-00002', 'Electrodo 7018 1/8"', 'KG', true],
            'disco_ci' => ['ART-00006', 'Disco de corte 4 1/2"', 'PZA', true],
            'guante_ci' => ['ART-00008', 'Guante de carnaza', 'PAR', true],
        ];

        $productos = [];

        foreach ($catalogo as $clave => [$codigo, $descripcion, $unidad, $inventario]) {
            $productos[$clave] = Producto::firstOrCreate(
                ['codigo' => $codigo],
                [
                    'descripcion' => $descripcion,
                    'unidad' => $unidad,
                    'controla_inventario' => $inventario,
                    'activo' => true,
                ],
            );
        }

        return $productos;
    }

    /**
     * Los tres ejemplos. Cada renglón de recepción trae su cantidad y, cuando
     * el proveedor surtió a otro precio, el `precio` que de verdad se recibió.
     *
     * @param  array<string, Producto>  $p
     * @return list<array<string, mixed>>
     */
    private function escenarios(array $p): array
    {
        return [
            [
                'referencia' => 'DEMO-ALM-1',
                'obra' => 'T4',
                'titulo' => 'Recepción completa que cierra la factura',
                'notas' => 'Llega todo de una vez y la recepción cierra la factura.',
                'cierra_factura' => true,
                'con_comprobante' => true,
                'partidas' => [
                    ['producto' => $p['varilla'], 'cantidad' => 200, 'precio' => 118.50],
                    ['producto' => $p['alambre'], 'cantidad' => 50, 'precio' => 32.90],
                    ['producto' => $p['maniobra'], 'cantidad' => 1, 'precio' => 850.00],
                ],
                'recepciones' => [
                    [
                        'dias' => -6,
                        'tipo' => 'completa',
                        'liga_factura' => true,
                        'completa_factura' => true,
                        'observaciones' => 'Entró completo, material verificado contra la factura.',
                        'renglones' => [
                            ['cantidad' => 200],
                            ['cantidad' => 50],
                            ['cantidad' => 1],
                        ],
                    ],
                ],
            ],
            [
                'referencia' => 'DEMO-ALM-2',
                'obra' => 'T4',
                'titulo' => 'Recepción parcial: la factura sigue esperando',
                'notas' => 'El proveedor factura todo pero surte a medias.',
                'cierra_factura' => false,
                'con_comprobante' => false,
                'partidas' => [
                    ['producto' => $p['placa'], 'cantidad' => 20, 'precio' => 2_480.00],
                    ['producto' => $p['tornillo'], 'cantidad' => 500, 'precio' => 18.75],
                ],
                'recepciones' => [
                    [
                        'dias' => -3,
                        'tipo' => 'parcial',
                        'liga_factura' => true,
                        'completa_factura' => false,
                        'observaciones' => 'Solo llegó la mitad; el resto queda pendiente con el proveedor.',
                        'renglones' => [
                            ['cantidad' => 10],
                            ['cantidad' => 250],
                        ],
                    ],
                ],
            ],
            [
                'referencia' => 'DEMO-ALM-3',
                'obra' => 'T4',
                'titulo' => 'Dos recepciones y un precio distinto al de la orden',
                'notas' => 'La segunda entrega llega a otro precio y cierra la factura.',
                'cierra_factura' => true,
                'con_comprobante' => true,
                'partidas' => [
                    ['producto' => $p['solera'], 'cantidad' => 100, 'precio' => 45.00],
                ],
                'recepciones' => [
                    [
                        'dias' => -10,
                        'tipo' => 'parcial',
                        'liga_factura' => true,
                        'completa_factura' => false,
                        'observaciones' => 'Primer envío al precio de la orden.',
                        'renglones' => [
                            ['cantidad' => 60],
                        ],
                    ],
                    [
                        'dias' => -2,
                        'tipo' => 'parcial',
                        'liga_factura' => true,
                        'completa_factura' => true,
                        'observaciones' => 'Segundo envío: el proveedor subió el precio, se captura el real.',
                        'renglones' => [
                            ['cantidad' => 40, 'precio' => 47.25],
                        ],
                    ],
                ],
            ],
            [
                'referencia' => 'DEMO-ALM-4',
                'obra' => 'MBP',
                'almacen' => 'INS',
                'titulo' => 'Compra de MBP que cae sobre existencia libre',
                'notas' => 'Material comprado contra el presupuesto de MBP.',
                'cierra_factura' => true,
                'con_comprobante' => true,
                'partidas' => [
                    ['producto' => $p['tornillo_ci'], 'cantidad' => 3_000, 'precio' => 4.50],
                    ['producto' => $p['disco_ci'], 'cantidad' => 120, 'precio' => 28.00],
                ],
                'recepciones' => [
                    [
                        'dias' => -9,
                        'tipo' => 'completa',
                        'liga_factura' => true,
                        'completa_factura' => true,
                        'observaciones' => 'Entro completo al almacen de insumos.',
                        'renglones' => [
                            ['cantidad' => 3_000],
                            ['cantidad' => 120],
                        ],
                    ],
                ],
            ],
            [
                'referencia' => 'DEMO-ALM-5',
                'obra' => 'sp260501',
                'almacen' => 'INS',
                'titulo' => 'Tercera obra sobre el mismo articulo',
                'notas' => 'Suministro compra del mismo tornillo que MBP.',
                'cierra_factura' => false,
                'con_comprobante' => false,
                'partidas' => [
                    ['producto' => $p['tornillo_ci'], 'cantidad' => 2_000, 'precio' => 4.60],
                    ['producto' => $p['guante_ci'], 'cantidad' => 60, 'precio' => 52.00],
                ],
                'recepciones' => [
                    [
                        'dias' => -5,
                        'tipo' => 'parcial',
                        'liga_factura' => true,
                        'completa_factura' => false,
                        'observaciones' => 'Llego el tornillo y la mitad del guante.',
                        'renglones' => [
                            ['cantidad' => 1_500],
                            ['cantidad' => 30],
                        ],
                    ],
                ],
            ],
            [
                'referencia' => 'DEMO-ALM-6',
                'obra' => 'T4',
                'almacen' => 'INS',
                'titulo' => 'Cuarto dueno del mismo tornillo',
                'notas' => 'T4 tambien compro tornillo: tres obras y lo libre en la misma existencia.',
                'cierra_factura' => true,
                'con_comprobante' => true,
                'partidas' => [
                    ['producto' => $p['tornillo_ci'], 'cantidad' => 2_500, 'precio' => 4.40],
                    ['producto' => $p['electrodo_ci'], 'cantidad' => 180, 'precio' => 61.00],
                ],
                'recepciones' => [
                    [
                        'dias' => -4,
                        'tipo' => 'completa',
                        'liga_factura' => true,
                        'completa_factura' => true,
                        'observaciones' => 'Material de T4 resguardado en planta hasta que lo pidan.',
                        'renglones' => [
                            ['cantidad' => 2_500],
                            ['cantidad' => 180],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function orden(array $escenario, Proveedor $proveedor, ObraRubro $obraRubro, Departamento $departamento, User $usuario): OrdenCompra
    {
        $subtotal = collect($escenario['partidas'])
            ->sum(fn (array $partida): float => $partida['cantidad'] * $partida['precio']);

        $orden = OrdenCompra::create([
            'referencia' => $escenario['referencia'],
            'proveedor_id' => $proveedor->id,
            'departamento_id' => $departamento->id,
            'creado_por' => $usuario->getAuthIdentifier(),
            'moneda' => 'mxn',
            'tipo_cambio' => 1,
            'tipo_pago' => 'credito',
            'dias_credito' => 30,
            'total' => round($subtotal * (1 + (float) config('costos.iva_rate')), 2),
            'fecha_entrega_esperada' => now()->subDays(7)->format('Y-m-d'),
            'notas' => $escenario['notas'],
            'estatus' => 'pendiente_entrega',
        ]);

        foreach ($escenario['partidas'] as $partida) {
            /** @var Producto $producto */
            $producto = $partida['producto'];

            OrdenCompraDetalle::create([
                'orden_compra_id' => $orden->id,
                'obra_rubro_id' => $obraRubro->id,
                'producto_id' => $producto->id,
                'codigo_producto' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad' => $producto->unidad,
                'cantidad' => $partida['cantidad'],
                'precio_unitario' => $partida['precio'],
                'subtotal' => round($partida['cantidad'] * $partida['precio'], 2),
            ]);
        }

        return $orden->load('detalles');
    }

    /**
     * La factura del proveedor, tal como nace en el portal: pendiente de
     * recepción. El comprobante de recepción es el otro requisito para que
     * avance a aprobación, así que solo lo llevan los escenarios que cierran.
     *
     * @param  array<string, mixed>  $escenario
     */
    private function factura(OrdenCompra $orden, array $escenario): Factura
    {
        $subtotal = round((float) $orden->detalles->sum('subtotal'), 2);
        $iva = round($subtotal * (float) config('costos.iva_rate'), 2);

        $factura = Factura::create([
            'orden_compra_id' => $orden->id,
            'proveedor_id' => $orden->proveedor_id,
            'uuid_fiscal' => (string) Str::uuid(),
            'folio_fiscal' => 'A-'.fake()->numberBetween(1000, 9999),
            'subtotal' => $subtotal,
            'iva' => $iva,
            'iva_trasladado' => $iva,
            'total' => round($subtotal + $iva, 2),
            'moneda' => 'mxn',
            'tipo_cambio' => 1,
            'metodo_pago' => 'PPD',
            'forma_pago' => '03',
            'fecha_factura' => now()->subDays(8)->format('Y-m-d'),
            'estatus' => FacturaEstatus::PendienteRecepcion->value,
            'dias_credito' => 30,
        ]);

        if ($escenario['con_comprobante']) {
            $this->comprobanteDeRecepcion($factura);
        }

        return $factura;
    }

    /** Documento que el proveedor sube por el portal; sin él la factura no avanza. */
    private function comprobanteDeRecepcion(Factura $factura): void
    {
        $path = "costos/comprobantes/demo-{$factura->id}.pdf";

        $pdf = Pdf::loadHTML(
            '<h2>Comprobante de recepción (demo)</h2>'
            ."<p>Factura {$factura->folio} · sembrado por AlmEntradaDevSeeder.</p>",
        )->output();

        Storage::disk('public')->put($path, $pdf);

        $factura->media()->create([
            'descripcion' => DocumentoTipo::ComprobanteRecepcion->value,
            'nombre_original' => 'comprobante-recepcion.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => strlen($pdf),
        ]);
    }

    /**
     * La recepción, que es lo que ve Almacén: escribe en `costos_entregas` con
     * `almacen_id`, que es lo que hace que el kardex se mueva.
     *
     * @param  array<string, mixed>  $recepcion
     */
    private function recepcion(OrdenCompra $orden, Factura $factura, Almacen $almacen, User $usuario, array $recepcion): Entrega
    {
        $entrega = Entrega::create([
            'orden_compra_id' => $orden->id,
            'almacen_id' => $almacen->id,
            'factura_id' => $recepcion['liga_factura'] ? $factura->id : null,
            'recibido_por' => $usuario->getAuthIdentifier(),
            'fecha_entrega' => now()->addDays($recepcion['dias'])->format('Y-m-d'),
            'tipo' => $recepcion['tipo'],
            'completa_factura' => $recepcion['completa_factura'],
            'observaciones' => $recepcion['observaciones'],
        ]);

        $partidas = $orden->detalles->values();

        foreach ($recepcion['renglones'] as $indice => $renglon) {
            $partida = $partidas[$indice];

            $entrega->detalles()->create([
                'orden_compra_detalle_id' => $partida->id,
                // Se sella el artículo de la partida, igual que en la captura.
                'producto_id' => $partida->producto_id,
                'descripcion' => $partida->descripcion,
                'unidad' => $partida->unidad,
                'cantidad_recibida' => $renglon['cantidad'],
                'precio_unitario' => $renglon['precio'] ?? null,
            ]);
        }

        return $entrega;
    }
}
