<?php

namespace Database\Seeders;

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Obra as ObraDelPortal;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Defecto;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Obra;
use App\Models\Qal\Sublote;
use App\Models\Usuario;
use App\Services\Qal\RegistradorInspeccion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Una obra de prueba para ver Registro 2 en pantalla.
 *
 * Arma la obra `PRUEBA-REGISTRO2` con inspecciones de pieza en las tres
 * transformaciones y sublotes de accesorios, repartidos en los últimos tres
 * meses para que cada opción del periodo dé un resultado distinto. Lo que deja
 * a propósito:
 *
 *  - **Registro ≠ pieza.** CM-101 #1 se rechazó y se reinspeccionó, y la QR
 *    PR2-TR-301-1 se rechazó en soldado y se volvió a revisar: dos registros,
 *    una pieza.
 *  - **Los tres estatus.** Liberadas, rechazadas y una pendiente de hoy.
 *  - **Un inspector dado de baja.** Diego Ruiz tiene registros de hace más de
 *    un mes: sale «(inactivo)» en el filtro y se puede seguir filtrando por él.
 *  - **Sublotes.** Uno rechazado sin disposición (sale «sin decidir»), uno
 *    rechazado y reinspeccionado (se lista una vez, con la última), uno de
 *    pintura y uno liberado por concesión.
 *
 * Idempotente: borra y vuelve a sembrar sólo lo de su obra. Los inspectores se
 * reutilizan por su correo.
 *
 * Correr con: php artisan db:seed --class=QalRegistro2DevSeeder
 */
class QalRegistro2DevSeeder extends Seeder
{
    private const OBRA = 'PRUEBA-REGISTRO2';

    /** @var array<string, array{0: string, 1: FaseTransformacion, 2: bool}> clave => nombre, fase, activo */
    private const INSPECTORES = [
        'ana' => ['Ana Torres', FaseTransformacion::Primera, true],
        'bruno' => ['Bruno Díaz', FaseTransformacion::Segunda, true],
        'carla' => ['Carla Méndez', FaseTransformacion::Tercera, true],
        'diego' => ['Diego Ruiz', FaseTransformacion::Primera, false],
    ];

    /** @var list<array{0: string, 1: string, 2: float}> marca, descripción, kg */
    private const MARCAS = [
        ['CM-101', 'Columna HSS 8x8', 120.0],
        ['VG-201', 'Viga IPR 12"', 85.5],
        ['TR-301', 'Trabe armada 24"', 210.0],
    ];

    private const PIEZAS_POR_MARCA = 6;

    private CarbonImmutable $hoy;

    /** @var array<string, Inspector> */
    private array $inspectores = [];

    public function run(): void
    {
        $this->hoy = CarbonImmutable::today();

        $obra = ObraDelPortal::query()->firstOrCreate(
            ['no' => self::OBRA],
            ['descripcion' => 'Obra de prueba · Registro 2', 'presupuesto_total' => 0, 'estatus' => 'abierta'],
        );
        $obra->update(['activa' => true]);

        $this->limpiar($obra);

        Obra::query()->create(['obra_id' => $obra->id, 'responsable_calidad' => 'Prueba']);

        foreach (self::INSPECTORES as $clave => [$nombre, $fase, $activo]) {
            $this->inspectores[$clave] = $this->inspector($clave, $nombre, $fase, $activo);
        }

        $catalogo = Catalogo::query()->create([
            'obra_id' => $obra->id,
            'nombre' => 'Catálogo '.self::OBRA,
            'version' => 1,
            'vigente' => true,
        ]);

        $conceptos = collect(self::MARCAS)->mapWithKeys(fn (array $marca): array => [
            $marca[0] => $this->concepto($catalogo, ...$marca),
        ]);

        $this->primera($conceptos->all());
        $this->segundaYPintura($conceptos->all());
        $this->accesorios($obra);

        $this->command?->info('Obra '.self::OBRA." (id {$obra->id}) lista para Registro 2.");
        $this->command?->table(['Qué', 'Cuántos'], [
            ['Inspecciones de pieza', Inspeccion::query()->where('obra_id', $obra->id)->count()],
            ['Sublotes (con reinspecciones)', Sublote::query()->whereHas('lote', fn ($lote) => $lote->where('obra_id', $obra->id))->count()],
            ['Inspector dado de baja', 'Diego Ruiz'],
        ]);
    }

    /**
     * 1ª por marca y consecutivo. CM-101 #1 se rechaza y se reinspecciona.
     *
     * @param  array<string, Concepto>  $conceptos
     */
    private function primera(array $conceptos): void
    {
        $this->deMarca($conceptos['CM-101'], 1, 'ana', 10, EstatusInspeccion::Rechazado);
        $this->deMarca($conceptos['CM-101'], 1, 'ana', 6, EstatusInspeccion::Liberado, numero: 2);
        $this->deMarca($conceptos['CM-101'], 2, 'ana', 2, EstatusInspeccion::Liberado);
        $this->deMarca($conceptos['CM-101'], 3, 'ana', 0, EstatusInspeccion::Liberado);
        $this->deMarca($conceptos['VG-201'], 1, 'ana', 15, EstatusInspeccion::Liberado);
        $this->deMarca($conceptos['VG-201'], 2, 'ana', 4, EstatusInspeccion::Liberado);
        $this->deMarca($conceptos['VG-201'], 3, 'ana', 0, EstatusInspeccion::Pendiente);
        $this->deMarca($conceptos['TR-301'], 1, 'ana', 26, EstatusInspeccion::Rechazado);

        // Lo de quien ya se fue: fuera de los últimos 30 días.
        $this->deMarca($conceptos['VG-201'], 4, 'diego', 45, EstatusInspeccion::Liberado);
        $this->deMarca($conceptos['CM-101'], 4, 'diego', 60, EstatusInspeccion::Rechazado);
        $this->deMarca($conceptos['TR-301'], 2, 'diego', 80, EstatusInspeccion::Liberado);
    }

    /**
     * 2ª y pintura por la pieza física. La TR-301-1 se rechaza en soldado y se
     * vuelve a revisar.
     *
     * @param  array<string, Concepto>  $conceptos
     */
    private function segundaYPintura(array $conceptos): void
    {
        $piezas = collect($conceptos)->map(fn (Concepto $concepto) => $concepto->piezas()->orderBy('id')->get());

        foreach ($piezas['CM-101']->take(3) as $i => $pieza) {
            $this->dePieza($pieza, FaseTransformacion::Segunda, Subetapa::ArmadoVestido, 'bruno', 9 - $i, '1.'.($i + 1));
            $this->dePieza($pieza, FaseTransformacion::Segunda, Subetapa::Soldado, 'bruno', 5 - $i, '1.'.($i + 1));
        }

        $trabe = $piezas['TR-301']->first();
        $this->dePieza($trabe, FaseTransformacion::Segunda, Subetapa::ArmadoVestido, 'bruno', 20, '2.1');
        $this->dePieza($trabe, FaseTransformacion::Segunda, Subetapa::Soldado, 'bruno', 12, '2.1', EstatusInspeccion::Rechazado);
        $this->dePieza($trabe, FaseTransformacion::Segunda, Subetapa::Soldado, 'bruno', 8, '2.1', numero: 2);

        foreach ($piezas['CM-101']->take(2) as $i => $pieza) {
            $this->dePieza($pieza, FaseTransformacion::Tercera, null, 'carla', 1 - $i, '1.'.($i + 1));
        }
        $this->dePieza($piezas['VG-201']->first(), FaseTransformacion::Tercera, null, 'carla', 3, '3.1', EstatusInspeccion::Rechazado);
    }

    private function accesorios(ObraDelPortal $obra): void
    {
        $placas = LoteAccesorio::factory()->create([
            'obra_id' => $obra->id,
            'marca' => 'ACC-PL12',
            'descripcion' => 'Placa de conexión 12 mm',
            'total_unidades' => 500,
        ]);
        $angulos = LoteAccesorio::factory()->create([
            'obra_id' => $obra->id,
            'marca' => 'ACC-AN08',
            'descripcion' => 'Ángulo de 3"',
            'total_unidades' => 300,
        ]);

        $this->sublote($placas, FaseTransformacion::Segunda, 'bruno', 3, unidades: 100);
        $this->sublote($placas, FaseTransformacion::Segunda, 'bruno', 12, unidades: 120, rechazado: true);

        $rechazado = $this->sublote($angulos, FaseTransformacion::Segunda, 'bruno', 20, unidades: 80, rechazado: true, disposicion: 'Reparar y reinspeccionar');
        Sublote::factory()->reinspeccionDe($rechazado)->create([
            'fase' => FaseTransformacion::Segunda,
            'inspector_id' => $this->inspectores['bruno']->id,
            ...$this->fecha(15),
        ]);

        $this->sublote($angulos, FaseTransformacion::Tercera, 'carla', 5, unidades: 80);
        $this->sublote($angulos, FaseTransformacion::Tercera, 'diego', 40, unidades: 60, rechazado: true, disposicion: 'Concesión del cliente');
    }

    private function deMarca(Concepto $concepto, int $consecutivo, string $inspector, int $haceDias, EstatusInspeccion $estatus, int $numero = 1): void
    {
        Inspeccion::factory()->deMarca($concepto, $consecutivo)->create([
            'inspector_id' => $this->inspectores[$inspector]->id,
            'estatus' => $estatus,
            'numero_inspeccion' => $numero,
            'kg' => $concepto->peso_unitario,
            'linea' => 'L1',
            'modulo' => '1.'.$consecutivo,
            'observaciones' => $estatus === EstatusInspeccion::Rechazado ? 'Deflexión fuera de tolerancia.' : null,
            ...$this->fecha($haceDias),
        ]);
    }

    private function dePieza(
        Pieza $pieza,
        FaseTransformacion $fase,
        ?Subetapa $subetapa,
        string $inspector,
        int $haceDias,
        string $modulo,
        EstatusInspeccion $estatus = EstatusInspeccion::Liberado,
        int $numero = 1,
    ): void {
        Inspeccion::factory()->dePieza($pieza, $fase, $subetapa)->create([
            'inspector_id' => $this->inspectores[$inspector]->id,
            'estatus' => $estatus,
            'numero_inspeccion' => $numero,
            'kg' => $pieza->marca->peso_unitario,
            'linea' => 'L2',
            'modulo' => $modulo,
            'observaciones' => $estatus === EstatusInspeccion::Rechazado ? 'Defecto a reparar.' : null,
            ...$this->fecha($haceDias),
        ]);
    }

    private function sublote(
        LoteAccesorio $lote,
        FaseTransformacion $fase,
        string $inspector,
        int $haceDias,
        int $unidades,
        bool $rechazado = false,
        ?string $disposicion = null,
    ): Sublote {
        $fabrica = Sublote::factory();

        $sublote = ($rechazado ? $fabrica->rechazado($disposicion) : $fabrica)->create([
            'lote_id' => $lote->id,
            'fase' => $fase,
            'unidades' => $unidades,
            'inspector_id' => $this->inspectores[$inspector]->id,
            'modulo' => 'ACC',
            ...$this->fecha($haceDias),
        ]);

        // Los defectos por unidad salen del catálogo si ya está sembrado; sin
        // él, el sublote rechazado se queda sin el detalle, no sin el rechazo.
        $defecto = Defecto::query()
            ->where('ambito', $fase === FaseTransformacion::Tercera ? AmbitoDefecto::Pintura : AmbitoDefecto::AccesorioDimensional)
            ->first();

        if ($rechazado && $defecto !== null) {
            foreach (range(1, $sublote->rechazadas) as $unidad) {
                $sublote->defectos()->create(['unidad' => $unidad, 'defecto_id' => $defecto->id]);
            }
        }

        return $sublote;
    }

    /**
     * La fecha con su año y semana ISO: el selector de semanas sale de esas dos
     * columnas, no de la fecha.
     *
     * @return array{fecha: string, anio: int, semana: int, capturado_en: CarbonImmutable}
     */
    private function fecha(int $haceDias): array
    {
        $dia = $this->hoy->subDays($haceDias);

        return [
            'fecha' => $dia->toDateString(),
            'anio' => $dia->isoWeekYear(),
            'semana' => $dia->isoWeek(),
            'capturado_en' => $dia->setTime(10, 0),
        ];
    }

    private function inspector(string $clave, string $nombre, FaseTransformacion $fase, bool $activo): Inspector
    {
        $usuario = Usuario::query()->firstOrCreate(
            ['email' => "registro2.{$clave}@prueba.local"],
            ['name' => $nombre, 'password' => Hash::make('password')],
        );
        $activo ? $usuario->reactivar() : $usuario->darDeBaja();

        return Inspector::query()->firstOrCreate(['usuario_id' => $usuario->id], ['fase' => $fase]);
    }

    private function concepto(Catalogo $catalogo, string $marca, string $descripcion, float $kg): Concepto
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

        foreach (range(1, self::PIEZAS_POR_MARCA) as $n) {
            Pieza::query()->create([
                'catalogo_id' => $catalogo->id,
                'concepto_id' => $concepto->id,
                'qr' => "PR2-{$marca}-{$n}",
                'qs' => "{$marca}-{$n}",
                'activo' => true,
            ]);
        }

        return $concepto;
    }

    private function limpiar(ObraDelPortal $obra): void
    {
        $registrador = app(RegistradorInspeccion::class);
        Inspeccion::query()->where('obra_id', $obra->id)->get()->each(fn (Inspeccion $inspeccion) => $registrador->borrar($inspeccion));

        // Las reinspecciones primero: la original no se deja borrar mientras
        // tenga alguna colgando.
        $lotes = LoteAccesorio::query()->where('obra_id', $obra->id)->pluck('id');
        Sublote::query()->whereIn('lote_id', $lotes)->whereNotNull('sublote_origen_id')->delete();
        Sublote::query()->whereIn('lote_id', $lotes)->delete();
        LoteAccesorio::query()->whereKey($lotes)->delete();

        $catalogos = Catalogo::query()->where('obra_id', $obra->id)->pluck('id');
        Pieza::query()->whereIn('catalogo_id', $catalogos)->delete();
        Concepto::query()->whereIn('catalogo_id', $catalogos)->delete();
        Catalogo::query()->whereKey($catalogos)->delete();

        Obra::query()->where('obra_id', $obra->id)->delete();
    }
}
