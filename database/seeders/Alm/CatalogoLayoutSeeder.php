<?php

namespace Database\Seeders\Alm;

use App\Enums\Alm\AlmacenTipo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Obra;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Lo que los layouts de carga dan por hecho: las áreas, las obras y los
 * almacenes a los que le van a cargar saldo. Va antes que cualquier
 * {@see CargaInicialSeeder}, que no inventa ninguno de los tres.
 *
 * Es idempotente —todo entra por `firstOrCreate`— porque los layouts llegan por
 * tandas y hay que poder correrlo otra vez sin duplicar el catálogo.
 *
 * **En planta = central.** Los layouts traen `OBRA=PLANTA` en almacenes que no
 * son de obra: es la misma casilla que la pantalla de alta llama "está en la
 * planta", y significa `obra_id` nulo. Sólo CONST es de obra de verdad, y por
 * eso se repite seis veces con la misma clave.
 */
class CatalogoLayoutSeeder extends Seeder
{
    /**
     * Las familias del insumo. Son las que traen pegadas los layouts en su hoja
     * de Catálogos, más `Tornillería`, que 261 renglones usan sin que estuviera
     * en la lista: se da de alta porque rechazarlos costaría más que aceptarla.
     *
     * @var list<string>
     */
    private const AREAS = [
        'Consumibles',
        'Estructura',
        'Gases',
        'Herramienta',
        'Limpieza',
        'Mantenimiento',
        'Papelería',
        'Pintura',
        'Plasma',
        'Seguridad',
        'Soldadura',
        'Tornillería',
    ];

    /**
     * Las obras con almacén propio en los layouts. `SIX PARKS CANCUN` viene
     * escrita de dos formas entre archivos; aquí queda una sola y la carga
     * normaliza contra ésta.
     *
     * Van las seis, MBP incluida. Estaba fuera por venir sembrada en
     * desarrollo, y en producción no existe: la carga se quedaba sin su almacén
     * y reventaba al llegarle el saldo. Que una obra ya exista no cuesta nada
     * —`firstOrCreate` la respeta— y que falte sí.
     *
     * @var array<string, string>
     */
    private const OBRAS = [
        'AMPLIACION T4' => 'Ampliación T4',
        'MBP' => 'Mega Bodega Parque Industrial',
        'PARKS NAVE A' => 'Parks Nave A',
        'SIX PARKS CANCUN' => 'Six Parks Cancún',
        'TERRAZA ERNESTO ROSADO' => 'Terraza Ernesto Rosado',
        'TRES GUERRAS' => 'Tres Guerras',
    ];

    /**
     * Los almacenes centrales que piden los layouts. Los que ya existen se
     * quedan como están: esto sólo abre los que faltan.
     *
     * @var array<string, array{nombre: string, tipo: AlmacenTipo}>
     */
    private const CENTRALES = [
        'CONS' => ['nombre' => 'Almacén de Construcción', 'tipo' => AlmacenTipo::Insumos],
        'INS' => ['nombre' => 'Almacén de Insumos', 'tipo' => AlmacenTipo::Insumos],
        'MON' => ['nombre' => 'Almacén de Montaje', 'tipo' => AlmacenTipo::Montaje],
        'MTO' => ['nombre' => 'Almacén de Mantenimiento', 'tipo' => AlmacenTipo::Herramienta],
        'PROD' => ['nombre' => 'Almacén de Producción', 'tipo' => AlmacenTipo::Herramienta],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $descripcion) {
            Area::firstOrCreate(['descripcion' => $descripcion], ['activo' => true]);
        }

        foreach (self::OBRAS as $no => $descripcion) {
            Obra::firstOrCreate(['no' => $no], ['descripcion' => $descripcion, 'activa' => true]);
        }

        foreach (self::CENTRALES as $clave => $datos) {
            Almacen::firstOrCreate(
                ['clave' => $clave, 'obra_id' => null],
                ['nombre' => $datos['nombre'], 'tipo' => $datos['tipo'], 'activo' => true],
            );
        }

        // CONST es la misma clave en todas: el almacén de la obra, con lo que se
        // llevó de la planta. Se distingue por obra, no por clave.
        foreach (array_keys(self::OBRAS) as $no) {
            $obra = Obra::where('no', $no)->first();

            if ($obra === null) {
                throw new RuntimeException("No existe la obra {$no} y no se pudo crear: su almacén CONST se quedaría sin dar de alta.");
            }

            Almacen::firstOrCreate(
                ['clave' => 'CONST', 'obra_id' => $obra->id],
                ['nombre' => "Almacén de Construcción {$obra->no}", 'tipo' => AlmacenTipo::Montaje, 'activo' => true],
            );
        }

        $this->command?->info(sprintf(
            'Catálogo: %d áreas, %d obras y %d almacenes en total.',
            Area::count(),
            Obra::count(),
            Almacen::count(),
        ));
    }
}
