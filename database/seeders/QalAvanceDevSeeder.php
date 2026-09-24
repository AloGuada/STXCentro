<?php

namespace Database\Seeders;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Qal\ConfiguracionQal;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use App\Services\Qal\RegistradorInspeccion;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Una obra de prueba para ver «Formularios según el avance» en pantalla.
 *
 * Deja el interruptor encendido y arma la obra `PRUEBA-AVANCE` con un catálogo
 * de cuatro marcas y cinco piezas cada una, y los planes justos para que cada
 * regla se vea:
 *
 *  - CM-101 · tres piezas en el plan de 2ª **cerrado de esta semana**: entran.
 *  - VG-201 · dos piezas en el plan de 2ª **cerrado de la semana pasada** que
 *    no se hicieron: entran por atrasadas.
 *  - TR-301 · una pieza con soldado **rechazado** y sin plan: entra porque ya
 *    tiene inspección en 2ª (la reinspección no se queda a medias).
 *  - PL-401 · dos piezas en el plan de 2ª de la **semana que entra**, todavía
 *    abierto: no entran. La marca no sale en Registros.
 *  - Pintura (3ª) · el plan de esta semana existe pero **sigue abierto**: no
 *    entra ninguna pieza en pintura y el aviso lo dice.
 *
 * Idempotente: borra y vuelve a sembrar sólo lo de su obra.
 *
 * Correr con: php artisan db:seed --class=QalAvanceDevSeeder
 */
class QalAvanceDevSeeder extends Seeder
{
    private const OBRA = 'PRUEBA-AVANCE';

    /** @var list<array{0: string, 1: string, 2: float}> marca, descripción, kg */
    private const MARCAS = [
        ['CM-101', 'Columna HSS 8x8', 120.0],
        ['VG-201', 'Viga IPR 12"', 85.5],
        ['TR-301', 'Trabe armada 24"', 210.0],
        ['PL-401', 'Placa base 25 mm', 45.0],
    ];

    private const PIEZAS_POR_MARCA = 5;

    public function run(): void
    {
        $obra = ObraDelPortal::query()->firstOrCreate(
            ['no' => self::OBRA],
            ['descripcion' => 'Obra de prueba · Formularios según avance', 'presupuesto_total' => 0, 'estatus' => 'abierta'],
        );
        $obra->update(['activa' => true]);

        $this->limpiar($obra);

        Obra::query()->create(['obra_id' => $obra->id, 'responsable_calidad' => 'Prueba']);
        ConfiguracionQal::actual()->update(['formularios_segun_avance' => true]);

        $catalogo = Catalogo::query()->create([
            'obra_id' => $obra->id,
            'nombre' => 'Catálogo '.self::OBRA,
            'version' => 1,
            'vigente' => true,
        ]);

        $piezas = collect(self::MARCAS)->mapWithKeys(fn (array $marca): array => [
            $marca[0] => $this->marcaConPiezas($catalogo, ...$marca),
        ]);

        $grupo = GrupoTrabajo::query()->firstOrCreate(['descripcion' => 'Grupo de prueba avance'], ['activo' => true]);

        $this->plan($obra, FaseTransformacion::Segunda, now(), cerrado: true, piezas: $piezas['CM-101']->take(3), grupo: $grupo);
        $this->plan($obra, FaseTransformacion::Segunda, now()->subWeek(), cerrado: true, piezas: $piezas['VG-201']->take(2), grupo: $grupo);
        $this->plan($obra, FaseTransformacion::Segunda, now()->addWeek(), cerrado: false, piezas: $piezas['PL-401']->take(2), grupo: $grupo);
        $this->plan($obra, FaseTransformacion::Tercera, now(), cerrado: false, piezas: $piezas['CM-101']->take(3), grupo: $grupo);

        Inspeccion::factory()
            ->dePieza($piezas['TR-301']->first(), FaseTransformacion::Segunda, Subetapa::Soldado)
            ->create(['estatus' => EstatusInspeccion::Rechazado, 'fecha' => now()->subDays(2)]);

        $this->command?->info('Obra '.self::OBRA." (id {$obra->id}) lista. Interruptor encendido.");
        $this->command?->table(['Marca', 'QR', '¿Entra en 2ª?'], $this->resumen($piezas));
    }

    /**
     * @return Collection<int, Pieza>
     */
    private function marcaConPiezas(Catalogo $catalogo, string $marca, string $descripcion, float $kg): Collection
    {
        $concepto = Concepto::query()->create([
            'obra_id' => $catalogo->obra_id,
            'catalogo_id' => $catalogo->id,
            'marca' => $marca,
            'descripcion' => $descripcion,
            'cantidad' => self::PIEZAS_POR_MARCA,
            'peso_unitario' => $kg,
            'version' => 1,
            'activo' => true,
        ]);

        return collect(range(1, self::PIEZAS_POR_MARCA))->map(fn (int $n): Pieza => Pieza::query()->create([
            'catalogo_id' => $catalogo->id,
            'concepto_id' => $concepto->id,
            'qr' => "PA-{$marca}-{$n}",
            'qs' => "{$marca}-{$n}",
            'activo' => true,
        ]));
    }

    /**
     * @param  Collection<int, Pieza>  $piezas
     */
    private function plan(ObraDelPortal $obra, FaseTransformacion $fase, CarbonInterface $semana, bool $cerrado, Collection $piezas, GrupoTrabajo $grupo): void
    {
        $plan = Programacion::query()->create([
            'obra_id' => $obra->id,
            'fase' => $fase,
            'anio' => $semana->isoWeekYear(),
            'semana' => $semana->isoWeek(),
            'cerrada_at' => $cerrado ? now() : null,
        ]);

        foreach ($piezas as $modulo => $pieza) {
            ProgramacionPieza::query()->create([
                'programacion_id' => $plan->id,
                'pieza_id' => $pieza->id,
                'concepto_id' => $pieza->concepto_id,
                'marca' => $pieza->marca->marca,
                'lote' => $pieza->marca->lote,
                'qr' => $pieza->qr,
                'qs' => $pieza->qs,
                'grupo_trabajo_id' => $grupo->id,
                'modulo' => '1.'.($modulo + 1),
            ]);
        }
    }

    private function limpiar(ObraDelPortal $obra): void
    {
        $registrador = app(RegistradorInspeccion::class);
        Inspeccion::query()->where('obra_id', $obra->id)->get()->each(fn (Inspeccion $inspeccion) => $registrador->borrar($inspeccion));

        Programacion::query()->where('obra_id', $obra->id)->get()->each->delete();

        $catalogos = Catalogo::query()->where('obra_id', $obra->id)->pluck('id');
        Pieza::query()->whereIn('catalogo_id', $catalogos)->delete();
        Concepto::query()->whereIn('catalogo_id', $catalogos)->delete();
        Catalogo::query()->whereKey($catalogos)->delete();

        Obra::query()->where('obra_id', $obra->id)->delete();
    }

    /**
     * @param  Collection<string, Collection<int, Pieza>>  $piezas
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function resumen(Collection $piezas): array
    {
        $motivos = [
            'CM-101' => [1 => 'Sí · plan de esta semana', 2 => 'Sí · plan de esta semana', 3 => 'Sí · plan de esta semana'],
            'VG-201' => [1 => 'Sí · atrasada', 2 => 'Sí · atrasada'],
            'TR-301' => [1 => 'Sí · ya tiene soldado rechazado'],
            'PL-401' => [1 => 'No · es de la semana que entra', 2 => 'No · es de la semana que entra'],
        ];

        return $piezas->flatMap(fn (Collection $deLaMarca, string $marca): array => $deLaMarca
            ->values()
            ->map(fn (Pieza $pieza, int $i): array => [$marca, $pieza->qr, $motivos[$marca][$i + 1] ?? 'No · no está programada'])
            ->all())
            ->values()
            ->all();
    }
}
