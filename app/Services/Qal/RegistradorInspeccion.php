<?php

namespace App\Services\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\NivelAql;
use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\ResultadoPunto;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Enums\Qal\TipoDatoPunto;
use App\Enums\Qal\TipoJunta;
use App\Enums\Qal\VeredictoLote;
use App\Models\Concepto;
use App\Models\Media;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Inspector;
use App\Models\Qal\Junta;
use App\Models\Qal\PuntoInspeccion;
use App\Models\Qal\TipoPieza;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Guarda una inspección entera: cabecera, puntos, defectos y lo propio de su
 * fase (muestreo, juntas, espesores, adherencia con sus fotos).
 *
 * Va en una transacción porque la inspección **es** todo eso. Una cabecera sin
 * sus puntos contaría como pieza inspeccionada sin haber revisado nada, y el
 * FPY saldría de un denominador que no existió.
 *
 * Aquí se calcula lo que el inspector no teclea: el folio, el número de
 * inspección, la semana, los barrenos y el dimensional deducidos, el veredicto
 * del lote, el promedio de espesores y el resultado de cada junta. El
 * formulario adelanta esas cuentas; las que valen son éstas.
 */
class RegistradorInspeccion
{
    public function __construct(
        private readonly ReglasInspeccion $reglas,
        private readonly CalculadorAql $aql,
        private readonly CalculadorEspesores $espesores,
        private readonly PiezasHabilitadas $habilitadas,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  lo validado por InspeccionRequest
     * @param  list<UploadedFile>  $fotos  evidencia de la prueba de adherencia
     */
    public function registrar(array $datos, Usuario $capturista, array $fotos = []): Inspeccion
    {
        return DB::transaction(function () use ($datos, $capturista, $fotos): Inspeccion {
            $fase = FaseTransformacion::from($datos['fase']);
            $subetapa = $fase === FaseTransformacion::Segunda ? Subetapa::from($datos['subetapa']) : null;
            $pieza = $this->pieza($fase, $datos);

            // Con el filtro por avance encendido sólo entra lo que Producción
            // programó. Corregir una inspección ya guardada no pasa por aquí.
            $motivo = $this->habilitadas->motivoDeBloqueo((int) $datos['obra_id'], $fase, $pieza['qr']);

            if ($motivo !== null) {
                throw ValidationException::withMessages(['prod_pieza_id' => $motivo]);
            }

            $inspeccion = new Inspeccion([
                ...$pieza,
                'obra_id' => $datos['obra_id'],
                'fase' => $fase,
                'subetapa' => $subetapa,
                'inspector_id' => Inspector::query()->firstOrCreate(
                    ['usuario_id' => $capturista->getKey()],
                    ['fase' => $fase],
                )->id,
                'numero_inspeccion' => $this->siguienteNumero((int) $datos['obra_id'], $fase, $subetapa, $pieza),
                'capturado_en' => now(),
                'capturista_id' => $capturista->getKey(),
            ]);

            $this->guardar($inspeccion, $datos, $fotos);

            return $inspeccion;
        });
    }

    /**
     * Corrige una inspección ya guardada.
     *
     * Lo que la identifica —obra, fase, sub-etapa, pieza— no cambia (lo cuida
     * InspeccionRequest), así que folio, número de inspección, inspector y
     * hora de captura se quedan. Lo capturado se reemplaza en bloque: la
     * corrección es «esto no era lo que vi», no un ajuste renglón por renglón.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<UploadedFile>  $fotos  evidencia nueva
     * @param  list<int>  $fotosQuitar  evidencia guardada que se retira
     */
    public function actualizar(Inspeccion $inspeccion, array $datos, array $fotos = [], array $fotosQuitar = []): Inspeccion
    {
        return DB::transaction(function () use ($inspeccion, $datos, $fotos, $fotosQuitar): Inspeccion {
            $inspeccion->puntos()->delete();
            $inspeccion->defectos()->delete();
            $inspeccion->muestreo()->delete();
            $inspeccion->juntas()->delete();
            $inspeccion->pintura()->delete();

            $this->guardar($inspeccion, $datos, $fotos, $fotosQuitar);

            return $inspeccion;
        });
    }

    /**
     * Puntos, juntas, espesores y demás se van en cascada. La evidencia no:
     * cuelga de `media` por relación polimórfica y sus archivos están en disco.
     */
    public function borrar(Inspeccion $inspeccion): void
    {
        DB::transaction(function () use ($inspeccion): void {
            if ($adherencia = $inspeccion->adherencia) {
                $this->quitarFotos($adherencia->fotos);
            }

            $inspeccion->delete();
        });
    }

    /**
     * La cabecera y todo lo capturado, igual al crear que al corregir.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<UploadedFile>  $fotos
     * @param  list<int>  $fotosQuitar
     */
    private function guardar(Inspeccion $inspeccion, array $datos, array $fotos, array $fotosQuitar = []): void
    {
        $fase = $inspeccion->fase;
        $subtipo = $fase === FaseTransformacion::Primera ? SubtipoPrimera::from($datos['subtipo']) : null;
        $fecha = Carbon::parse($datos['fecha']);

        $puntos = PuntoInspeccion::query()->paraFormulario($fase, $inspeccion->subetapa, $subtipo)->get()->keyBy('clave');
        $respuestas = $this->respuestas($datos['puntos'] ?? [], $puntos);

        $inspeccion->fill([
            'tipo_pieza_id' => $datos['tipo_pieza_id'] ?? TipoPieza::paraMarca($inspeccion->marca)?->id,
            'subtipo' => $subtipo,
            'fecha' => $fecha,
            'anio' => $fecha->isoWeekYear(),
            'semana' => $fecha->isoWeek(),
            'cantidad_lote' => $fase === FaseTransformacion::Primera
                ? ($datos['muestreo']['tamano_lote'] ?? $datos['cantidad_lote'] ?? null)
                : null,
            'kg' => $datos['kg'],
            'folio_strumis' => $datos['folio_strumis'] ?? null,
            'linea' => $fase === FaseTransformacion::Primera ? null : ($datos['linea'] ?? null),
            'modulo' => $fase === FaseTransformacion::Primera ? null : ($datos['modulo'] ?? null),
            'equipo_id' => $fase === FaseTransformacion::Primera ? ($datos['equipo_id'] ?? null) : null,
            'operador_id' => $fase === FaseTransformacion::Primera ? ($datos['operador_id'] ?? null) : null,
            'responsable_id' => $fase === FaseTransformacion::Segunda ? ($datos['responsable_id'] ?? null) : null,
            'supervisor_pintura_id' => $fase === FaseTransformacion::Tercera ? ($datos['supervisor_pintura_id'] ?? null) : null,
            'soldador_id' => $fase === FaseTransformacion::Segunda ? ($datos['soldador_id'] ?? null) : null,
            'estatus' => $this->reglas->estatusFinal(EstatusInspeccion::from($datos['estatus']), $respuestas),
            'avance_iv' => $fase === FaseTransformacion::Segunda ? ($datos['avance_iv'] ?? null) : null,
            'avance_is' => $fase === FaseTransformacion::Segunda ? ($datos['avance_is'] ?? null) : null,
            'observaciones' => $datos['observaciones'] ?? null,
        ])->save();

        $inspeccion->puntos()->createMany(
            collect($respuestas)->map(fn (string $valor, string $clave): array => [
                'punto_id' => $puntos[$clave]->id,
                ...$this->filaDePunto($puntos[$clave], $valor),
            ])->values()->all(),
        );

        $inspeccion->defectos()->createMany(array_map(
            fn (array $defecto): array => ['defecto_id' => $defecto['defecto_id'], 'cantidad' => $defecto['cantidad']],
            $datos['defectos'] ?? [],
        ));

        if (! empty($datos['muestreo'])) {
            $this->guardarMuestreo($inspeccion, $datos['muestreo']);
        }

        if (! empty($datos['juntas'])) {
            $this->guardarJuntas($inspeccion, $datos['juntas']);
        }

        if (! empty($datos['pintura'])) {
            $this->guardarPintura($inspeccion, $datos['pintura']);
        }

        $this->guardarAdherencia($inspeccion, $datos['adherencia'] ?? null, $fotos, $fotosQuitar);
    }

    /**
     * De qué pieza se habla, con marca, lote y QR copiados como texto.
     *
     * En 1ª es la marca y su consecutivo; en 2ª y pintura, la pieza física, y la
     * marca se lee de ella en vez de confiar en la que mande el formulario.
     *
     * @param  array<string, mixed>  $datos
     * @return array{catalogo_id: int|null, concepto_id: int, prod_pieza_id: int|null, marca: string, lote: string|null, qr: string|null, consecutivo: int|null}
     */
    private function pieza(FaseTransformacion $fase, array $datos): array
    {
        if ($fase === FaseTransformacion::Primera) {
            $concepto = Concepto::query()->findOrFail($datos['concepto_id']);

            return [
                'catalogo_id' => $concepto->catalogo_id,
                'concepto_id' => $concepto->id,
                'prod_pieza_id' => null,
                'marca' => $concepto->marca,
                'lote' => $concepto->lote,
                'qr' => null,
                'consecutivo' => (int) $datos['consecutivo'],
            ];
        }

        $pieza = Pieza::query()->with('marca')->findOrFail($datos['prod_pieza_id']);

        return [
            'catalogo_id' => $pieza->catalogo_id,
            'concepto_id' => $pieza->concepto_id,
            'prod_pieza_id' => $pieza->id,
            'marca' => $pieza->marca->marca,
            'lote' => $pieza->marca->lote,
            'qr' => $pieza->qr,
            'consecutivo' => null,
        ];
    }

    /**
     * La misma pieza se reinspecciona tras repararse, y con este número se mide
     * el FPY: la primera inspección liberada es la que pasó a la primera.
     *
     * Se cuenta por el texto (marca y lote en 1ª, QR en las demás) y no por las
     * llaves a Producción: al versionar el catálogo la marca cambia de id, y
     * contar por id reiniciaría la cuenta de una pieza que ya se rechazó.
     *
     * @param  array{marca: string, lote: string|null, qr: string|null, consecutivo: int|null}  $pieza
     */
    private function siguienteNumero(int $obraId, FaseTransformacion $fase, ?Subetapa $subetapa, array $pieza): int
    {
        $consulta = Inspeccion::query()
            ->where('obra_id', $obraId)
            ->where('fase', $fase->value);

        if ($fase === FaseTransformacion::Primera) {
            $consulta->where('marca', $pieza['marca'])
                ->where('lote', $pieza['lote'])
                ->where('consecutivo', $pieza['consecutivo']);
        } else {
            $consulta->where('qr', $pieza['qr'])
                ->where('subetapa', $subetapa?->value);
        }

        return (int) $consulta->max('numero_inspeccion') + 1;
    }

    /**
     * Sólo las respuestas del formulario que se llenó, con lo que se deduce ya
     * escrito encima.
     *
     * Los barrenos de 1ª no se teclean. El dimensional de 2ª se deduce cuando
     * longitud y placas lo deciden; si no, vale lo que eligió el inspector.
     *
     * @param  array<string, mixed>  $enviadas
     * @param  Collection<string, PuntoInspeccion>  $puntos
     * @return array<string, string>
     */
    private function respuestas(array $enviadas, Collection $puntos): array
    {
        $respuestas = collect($enviadas)
            ->filter(fn (mixed $valor, string $clave): bool => $puntos->has($clave) && filled($valor))
            ->map(fn (mixed $valor): string => trim((string) $valor))
            ->all();

        if ($puntos->has('p1_bar')) {
            unset($respuestas['p1_bar']);
            $barrenos = $this->reglas->barrenos($respuestas['p1_posbar'] ?? null, $respuestas['p1_diam'] ?? null);

            if ($barrenos !== null) {
                $respuestas['p1_bar'] = $barrenos;
            }
        }

        if ($puntos->has('p2_dimok')) {
            $dimensional = $this->reglas->dimensional($respuestas['p2_long'] ?? null, $respuestas['p2_placas'] ?? null);

            if ($dimensional !== null) {
                $respuestas['p2_dimok'] = $dimensional;
            }
        }

        return $respuestas;
    }

    /**
     * Un contador en cero es cumplimiento: ninguna placa girada, ningún
     * elemento sin vestir. Los números sueltos sólo describen la pieza.
     *
     * @return array{resultado: string|null, valor_numerico: float|int|null, valor_texto: string|null}
     */
    private function filaDePunto(PuntoInspeccion $punto, string $valor): array
    {
        return match ($punto->tipo_dato) {
            TipoDatoPunto::Seleccion => [
                'resultado' => $punto->resultadoDe($valor)?->value,
                'valor_numerico' => null,
                'valor_texto' => $valor,
            ],
            TipoDatoPunto::Contador => [
                'resultado' => ((int) $valor > 0 ? ResultadoPunto::NoOk : ResultadoPunto::Ok)->value,
                'valor_numerico' => (int) $valor,
                'valor_texto' => null,
            ],
            TipoDatoPunto::Numero => ['resultado' => null, 'valor_numerico' => (float) $valor, 'valor_texto' => null],
            TipoDatoPunto::Texto => ['resultado' => null, 'valor_numerico' => null, 'valor_texto' => $valor],
        };
    }

    /**
     * El plan se recalcula con la tabla vigente y se guarda junto al lote: si
     * la norma cambia, el lote sigue diciendo con qué criterio se aceptó.
     *
     * @param  array<string, mixed>  $muestreo
     */
    private function guardarMuestreo(Inspeccion $inspeccion, array $muestreo): void
    {
        $plan = $this->aql->plan((int) $muestreo['tamano_lote'], NivelAql::from($muestreo['nivel']));
        $conformes = (int) $muestreo['conformes'];
        $rechazadas = (int) $muestreo['rechazadas'];
        $veredicto = $this->aql->veredicto($plan, $conformes, $rechazadas);

        $inspeccion->muestreo()->create([
            'tamano_lote' => $muestreo['tamano_lote'],
            'nivel' => $muestreo['nivel'],
            ...$plan,
            'conformes' => $conformes,
            'rechazadas' => $rechazadas,
            'veredicto' => $veredicto,
            // Qué se hizo con el lote sólo tiene sentido si se rechazó; en
            // blanco es «pendiente de decidir» y así lo marca el tablero.
            'disposicion' => $veredicto === VeredictoLote::Rechazado ? ($muestreo['disposicion'] ?? null) : null,
            'detalle_fallas' => $muestreo['detalle_fallas'] ?? null,
        ]);
    }

    /**
     * Cada junta con su intento: la misma junta de la misma pieza vuelve a
     * inspeccionarse tras repararse, y el intento dice cuántas veces falló.
     *
     * El resultado no se teclea. Un filete por debajo del nominal marca solo el
     * punto de perfil, y con que un punto salga con defecto la junta entera
     * queda con defecto.
     *
     * @param  list<array<string, mixed>>  $juntas
     */
    private function guardarJuntas(Inspeccion $inspeccion, array $juntas): void
    {
        $puntos = PuntoInspeccion::query()
            ->where('ambito', AmbitoPunto::Junta->value)
            ->where('activo', true)
            ->get()
            ->keyBy('clave');

        foreach ($juntas as $datos) {
            $tipo = TipoJunta::from($datos['tipo']);
            $identificador = mb_strtoupper(trim((string) $datos['identificador']));
            $requerido = $tipo === TipoJunta::Filete ? $this->numero($datos['espesor_requerido_mm'] ?? null) : null;
            $medido = $tipo === TipoJunta::Filete ? $this->numero($datos['espesor_medido_mm'] ?? null) : null;
            $cumple = $this->reglas->fileteCumple($requerido, $medido);

            $respuestas = collect($datos['puntos'] ?? [])
                ->filter(fn (mixed $valor, string $clave): bool => $puntos->has($clave) && filled($valor))
                ->map(fn (mixed $valor): string => trim((string) $valor));

            if ($cumple === false) {
                $respuestas[ReglasInspeccion::PUNTO_PERFIL] = 'Defecto';
            }

            $filas = $respuestas
                ->map(fn (string $valor, string $clave): array => [
                    'punto_id' => $puntos[$clave]->id,
                    'resultado' => $puntos[$clave]->resultadoDe($valor),
                ])
                ->filter(fn (array $fila): bool => $fila['resultado'] !== null)
                ->values();

            $conDefecto = $filas->contains(fn (array $fila): bool => $fila['resultado'] === ResultadoPunto::NoOk);

            $junta = $inspeccion->juntas()->create([
                'cordon_id' => $datos['cordon_id'] ?? null,
                'identificador' => $identificador,
                'tipo' => $tipo,
                'soldador_id' => $datos['soldador_id'] ?? null,
                'espesor_requerido_mm' => $requerido,
                'espesor_medido_mm' => $medido,
                'espesor_cumple' => $cumple,
                'es_empate' => (bool) ($datos['es_empate'] ?? false),
                'intento' => $this->siguienteIntento($inspeccion, $identificador),
                'resultado' => $conDefecto ? ResultadoJunta::ConDefecto : ResultadoJunta::Correcta,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $junta->puntos()->createMany($filas->all());
        }
    }

    private function siguienteIntento(Inspeccion $inspeccion, string $identificador): int
    {
        return (int) Junta::query()
            ->where('identificador', $identificador)
            ->where('inspeccion_id', '!=', $inspeccion->id)
            ->whereHas('inspeccion', fn ($consulta) => $consulta
                ->where('obra_id', $inspeccion->obra_id)
                ->where('qr', $inspeccion->qr)
                ->where('fase', FaseTransformacion::Segunda->value))
            ->max('intento') + 1;
    }

    /**
     * Sólo se guardan las lecturas de las mediciones a la vista; una columna
     * que el inspector quitó no va al reporte aunque tuviera números.
     *
     * @param  array<string, mixed>  $pintura
     */
    private function guardarPintura(Inspeccion $inspeccion, array $pintura): void
    {
        $visibles = (int) $pintura['mediciones_visibles'];
        $requerido = $this->numero($pintura['espesor_requerido_mils'] ?? null);

        $lecturas = collect($pintura['lecturas'] ?? [])
            ->values()
            ->mapWithKeys(fn (mixed $fila, int $indice): array => [$indice + 1 => array_values((array) $fila)])
            ->all();

        $resumen = $this->espesores->resumir($lecturas, $visibles, $requerido);

        $registro = $inspeccion->pintura()->create([
            'espesor_requerido_mils' => $requerido,
            'metodo' => $pintura['metodo'] ?? null,
            'area_m2' => $this->numero($pintura['area_m2'] ?? null),
            'mediciones_visibles' => $visibles,
            'promedio_mils' => $resumen['promedio'],
            'cumple' => $resumen['cumple'],
            'mediciones_bajas' => $resumen['bajas'] === [] ? null : $resumen['bajas'],
            'revision' => $pintura['revision'] ?? null,
            'accion' => $pintura['accion'] ?? null,
        ]);

        $filas = [];

        foreach ($lecturas as $medicion => $valores) {
            if ($medicion > $visibles) {
                continue;
            }

            foreach (array_slice($valores, 0, CalculadorEspesores::LECTURAS_POR_MEDICION) as $indice => $valor) {
                if (is_numeric($valor)) {
                    $filas[] = ['medicion' => $medicion, 'lectura' => $indice + 1, 'valor_mils' => (float) $valor];
                }
            }
        }

        $registro->lecturas()->createMany($filas);
    }

    /**
     * La prueba de adherencia es opcional. Si al corregir se quita, su
     * evidencia se va con ella; si se conserva, las fotos guardadas se quedan
     * salvo las que se marcaron para quitar.
     *
     * @param  array<string, mixed>|null  $adherencia
     * @param  list<UploadedFile>  $fotos
     * @param  list<int>  $fotosQuitar
     */
    private function guardarAdherencia(Inspeccion $inspeccion, ?array $adherencia, array $fotos, array $fotosQuitar): void
    {
        if (empty($adherencia)) {
            if ($existente = $inspeccion->adherencia()->first()) {
                $this->quitarFotos($existente->fotos);
                $existente->delete();
            }

            return;
        }

        $prueba = $inspeccion->adherencia()->updateOrCreate([], [
            'resultado' => $adherencia['resultado'] ?? null,
        ]);

        $tiras = [];

        // Una tira existe si se clasificó: la que no se cortó no se guarda, y
        // su método —que el formulario deja elegido— no significa nada.
        foreach (array_values($adherencia['tiras'] ?? []) as $indice => $tira) {
            if (filled($tira['clasificacion'] ?? null)) {
                $tiras[] = [
                    'orden' => $indice + 1,
                    'metodo' => $tira['metodo'],
                    'clasificacion' => $tira['clasificacion'],
                ];
            }
        }

        $prueba->tiras()->delete();
        $prueba->tiras()->createMany($tiras);

        $this->quitarFotos($prueba->fotos()->whereKey($fotosQuitar)->get());

        foreach ($fotos as $foto) {
            $prueba->fotos()->create([
                'nombre_original' => $foto->getClientOriginalName(),
                'path' => $foto->store("qal/adherencia/{$prueba->id}", 'public'),
                'mime' => $foto->getMimeType(),
                'size' => $foto->getSize(),
            ]);
        }
    }

    /**
     * Los archivos se borran cuando la transacción confirma: si algo falla
     * después, la fila vuelve y su archivo tiene que seguir ahí.
     *
     * @param  Collection<int, Media>  $fotos
     */
    private function quitarFotos(Collection $fotos): void
    {
        if ($fotos->isEmpty()) {
            return;
        }

        $rutas = $fotos->pluck('path')->all();
        Media::query()->whereKey($fotos->pluck('id')->all())->delete();

        DB::afterCommit(fn () => Storage::disk('public')->delete($rutas));
    }

    private function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }
}
