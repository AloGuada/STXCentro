<?php

namespace Database\Seeders;

use App\Enums\Alm\AlmacenTipo;
use App\Models\Alm\Almacen;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba del catálogo de almacenes: los centrales de la planta más los
 * de montaje que viven dentro de una obra. Es idempotente (firstOrCreate sobre
 * la llave real obra+clave), así que se puede correr las veces que haga falta.
 */
class AlmDevSeeder extends Seeder
{
    public function run(): void
    {
        $responsable = User::first();
        $obras = $this->obras();

        foreach ($this->almacenes($obras) as $almacen) {
            Almacen::firstOrCreate(
                [
                    'obra_id' => $almacen['obra_id'],
                    'clave' => $almacen['clave'],
                ],
                [
                    ...$almacen,
                    'responsable_id' => $responsable?->getKey(),
                ],
            );
        }

        $this->command?->info('Almacenes: '.Almacen::count());
    }

    /**
     * Las obras a las que se les cuelga un almacén de montaje. Se reutilizan las
     * que ya existan por número de obra para no ensuciar la base con duplicados.
     *
     * @return array<string, \App\Models\Obra>
     */
    private function obras(): array
    {
        $obras = [
            'T4' => 'Torre 4 Corporativo',
            'MBP' => 'Mega BodegaParque Industrial',
        ];

        $creadas = [];

        foreach ($obras as $no => $descripcion) {
            $creadas[$no] = Obra::firstOrCreate(
                ['no' => $no],
                [
                    'descripcion' => $descripcion,
                    'estatus' => 'abierta',
                    'presupuesto_total' => fake()->randomFloat(2, 5_000_000, 40_000_000),
                ],
            );
        }

        return $creadas;
    }

    /**
     * @param  array<string, \App\Models\Obra>  $obras
     * @return list<array<string, mixed>>
     */
    private function almacenes(array $obras): array
    {
        return [
            [
                'clave' => 'AG',
                'nombre' => 'Almacén General',
                'obra_id' => null,
                'tipo' => AlmacenTipo::Insumos,
                'observaciones' => 'Surte a planta y a todas las obras.',
                'activo' => true,
            ],
            [
                'clave' => 'FAK',
                'nombre' => 'Almacén de Fabricación Nave K',
                'obra_id' => null,
                'tipo' => AlmacenTipo::Insumos,
                'observaciones' => 'Consumibles de soldadura y placa de la nave K.',
                'activo' => true,
            ],
            [
                'clave' => 'FAD',
                'nombre' => 'Almacén de Fabricación Nave D',
                'obra_id' => null,
                'tipo' => AlmacenTipo::Insumos,
                'observaciones' => null,
                'activo' => true,
            ],
            [
                'clave' => 'HER',
                'nombre' => 'Pañol de Herramienta',
                'obra_id' => null,
                'tipo' => AlmacenTipo::Herramienta,
                'observaciones' => 'Herramienta que se presta y regresa.',
                'activo' => true,
            ],
            [
                'clave' => 'CHA',
                'nombre' => 'Almacén de Chatarra',
                'obra_id' => null,
                'tipo' => AlmacenTipo::Insumos,
                'observaciones' => 'Dado de baja: se consolidó en el general.',
                'activo' => false,
            ],
            [
                'clave' => 'A',
                'nombre' => 'Almacén de Montaje T4',
                'obra_id' => $obras['T4']->id,
                'tipo' => AlmacenTipo::Montaje,
                'observaciones' => 'Contenedor a pie de obra.',
                'activo' => true,
            ],
            [
                'clave' => 'E',
                'nombre' => 'Almacén de Estructura T4',
                'obra_id' => $obras['T4']->id,
                'tipo' => AlmacenTipo::Montaje,
                'observaciones' => null,
                'activo' => true,
            ],
            [
                'clave' => 'A',
                'nombre' => 'Almacén de Montaje MBP',
                'obra_id' => $obras['MBP']->id,
                'tipo' => AlmacenTipo::Montaje,
                'observaciones' => 'Misma clave que el de T4: la clave es única por obra, no global.',
                'activo' => true,
            ],
            [
                'clave' => 'HER',
                'nombre' => 'Herramienta MBP',
                'obra_id' => $obras['MBP']->id,
                'tipo' => AlmacenTipo::Herramienta,
                'observaciones' => null,
                'activo' => true,
            ],
        ];
    }
}
