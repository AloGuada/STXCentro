<?php

namespace Database\Seeders;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\AlmacenTipo;
use App\Enums\Alm\MovimientoTipo;
use App\Enums\Alm\PedidoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Alm\Pedido;
use App\Models\Alm\Salida;
use App\Models\Alm\Transferencia;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\User;
use App\Services\Alm\ReasignacionMaterial;
use App\Services\Alm\RegistradorAjuste;
use App\Services\Alm\RegistradorPiezas;
use App\Services\Alm\RegistradorSalida;
use App\Services\Alm\RegistradorTransferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Ejemplos locales de todo lo que pasa **después** de la entrada: reparto entre
 * obras, pedidos, salidas, transferencias, conteo y piezas con serie.
 *
 *   php artisan db:seed --class=AlmMovimientosDevSeeder
 *
 * Da por hecho que ya hay existencia. El orden natural es AlmDevSeeder (el
 * catálogo de almacenes), AlmEntradaDevSeeder (las entradas contra orden de
 * compra) y por último éste.
 *
 * Todo pasa por los servicios del módulo —{@see RegistradorSalida},
 * {@see RegistradorTransferencia}, {@see RegistradorAjuste},
 * {@see RegistradorPiezas} y {@see ReasignacionMaterial}—, nunca por inserts a
 * mano: el kardex, el costo promedio y las asignaciones tienen que quedar como
 * si se hubieran capturado en pantalla. Un insert directo dejaría documentos
 * bonitos y un saldo que no cuadra.
 *
 * Nada trae cantidades fijas. Cada bloque se dimensiona a lo que de verdad hay
 * libre en ese momento, porque el material con dueño no se puede tomar sin
 * permiso y cuánto queda suelto depende de qué entradas se corrieron antes: en
 * un local con carga inicial sobra, y en una base recién migrada la primera
 * compra deja todo comprometido.
 *
 * Es idempotente por bloque: cada documento lleva {@see self::MARCA} en sus
 * observaciones y el bloque que ya sembró se salta. Solo para desarrollo.
 */
class AlmMovimientosDevSeeder extends Seeder
{
    /**
     * Sello que identifica lo que sembró este seeder. Va en las observaciones
     * porque ninguno de estos documentos tiene una columna de referencia libre
     * donde apoyar la idempotencia, como sí la tiene la orden de compra.
     */
    private const MARCA = '[demo-alm]';

    /**
     * Sello propio del reparto de relleno. Va aparte y **sin** los corchetes de
     * {@see self::MARCA} dentro para que la guarda de {@see self::reparto()},
     * que busca el sello general, no confunda un bloque con el otro.
     */
    private const MARCA_ASIGNACIONES = '[demo-alm-asignaciones]';

    /**
     * Cuántos renglones se reparten **por almacén**. Con más, la pantalla deja de
     * tener casos sueltos, y lo que no tiene dueño también hay que poder verlo.
     */
    private const RENGLONES_REPARTIDOS = 12;

    /**
     * Menos que esto no se reparte: en tramos de cero o de uno el desglose no
     * enseña nada y sólo ensucia el kardex.
     */
    private const MINIMO_REPARTIBLE = 12.0;

    /** El usuario al que se le atribuyen todos los movimientos sembrados. */
    private ?string $usuarioId = null;

    /**
     * Los almacenes sobre los que se siembra. Se resuelven por el surtido que
     * tienen y no por clave: las claves de planta las da de alta cada local a
     * mano, y un seeder que exija «INS» se salta todos sus bloques en silencio
     * donde no exista. El catálogo mínimo de AlmDevSeeder alcanza.
     */
    private ?Almacen $principal = null;

    private ?Almacen $destino = null;

    private ?Almacen $herramienta = null;

    public function run(): void
    {
        $usuario = User::first();

        if ($usuario === null) {
            $this->command?->error('No hay usuarios: corre primero db:seed.');

            return;
        }

        $this->usuarioId = $usuario->getAuthIdentifier();
        $this->principal = $this->almacenMasSurtido();

        if ($this->principal === null) {
            $this->command?->error('No hay existencias: corre antes AlmEntradaDevSeeder.');

            return;
        }

        $this->destino = $this->otroAlmacenCentral($this->principal);
        $this->herramienta = $this->almacenDeHerramienta() ?? $this->principal;

        $this->command?->info(sprintf(
            'Sembrando sobre %s%s',
            $this->principal->clave,
            $this->destino === null
                ? ' · sin segundo almacén: no habrá transferencias'
                : ' → '.$this->destino->clave,
        ));

        // El reparto va primero a propósito: suelta material a lo libre, y de
        // ahí comen los bloques que siguen.
        $this->reparto();
        $this->pedidos();
        $this->salidas();
        $this->transferencias();
        $this->conteo();
        $this->piezas();
        // Al final: reparte lo que haya quedado suelto, sin quitarle material a
        // los bloques de arriba, que sí necesitan sacar de lo libre.
        $this->asignacionesDeEjemplo();

        $this->command?->info('Listo. Existencias en /admin/almacen/existencias · kardex en /admin/almacen/kardex');
    }

    /**
     * Reparte material de una obra a otra y suelta un tramo a lo libre.
     *
     * Es el caso que más cuesta creer viendo sólo la pantalla: la reasignación
     * mueve de quién es el material sin mover el gasto, así que el saldo total
     * del artículo no cambia y el kardex gana dos asientos que se anulan.
     */
    private function reparto(): void
    {
        // La reasignación no deja documento propio —sólo dos asientos en el
        // kardex—, así que la guarda va contra el movimiento y no contra una
        // tabla de cabeceras. El motivo viaja a las observaciones del asiento.
        $yaRepartido = Movimiento::query()
            ->where('tipo', MovimientoTipo::Reasignacion)
            ->where('observaciones', 'like', '%'.self::MARCA.'%')
            ->exists();

        if ($yaRepartido) {
            $this->command?->warn('· Reparto: ya sembrado, se omite.');

            return;
        }

        $existencia = $this->existenciaConDueno();

        if ($existencia === null) {
            $this->command?->warn('· Reparto: no hay material con dueño todavía, se omite.');

            return;
        }

        $asignaciones = $existencia->asignaciones()->vivas()->orderByDesc('cantidad')->get();
        $origen = $asignaciones->first();
        $disponible = (float) $origen->cantidad;

        if ($disponible < 10) {
            $this->command?->warn('· Reparto: la asignación es muy chica para partirla, se omite.');

            return;
        }

        // En tercios: a la obra origen le queda material suficiente para que la
        // salida que viene después tenga de dónde salir.
        $tramo = floor($disponible / 3);
        $destino = $asignaciones->skip(1)->first();
        $servicio = app(ReasignacionMaterial::class);

        $servicio->reasignar(
            existencia: $existencia,
            deObraId: (int) $origen->obra_id,
            aObraId: $destino === null ? null : (int) $destino->obra_id,
            cantidad: $tramo,
            motivo: 'Prestamo entre obras mientras llega su compra '.self::MARCA,
            userId: $this->usuarioId,
        );

        // El segundo tramo se suelta a lo libre: así la pantalla muestra las dos
        // direcciones del reparto, no sólo obra a obra.
        $servicio->reasignar(
            existencia: $existencia->refresh(),
            deObraId: (int) $origen->obra_id,
            aObraId: null,
            cantidad: $tramo,
            motivo: 'Sobrante que se libera al almacen '.self::MARCA,
            userId: $this->usuarioId,
        );

        $this->command?->info(sprintf(
            '· Reparto: %s unidades repartidas sobre %s',
            $tramo * 2,
            $existencia->producto?->codigo ?? $existencia->producto_id,
        ));
    }

    /**
     * Pedidos en los estatus que se ven en el listado. El aprobado queda vivo a
     * propósito: es el que la pantalla de salida ofrece para surtir.
     */
    private function pedidos(): void
    {
        if ($this->yaSembrado(Pedido::query())) {
            $this->command?->warn('· Pedidos: ya sembrados, se omiten.');

            return;
        }

        $articulos = $this->conSaldo($this->principal);

        if ($articulos->count() < 2) {
            $this->command?->warn('· Pedidos: el almacén no tiene surtido suficiente, se omiten.');

            return;
        }

        $obra = Obra::where('no', 'MBP')->first() ?? Obra::first();
        $departamento = Departamento::where('descripcion', 'Montaje')->first() ?? Departamento::first();

        $escenarios = [
            ['estatus' => PedidoEstatus::Borrador, 'dias' => -1, 'motivo' => 'Consumible para la semana'],
            ['estatus' => PedidoEstatus::Pendiente, 'dias' => -2, 'motivo' => 'Material para el frente 3'],
            ['estatus' => PedidoEstatus::Aprobado, 'dias' => -3, 'motivo' => 'Pedido listo para surtir'],
            ['estatus' => PedidoEstatus::Rechazado, 'dias' => -6, 'motivo' => 'Pedido fuera de presupuesto'],
        ];

        foreach ($escenarios as $escenario) {
            $aprobado = $escenario['estatus'] === PedidoEstatus::Aprobado;

            $pedido = Pedido::create([
                'almacen_id' => $this->principal->id,
                'departamento_id' => $departamento?->id,
                'obra_id' => $obra?->id,
                'solicitante_id' => $this->usuarioId,
                'recibe_nombre' => 'Cuadrilla de montaje',
                'fecha' => now()->addDays($escenario['dias'])->toDateString(),
                'fecha_requerida' => now()->addDays($escenario['dias'] + 7)->toDateString(),
                'motivo' => $escenario['motivo'],
                'estatus' => $escenario['estatus'],
                'aprobado_por' => $aprobado ? $this->usuarioId : null,
                'aprobado_at' => $aprobado ? now()->addDays($escenario['dias']) : null,
                'motivo_rechazo' => $escenario['estatus'] === PedidoEstatus::Rechazado
                    ? 'El centro de costos no tiene disponible.'
                    : null,
                'observaciones' => self::MARCA,
            ]);

            foreach ($articulos->take(2)->values() as $indice => $existencia) {
                $pedido->detalles()->create([
                    'producto_id' => $existencia->producto_id,
                    'cantidad_solicitada' => $indice === 0 ? 40 : 10,
                    'cantidad_surtida' => 0,
                ]);
            }
        }

        $this->command?->info('· Pedidos: '.count($escenarios).' sembrados (borrador, pendiente, aprobado, rechazado)');
    }

    /**
     * Tres salidas: una que consume material de su propia obra, una de consumo
     * interno que tira de lo libre, y una cancelada para que el kardex tenga un
     * reverso que explicar.
     */
    private function salidas(): void
    {
        if ($this->yaSembrado(Salida::query())) {
            $this->command?->warn('· Salidas: ya sembradas, se omiten.');

            return;
        }

        $servicio = app(RegistradorSalida::class);
        $departamento = Departamento::where('descripcion', 'Montaje')->first() ?? Departamento::first();
        $hechas = [];

        if (($asignacion = $this->asignacionMasGrande()) !== null) {
            // Sale contra su propia asignación, así que no necesita permiso para
            // tomar material ajeno. La mitad, para que a la obra le quede.
            $servicio->registrar(
                cabecera: [
                    'almacen_id' => $asignacion->existencia->almacen_id,
                    'departamento_id' => $departamento?->id,
                    'obra_destino_id' => (int) $asignacion->obra_id,
                    'solicitante_id' => $this->usuarioId,
                    'entregado_por' => $this->usuarioId,
                    'recibe_nombre' => 'Residente de la obra',
                    'fecha' => now()->subDays(2)->toDateString(),
                    'motivo' => 'Envio a obra de material propio',
                    'observaciones' => 'Sale contra la asignacion de la obra '.self::MARCA,
                ],
                renglones: [[
                    'producto_id' => $asignacion->existencia->producto_id,
                    'cantidad' => max(1, floor((float) $asignacion->cantidad / 2)),
                ]],
                userId: $this->usuarioId,
            );

            $hechas[] = 'propia';
        }

        if (($libre = $this->masLibre(20)) !== null) {
            [$existencia, $disponible] = $libre;

            $servicio->registrar(
                cabecera: [
                    'almacen_id' => $existencia->almacen_id,
                    'departamento_id' => $departamento?->id,
                    'obra_destino_id' => null,
                    'solicitante_id' => $this->usuarioId,
                    'entregado_por' => $this->usuarioId,
                    'recibe_nombre' => 'Taller de planta',
                    'fecha' => now()->subDay()->toDateString(),
                    'motivo' => 'Consumo interno de planta',
                    'observaciones' => 'Sale de lo libre, sin obra destino '.self::MARCA,
                ],
                renglones: [[
                    'producto_id' => $existencia->producto_id,
                    'cantidad' => max(1, floor(min(12, $disponible / 2))),
                ]],
                userId: $this->usuarioId,
            );

            $hechas[] = 'de lo libre';
        }

        if (($libre = $this->masLibre(10)) !== null) {
            [$existencia, $disponible] = $libre;

            $equivocada = $servicio->registrar(
                cabecera: [
                    'almacen_id' => $existencia->almacen_id,
                    'departamento_id' => $departamento?->id,
                    'obra_destino_id' => null,
                    'solicitante_id' => $this->usuarioId,
                    'entregado_por' => $this->usuarioId,
                    'recibe_nombre' => 'Capturado por error',
                    'fecha' => now()->subDay()->toDateString(),
                    'motivo' => 'Salida capturada por error',
                    'observaciones' => 'Se cancela para dejar el reverso en el kardex '.self::MARCA,
                ],
                renglones: [[
                    'producto_id' => $existencia->producto_id,
                    'cantidad' => max(1, floor(min(8, $disponible / 2))),
                ]],
                userId: $this->usuarioId,
            );

            $servicio->cancelar($equivocada, 'Se capturo en el almacen equivocado', $this->usuarioId);

            $hechas[] = 'una cancelada con su reverso';
        }

        $this->command?->info($hechas === []
            ? '· Salidas: no hay material disponible, se omiten.'
            : '· Salidas: '.implode(', ', $hechas));
    }

    /**
     * Dos transferencias: una que sigue en el camión y otra que llegó con
     * faltante. El faltante es el caso interesante: el saldo total baja y esa
     * diferencia tiene dueño, no se corrige sola.
     *
     * Sólo mueven material libre. La transferencia no pregunta de quién es lo
     * que sube al camión, así que mandar material asignado dejaría a una obra
     * con su saldo en otro almacén: correcto para el ledger, confuso como
     * ejemplo.
     */
    private function transferencias(): void
    {
        if ($this->destino === null) {
            $this->command?->warn('· Transferencias: no hay un segundo almacén central, se omiten.');

            return;
        }

        if ($this->yaSembrado(Transferencia::query())) {
            $this->command?->warn('· Transferencias: ya sembradas, se omiten.');

            return;
        }

        $servicio = app(RegistradorTransferencia::class);

        $cabecera = fn (int $dias, string $nota): array => [
            'almacen_origen_id' => $this->principal->id,
            'almacen_destino_id' => $this->destino->id,
            'autorizado_por' => $this->usuarioId,
            'fecha_envio' => now()->subDays($dias)->toDateString(),
            'enviado_por' => $this->usuarioId,
            'observaciones' => $nota.' '.self::MARCA,
        ];

        $enviadas = 0;

        if (($libre = $this->masLibre(20)) !== null) {
            [$existencia, $disponible] = $libre;

            $servicio->enviar(
                cabecera: $cabecera(1, 'Va en el camion, el destino todavia no confirma'),
                renglones: [[
                    'producto_id' => $existencia->producto_id,
                    'cantidad_enviada' => max(1, floor(min(200, $disponible / 2))),
                ]],
                userId: $this->usuarioId,
            );

            $enviadas++;
        }

        if (($libre = $this->masLibre(20)) !== null) {
            [$existencia, $disponible] = $libre;
            $cantidad = max(2, floor(min(20, $disponible / 2)));

            $conFaltante = $servicio->enviar(
                cabecera: $cabecera(4, 'Llego con faltante y tiene responsable'),
                renglones: [[
                    'producto_id' => $existencia->producto_id,
                    'cantidad_enviada' => $cantidad,
                ]],
                userId: $this->usuarioId,
            );

            // Baja menos de lo que subió: la diferencia no genera un tercer
            // movimiento, sólo queda registrada con su responsable.
            $servicio->recibir(
                transferencia: $conFaltante->load('detalles'),
                confirmado: [$conFaltante->detalles->first()->id => $cantidad - 1],
                faltanteResponsableId: $this->usuarioId,
                userId: $this->usuarioId,
            );

            $enviadas++;
        }

        $this->command?->info($enviadas === 0
            ? '· Transferencias: no hay material libre que mover, se omiten.'
            : '· Transferencias: una en tránsito y otra recibida con faltante');
    }

    /**
     * Un conteo físico con las tres formas de terminar: sobrante, faltante y
     * exacto. El renglón exacto queda grabado pero no mueve el kardex, que es
     * justo lo que hay que poder ver.
     */
    private function conteo(): void
    {
        if ($this->yaSembrado(Ajuste::query())) {
            $this->command?->warn('· Conteo: ya sembrado, se omite.');

            return;
        }

        $articulos = $this->conSaldo($this->principal);
        $faltante = $this->masLibre(10);

        if ($articulos->count() < 3 || $faltante === null) {
            $this->command?->warn('· Conteo: el almacén no tiene surtido suficiente, se omite.');

            return;
        }

        // El faltante sale de lo libre: descontarlo de una existencia que está
        // toda comprometida dejaría a las obras con más asignado que saldo. El
        // ajuste es el único documento al que el ledger le permite hacerlo, así
        // que aquí no hay nada que avise del error.
        [$menos, $libreDelMenos] = $faltante;
        $otros = $articulos->reject(fn (Existencia $e): bool => $e->is($menos))->values();

        $renglones = [
            [
                'producto_id' => $otros[0]->producto_id,
                'cantidad_contada' => (float) $otros[0]->cantidad + 15,
                'costo_unitario' => (float) $otros[0]->costo_promedio,
                'observaciones' => 'Aparecieron piezas que estaban en otro anaquel.',
            ],
            [
                'producto_id' => $menos->producto_id,
                'cantidad_contada' => (float) $menos->cantidad - max(1, floor(min(7, $libreDelMenos))),
                'costo_unitario' => null,
                'observaciones' => 'Faltante sin explicacion, se ajusta al conteo.',
            ],
            [
                'producto_id' => $otros[1]->producto_id,
                'cantidad_contada' => (float) $otros[1]->cantidad,
                'costo_unitario' => null,
                'observaciones' => 'Conteo exacto: queda el renglon, no el movimiento.',
            ],
        ];

        app(RegistradorAjuste::class)->registrar(
            cabecera: [
                'almacen_id' => $this->principal->id,
                'motivo' => AjusteMotivo::ConteoFisico,
                'autorizado_por' => $this->usuarioId,
                'fecha' => now()->toDateString(),
                'observaciones' => 'Conteo ciclico del anaquel A '.self::MARCA,
            ],
            renglones: $renglones,
            userId: $this->usuarioId,
        );

        $this->command?->info('· Conteo: un ajuste con sobrante, faltante y renglón exacto');
    }

    /**
     * Herramienta con número de serie. La marca, el modelo y la serie son de la
     * pieza y no del artículo: el catálogo dice "pulidora", la pieza dice cuál.
     */
    private function piezas(): void
    {
        // Datos de prueba: se dan de alta las dos mitades, como lo hace la
        // pantalla de artículos.
        $producto = Producto::firstOrCreate(
            ['codigo' => 'ART-000201'],
            [
                'descripcion' => 'Pulidora angular 4 1/2 pulg',
                'unidad' => 'PZA',
                'activo' => true,
            ],
        );

        $articulo = Articulo::firstOrCreate(
            ['producto_id' => $producto->id],
            [
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad' => $producto->unidad,
                'tipo' => \App\Enums\Alm\ProductoTipo::Activo,
                'se_controla_por_pieza' => true,
                'activo' => true,
            ],
        );

        $series = ['DW-2026-0001', 'DW-2026-0002', 'DW-2026-0003'];

        $faltantes = array_values(array_diff(
            $series,
            Activo::whereIn('no_serie', $series)->pluck('no_serie')->all(),
        ));

        if ($faltantes === []) {
            $this->command?->warn('· Piezas: ya sembradas, se omiten.');

            return;
        }

        app(RegistradorPiezas::class)->alta(
            articulo: $articulo,
            almacen: $this->herramienta,
            piezas: array_map(fn (string $serie): array => [
                'no_serie' => $serie,
                'marca' => 'DeWalt',
                'modelo' => 'DWE4011',
                'id_mantenimiento' => 'MTO-'.substr($serie, -4),
                'costo' => 1_850.00,
                'condicion' => 'Nueva',
                'observaciones' => self::MARCA,
            ], $faltantes),
            userId: $this->usuarioId,
        );

        $this->command?->info('· Piezas: '.count($faltantes).' pulidoras con serie en '.$this->herramienta->clave);
    }

    /**
     * Reparte entre obras el material que quedó suelto en bodega.
     *
     * {@see self::reparto()} explica el movimiento sobre un solo renglón: de una
     * obra a otra y de vuelta a lo libre. Éste no explica nada nuevo, llena. Sin
     * él casi todas las filas de la pantalla de existencias se ven sin desglose
     * sólo tiene dueño lo que entró contra una orden de compra y el reparto
     * por obra, que es lo que hay que revisar ahí, no se alcanza a ver.
     *
     * Siempre deja un tramo libre: un renglón comprometido al cien esconde la
     * mitad del caso, que es cuánto material queda para repartir.
     */
    private function asignacionesDeEjemplo(): void
    {
        $obras = Obra::query()->orderBy('no')->take(3)->get();

        if ($obras->count() < 2) {
            $this->command?->warn('· Asignaciones de ejemplo: hacen falta al menos dos obras, se omiten.');

            return;
        }

        // Rotar la lista hace que no siempre le toque a las mismas dos obras:
        // el filtro por obra de la pantalla necesita renglones de cada una.
        $rueda = array_merge($obras->all(), $obras->all());
        $servicio = app(ReasignacionMaterial::class);
        $repartidos = 0;

        // Todos los almacenes, no sólo el principal: el grueso de los renglones
        // suele venir de la carga inicial de una bodega pintura, soldadura y
        // ahí nadie tiene dueño, que es justo donde la pantalla se veía vacía.
        foreach ($this->almacenesConSaldo() as $almacen) {
            // La guarda es por almacén y no global, así el bloque completa lo que
            // falte cuando una bodega estrena carga sin resembrar las de antes.
            $yaRepartido = Movimiento::query()
                ->where('almacen_id', $almacen->id)
                ->where('tipo', MovimientoTipo::Reasignacion)
                ->where('observaciones', 'like', '%'.self::MARCA_ASIGNACIONES.'%')
                ->exists();

            if ($yaRepartido) {
                continue;
            }

            $enEsteAlmacen = 0;

            foreach ($this->conSaldo($almacen) as $existencia) {
                if ($enEsteAlmacen >= self::RENGLONES_REPARTIDOS) {
                    break;
                }

                $libre = (float) $existencia->cantidad
                    - (float) $existencia->asignaciones()->vivas()->sum('cantidad');

                if ($libre < self::MINIMO_REPARTIBLE) {
                    continue;
                }

                // Dos o tres obras según el renglón, y un tramo de más que se
                // queda libre: el desglose muestra las dos cosas a la vez.
                $cuantas = $repartidos % 2 === 0 ? 2 : min(3, $obras->count());
                $tramo = floor($libre / ($cuantas + 1));

                foreach (array_slice($rueda, $repartidos % $obras->count(), $cuantas) as $obra) {
                    $servicio->reasignar(
                        existencia: $existencia,
                        deObraId: null,
                        aObraId: (int) $obra->id,
                        cantidad: $tramo,
                        motivo: 'Material de bodega comprometido con la obra '.self::MARCA_ASIGNACIONES,
                        userId: $this->usuarioId,
                    );
                }

                $enEsteAlmacen++;
                $repartidos++;
            }
        }

        $this->command?->info($repartidos === 0
            ? '· Asignaciones de ejemplo: ya sembradas o sin material suelto que repartir.'
            : '· Asignaciones de ejemplo: '.$repartidos.' renglones repartidos entre obras.');
    }

    /**
     * Los almacenes que tienen algo encima. El orden por clave sólo hace que el
     * reparto salga igual en dos bases con el mismo catálogo.
     *
     * @return Collection<int, Almacen>
     */
    private function almacenesConSaldo(): Collection
    {
        return Almacen::query()
            ->whereHas('existencias', fn (Builder $query) => $query->where('cantidad', '>', 0))
            ->orderBy('clave')
            ->get();
    }

    /**
     * El almacén central con más material. Es donde cayeron las entradas, así
     * que es el único sobre el que tiene sentido sembrar salidas y conteos.
     */
    private function almacenMasSurtido(): ?Almacen
    {
        $almacenId = Existencia::query()
            ->whereHas('almacen', fn (Builder $query) => $query->whereNull('obra_id'))
            ->where('cantidad', '>', 0)
            ->groupBy('almacen_id')
            ->orderByRaw('sum(cantidad) desc')
            ->value('almacen_id');

        return $almacenId === null ? null : Almacen::find($almacenId);
    }

    /** Cualquier otro almacén central: es el destino de las transferencias. */
    private function otroAlmacenCentral(Almacen $principal): ?Almacen
    {
        return Almacen::query()
            ->whereNull('obra_id')
            ->whereKeyNot($principal->getKey())
            ->orderByDesc('activo')
            ->first();
    }

    private function almacenDeHerramienta(): ?Almacen
    {
        return Almacen::query()
            ->whereNull('obra_id')
            ->where('tipo', AlmacenTipo::Herramienta)
            ->orderByDesc('activo')
            ->first();
    }

    /**
     * Lo que ese almacén tiene con saldo, de mayor a menor.
     *
     * @return Collection<int, Existencia>
     */
    private function conSaldo(Almacen $almacen): Collection
    {
        return Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->where('cantidad', '>', 0)
            ->orderByDesc('cantidad')
            ->get();
    }

    /**
     * La existencia del almacén principal con más material sin comprometer,
     * siempre que llegue al mínimo pedido.
     *
     * Se recalcula en cada llamada porque el bloque anterior ya consumió: pedir
     * dos veces "lo más libre" sin releer devolvería la foto vieja y la segunda
     * salida se pasaría del saldo.
     *
     * @return array{0: Existencia, 1: float}|null la existencia y cuánto tiene libre
     */
    private function masLibre(float $minimo): ?array
    {
        $mejor = null;

        foreach ($this->conSaldo($this->principal) as $existencia) {
            $libre = (float) $existencia->cantidad
                - (float) $existencia->asignaciones()->vivas()->sum('cantidad');

            if ($libre >= $minimo && ($mejor === null || $libre > $mejor[1])) {
                $mejor = [$existencia, $libre];
            }
        }

        return $mejor;
    }

    /** La asignación viva más grande: la obra que más material tiene guardado. */
    private function asignacionMasGrande(): ?Asignacion
    {
        return Asignacion::query()
            ->with('existencia')
            ->vivas()
            ->orderByDesc('cantidad')
            ->first();
    }

    /** La existencia más surtida que ya tiene dueño: sobre ésa vale la pena repartir. */
    private function existenciaConDueno(): ?Existencia
    {
        return Existencia::query()
            ->whereHas('asignaciones', fn (Builder $query) => $query->vivas())
            ->orderByDesc('cantidad')
            ->first();
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function yaSembrado(Builder $query): bool
    {
        return $query->where('observaciones', 'like', '%'.self::MARCA.'%')->exists();
    }
}
