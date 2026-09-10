<?php

namespace Database\Seeders;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\ResultadoPnd;
use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use App\Models\Qal\ObraPndPlan;
use App\Models\Qal\PndJunta;
use App\Models\Qal\PndParametro;
use App\Models\Qal\PndReporte;
use App\Models\Qal\Soldador;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos de ejemplo del módulo de Calidad para desarrollo y demo.
 *
 * Llena **sólo lo que ya se calcula de la base**: las obras, el plan de PND con
 * sus informes, y el montaje e incidencias en obra. El tablero y la captura del
 * inspector siguen con sus propios datos falsos en el front, porque sus tablas
 * (`qal_inspecciones` y compañía) todavía no existen y no hay dónde sembrar.
 *
 * Es idempotente y acotado: borra y vuelve a sembrar **sus cuatro obras**, las
 * de la lista de abajo, y no toca ninguna otra que alguien haya dado de alta a
 * mano. Los catálogos se crean con `firstOrCreate`, así que tampoco pisan.
 *
 * Las semanas se cuelgan de hoy —de la actual hacia atrás— para que la pantalla
 * abra siempre con datos frescos y no en un año muerto.
 *
 * Lo que deja armado a propósito, para poder ver cada regla en pantalla:
 *
 *  - Una obra **sin `pz_total`**: la hoja de montaje escribe «falta» en vez de
 *    inventar un porcentaje de avance.
 *  - Una obra **sin plan de PND**: el avance dice «falta el dato» en vez de
 *    medir contra un denominador que nadie firmó.
 *  - Una semana **marcada «revisada, sin incidencias»**, que no es lo mismo que
 *    una semana en blanco.
 *  - Una semana **con defectos y sin montaje capturado**, que sale «sin base» y
 *    no como 0 %.
 *  - Semanas **sin capturar en medio**, que es como llega el dato de verdad.
 *
 * Correr con: php artisan db:seed --class=QalDevSeeder
 */
class QalDevSeeder extends Seeder
{
    /** Cuántas semanas hacia atrás se siembra, contando la actual. */
    private const SEMANAS = 12;

    /**
     * Las obras del seeder. La clave `no` es lo que identifica sus datos, así
     * que volver a correrlo sólo se lleva lo suyo.
     *
     * @var list<array{no: string, descripcion: string, pz_total: int|null, responsable: string, ritmo: int}>
     */
    private const OBRAS = [
        [
            'no' => 'T4-CANCUN',
            'descripcion' => 'Ampliación Terminal 4 Cancún',
            'pz_total' => 3100,
            'responsable' => 'R. Muñoz Salinas',
            'ritmo' => 150,
        ],
        [
            'no' => 'CP2-NAVE-A',
            'descripcion' => 'Cancún Parks II · Nave A',
            'pz_total' => 1480,
            'responsable' => 'L. Ferreiro',
            'ritmo' => 80,
        ],
        [
            // Sin piezas totales: la hoja de montaje tiene que decir «falta» en
            // lugar de calcular un avance sobre un denominador supuesto.
            'no' => 'TG-VILLA-MAGNA',
            'descripcion' => 'Tres Guerras · Villa Magna',
            'pz_total' => null,
            'responsable' => 'A. Del Valle',
            'ritmo' => 30,
        ],
        [
            'no' => 'TOTEM-PC',
            'descripcion' => 'Totem Prime Center',
            'pz_total' => 860,
            'responsable' => 'J. Cardona',
            'ritmo' => 45,
        ],
    ];

    public function run(): void
    {
        // Semilla fija: los datos de ejemplo tienen que salir iguales en la
        // máquina de cada quien, o «lo que ves tú» deja de ser «lo que veo yo».
        mt_srand(20260821);

        $this->limpiar();

        $laboratorios = $this->laboratorios();
        $soldadores = $this->soldadores();

        $semanaActual = (int) Carbon::now()->isoFormat('W');
        $anio = (int) Carbon::now()->isoFormat('GGGG');
        $desde = max(1, $semanaActual - self::SEMANAS + 1);

        foreach (self::OBRAS as $indice => $datos) {
            // La obra es la del portal; si ya existe con ese número se reusa.
            $obraDelPortal = ObraDelPortal::query()->firstOrCreate(
                ['no' => $datos['no']],
                ['descripcion' => $datos['descripcion'], 'activa' => true],
            );

            $obra = Obra::paraObra($obraDelPortal);
            $obra->update([
                'responsable_calidad' => $datos['responsable'],
                'pz_total' => $datos['pz_total'],
            ]);

            $this->planDePnd($obra, $indice);
            $this->informesDePnd($obra, $indice, $anio, $desde, $semanaActual, $laboratorios, $soldadores);
            $this->montajeEIncidencias($obra, $indice, $datos['ritmo'], $anio, $desde, $semanaActual);
        }

        $this->command?->info('Calidad: '.count(self::OBRAS).' obras con PND, montaje e incidencias de las últimas '.self::SEMANAS.' semanas.');
    }

    /**
     * Se borran las fichas de Calidad de las obras del seeder y con ellas, en
     * cascada, sus informes de PND, montaje e incidencias. La obra del portal
     * se queda: no es de Calidad.
     */
    private function limpiar(): void
    {
        Obra::query()
            ->whereHas('obra', fn ($obra) => $obra->whereIn('no', array_column(self::OBRAS, 'no')))
            ->get()
            ->each->delete();
    }

    /**
     * Lo pactado con el cliente, por método.
     *
     * La cuarta obra se queda **sin una sola fila** a propósito: sin plan no hay
     * compromiso firmado, y el avance debe decir «falta el dato» en lugar de
     * medir contra un número inventado. Ojo, no es lo mismo que un cero, que
     * significaría «se pactaron cero».
     */
    private function planDePnd(Obra $obra, int $indice): void
    {
        $planes = [
            0 => [MetodoPnd::Ut->value => 420, MetodoPnd::Mt->value => 180, MetodoPnd::Pt->value => 0],
            1 => [MetodoPnd::Ut->value => 260, MetodoPnd::Mt->value => 90],
            2 => [MetodoPnd::Ut->value => 75],
            3 => [],
        ];

        foreach ($planes[$indice] ?? [] as $metodo => $comprometidas) {
            ObraPndPlan::create([
                'qal_obra_id' => $obra->id,
                'metodo' => $metodo,
                'comprometidas' => $comprometidas,
            ]);
        }

        if ($indice === 0) {
            $obra->update([
                'pnd_nota' => '10% de las juntas de penetración completa por UT y el 100% de los filetes de '.
                    'columna por MT, cláusula 7.3 del contrato. PT se pactó en cero: el cliente lo sustituyó por MT.',
            ]);
        }
    }

    /**
     * Los informes del laboratorio, con su rejilla de puntos examinados.
     *
     * Cada renglón es un **spot**, no una junta: `J-18-1-2` es el segundo punto
     * de la junta `18-1`, y es el spot el denominador del porcentaje de rechazo.
     *
     * @param  list<Laboratorio>  $laboratorios
     * @param  list<Soldador>  $soldadores
     */
    private function informesDePnd(
        Obra $obra,
        int $indice,
        int $anio,
        int $desde,
        int $hasta,
        array $laboratorios,
        array $soldadores,
    ): void {
        // La cuarta obra no tiene ensayos: sirve para ver una obra viva en
        // incidencias que todavía no aparece en la hoja de PND.
        if ($indice === 3) {
            return;
        }

        $metodos = [MetodoPnd::Ut, MetodoPnd::Mt, MetodoPnd::Ut, MetodoPnd::Pt];
        $marcas = $this->marcas($indice);

        foreach (range(0, 3 - $indice) as $n) {
            $semana = min($hasta, $desde + 2 + $n * 3);
            $fecha = Carbon::now()->setISODate($anio, $semana, 3);
            $metodo = $metodos[$n % count($metodos)];
            $laboratorio = $laboratorios[$n % count($laboratorios)];

            $reporte = PndReporte::create([
                'reporte_no' => sprintf('%s-%s-%02d%02d', $laboratorio->siglas, $metodo->value, $semana, $indice * 10 + $n),
                'metodo' => $metodo,
                'laboratorio_id' => $laboratorio->id,
                'qal_obra_id' => $obra->id,
                'lugar' => 'Planta 2 · Celaya',
                'fecha_prueba' => $fecha,
                'fecha_emision' => $fecha->copy()->addDays(2),
                'anio' => $anio,
                'semana' => $semana,
                'porcentaje_inspeccion' => $metodo === MetodoPnd::Ut ? 10 : 100,
                'tecnico' => ['R. Muñoz', 'S. Arriaga', 'D. Peralta'][$n % 3],
                'material' => 'A572 Gr.50',
                'norma' => 'AWS D1.1',
            ]);

            foreach ($metodo->parametrosSugeridos() as $posicion => $clave) {
                PndParametro::create([
                    'qal_pnd_reporte_id' => $reporte->id,
                    'clave' => $clave,
                    'valor' => $this->valorDeParametro($clave, $posicion),
                ]);
            }

            $this->rejilla($reporte, $marcas, $soldadores);
        }
    }

    /**
     * La rejilla de un informe: entre 8 y 22 puntos, con algún rechazo.
     *
     * @param  list<string>  $marcas
     * @param  list<Soldador>  $soldadores
     */
    private function rejilla(PndReporte $reporte, array $marcas, array $soldadores): void
    {
        $discontinuidades = ['Porosidad agrupada', 'Falta de fusión', 'Socavado', 'Inclusión de escoria', 'Grieta transversal'];

        foreach (range(1, mt_rand(8, 22)) as $fila) {
            $marca = $marcas[($fila - 1) % count($marcas)];
            $junta = sprintf('%d-%d', 10 + intdiv($fila, 3), 1 + $fila % 3);
            $rechazada = mt_rand(1, 100) <= 9;

            PndJunta::create([
                'qal_pnd_reporte_id' => $reporte->id,
                'marca' => $marca,
                'junta' => $junta,
                'modulo' => 'M'.(1 + $fila % 4),
                'spot' => 1 + $fila % 2,
                'resultado' => $rechazada ? ResultadoPnd::Rechazada : ResultadoPnd::Aceptada,
                'discontinuidad' => $rechazada ? $discontinuidades[array_rand($discontinuidades)] : null,
                'longitud_discontinuidad' => $rechazada ? mt_rand(8, 60) / 2 : null,
                'espesor' => mt_rand(60, 250) / 10,
                'soldador_id' => $soldadores[$fila % count($soldadores)]->id,
            ]);
        }
    }

    /**
     * El montaje semana a semana y lo que falló en obra.
     *
     * Las dos cosas se siembran juntas porque están relacionadas —una es el
     * denominador de la otra— pero se guardan aparte, que es justo la
     * corrección de este módulo sobre el Excel que sustituye.
     */
    private function montajeEIncidencias(Obra $obra, int $indice, int $ritmo, int $anio, int $desde, int $hasta): void
    {
        // Semanas en las que NO se captura el avance. Son huecos deliberados:
        // así llega el dato de verdad, y la tabla tiene que decir «falta».
        $sinAvance = [$desde + 4, $hasta - 1];

        // Semana declarada revisada y limpia, que no es una semana en blanco.
        $limpia = $hasta - 3;

        // Semana con defectos y sin denominador: la tabla la marca «sin base»
        // en vez de dar un 0 % que se leería como que salió bien.
        $sinBase = $desde + 4;

        foreach (range($desde, $hasta) as $semana) {
            $capturaAvance = ! in_array($semana, $sinAvance, true);

            if ($capturaAvance) {
                ObraMontaje::create([
                    'qal_obra_id' => $obra->id,
                    'anio' => $anio,
                    'semana' => $semana,
                    'pz_montadas' => (int) max(0, $ritmo + intdiv(mt_rand(-$ritmo, $ritmo), 2)),
                    'sin_incidencias' => $semana === $limpia,
                    'notas' => $semana === $hasta - 5 ? 'Tres días parados por lluvia.' : null,
                ]);
            }

            if ($semana === $limpia) {
                continue;
            }

            // La semana en curso siempre trae algo en las dos primeras obras:
            // el bloque «% de la semana» del reporte semanal en blanco no deja
            // ver si el cálculo está bien o si es que no hubo nada.
            $cuantas = match (true) {
                $semana === $sinBase => 1,
                $semana === $hasta && $indice < 2 => mt_rand(1, 3),
                default => $this->cuantasIncidencias($indice),
            };

            for ($n = 0; $n < $cuantas; $n++) {
                $this->incidencia($obra, $anio, $semana, $indice, $hasta);
            }
        }
    }

    /**
     * Cuántas incidencias tiene una semana.
     *
     * La mayoría de las semanas no tiene ninguna. Sembrar una por semana en
     * todas dejaría una pantalla plana que no se parece a la realidad y en la
     * que no se distingue una obra problemática de una tranquila.
     */
    private function cuantasIncidencias(int $indice): int
    {
        $tirada = mt_rand(1, 100);

        // La primera obra es la que va peor: es la que se quiere ver arriba en
        // la portada, que ordena de la peor a la mejor.
        $probabilidad = [0 => 75, 1 => 45, 2 => 55, 3 => 30][$indice] ?? 40;

        if ($tirada > $probabilidad) {
            return 0;
        }

        return mt_rand(1, $indice === 0 ? 4 : 2);
    }

    private function incidencia(Obra $obra, int $anio, int $semana, int $indice, int $hasta): void
    {
        $descripciones = [
            'Barrenos desfasados en el empalme de columna',
            'Placa base sin el nivel de proyecto, se calzó en sitio',
            'Contraflecha fuera de tolerancia en trabe de cubierta',
            'Falta de vestido en el atiesador del nudo',
            'Golpe de transporte en el patín inferior',
            'Recubrimiento con escurrimiento y espesor bajo',
            'Marca ilegible, no se pudo trazar la pieza',
            'Faltó anclaje en la entrega, se detuvo el montaje',
            'Soldadura de campo con porosidad visible',
            'Perfil llegó con longitud corta contra plano',
        ];

        $area = $this->areaAlAzar();

        ObraIncidencia::create([
            'qal_obra_id' => $obra->id,
            'anio' => $anio,
            'semana' => $semana,
            'fecha' => Carbon::now()->setISODate($anio, $semana, mt_rand(1, 5)),
            'area' => $area,
            'departamento' => $this->departamentoDe($area),
            'pz_defecto' => mt_rand(1, $indice === 0 ? 8 : 4),
            'folio' => mt_rand(1, 100) <= 70 ? sprintf('NC-%04d', mt_rand(1, 900)) : null,
            'descripcion' => $descripciones[array_rand($descripciones)],
            // Lo viejo ya se resolvió; lo de las dos últimas semanas sigue
            // abierto, que es lo que hace útil el contador «sin cerrar».
            'cerrada_en' => $semana < $hasta - 1 && mt_rand(1, 100) <= 80
                ? Carbon::now()->setISODate($anio, $semana + 1, 3)
                : null,
        ]);
    }

    /** Más taller que montaje: es el reparto real que enseña el histórico. */
    private function areaAlAzar(): AreaIncidencia
    {
        $tirada = mt_rand(1, 100);

        return match (true) {
            $tirada <= 50 => AreaIncidencia::Taller,
            $tirada <= 75 => AreaIncidencia::Montaje,
            default => AreaIncidencia::TallerPintura,
        };
    }

    /**
     * El responsable coherente con el área.
     *
     * No se sortea suelto: una incidencia de taller de pintura atribuida a
     * logística dejaría la hoja 5 del reporte semanal en cero y parecería un
     * error de cálculo cuando sería un error del dato.
     */
    private function departamentoDe(AreaIncidencia $area): DepartamentoIncidencia
    {
        $candidatos = match ($area) {
            AreaIncidencia::Taller => [
                DepartamentoIncidencia::PrimeraTransformacion,
                DepartamentoIncidencia::SegundaTransformacion,
                DepartamentoIncidencia::SegundaTransformacion,
                DepartamentoIncidencia::Ingenieria,
            ],
            AreaIncidencia::TallerPintura => [DepartamentoIncidencia::PinturaTaller],
            AreaIncidencia::Montaje => [
                DepartamentoIncidencia::Construccion,
                DepartamentoIncidencia::Construccion,
                DepartamentoIncidencia::Logistica,
                DepartamentoIncidencia::PinturaObra,
            ],
        };

        return $candidatos[array_rand($candidatos)];
    }

    /**
     * Marcas con los prefijos de ingeniería, que es lo que teclea el
     * laboratorio y lo que un día enlazará con las piezas de la obra.
     *
     * @return list<string>
     */
    private function marcas(int $indice): array
    {
        $prefijos = [['TP', 'CM', 'TS'], ['CM', 'AR'], ['TG', 'VR']][$indice] ?? ['TP'];
        $marcas = [];

        foreach ($prefijos as $prefijo) {
            foreach (range(1, 6) as $n) {
                $marcas[] = sprintf('%s-%d%d', $prefijo, $indice + 1, $n);
            }
        }

        return $marcas;
    }

    private function valorDeParametro(string $clave, int $posicion): string
    {
        return match ($clave) {
            'Frecuencia' => '2.25 MHz',
            'Palpador' => 'Angular 70°',
            'Ángulo' => '70°',
            'Acoplante' => 'Carboximetilcelulosa',
            'Equipo' => 'Olympus EPOCH 650',
            'Tipo de partícula' => 'Vía húmeda fluorescente',
            'Técnica' => 'Yugo de CA',
            'Penetrante' => 'Magnaflux SKL-SP2',
            'Revelador' => 'Magnaflux SKD-S2',
            'Iluminación' => '1000 lux',
            default => 'Según procedimiento PR-'.str_pad((string) ($posicion + 1), 2, '0', STR_PAD_LEFT),
        };
    }

    /**
     * @return list<Laboratorio>
     */
    private function laboratorios(): array
    {
        $listado = [
            'Infraestructura y Ensayos del Bajío' => 'INFRA',
            'Laboratorio Sigma NDT' => 'SIGMA',
            'Control Técnico Peninsular' => 'CTP',
        ];

        $laboratorios = [];

        foreach ($listado as $nombre => $siglas) {
            $laboratorios[] = Laboratorio::firstOrCreate(
                ['nombre' => $nombre],
                ['siglas' => $siglas, 'activo' => true],
            );
        }

        return $laboratorios;
    }

    /**
     * @return list<Soldador>
     */
    private function soldadores(): array
    {
        $listado = [
            'SOL-01' => 'J. Ramírez Ortega',
            'SOL-02' => 'M. Chávez Lira',
            'SOL-03' => 'E. Nájera Pinto',
            'SOL-04' => 'H. Valadez Ruiz',
            'SOL-05' => 'C. Aguilar Mota',
        ];

        $soldadores = [];

        foreach ($listado as $clave => $nombre) {
            $soldadores[] = Soldador::firstOrCreate(
                ['clave' => $clave],
                ['nombre' => $nombre, 'activo' => true],
            );
        }

        return $soldadores;
    }
}
