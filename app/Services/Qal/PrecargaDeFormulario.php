<?php

namespace App\Services\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\TipoDatoPunto;
use App\Models\Media;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\InspeccionPunto;
use App\Models\Qal\Junta;
use App\Models\Qal\JuntaPunto;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\PinturaLectura;
use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Lo que la captura trae ya llenado cuando se abre para editar, reinspeccionar
 * o registrar otra entrega de un lote de accesorios.
 *
 * Traduce lo guardado al diccionario del formulario (`p1_defl`, `status`,
 * `ac_unid`…) y a su estado propio: juntas, rejilla de espesores, unidades
 * rechazadas. Se hace del lado del servidor para que el front no tenga que
 * saber cómo se guarda cada cosa.
 *
 * Editar sobrescribe lo que se abrió. Reinspeccionar abre una inspección nueva
 * de la misma pieza con sólo lo que la identifica: lo que se revisa se captura
 * otra vez, porque la pieza se reparó.
 */
class PrecargaDeFormulario
{
    private const ESTATUS = ['liberado' => 'Liberado', 'rechazado' => 'Rechazado', 'pendiente' => 'Pendiente'];

    private const SUBETAPAS = ['armado_vestido' => 'Armado-Vestido', 'soldado' => 'Soldado'];

    private const SUBTIPOS = ['perfil' => 'Perfil', 'placa' => 'Placa'];

    /** El mapeo guarda el resultado de cada punto, no el texto; éste es el texto de cada resultado. */
    private const RESPUESTA_JUNTA = ['ok' => 'OK', 'no_ok' => 'Defecto', 'no_aplica' => 'n/a'];

    /** La familia del modal de rechazo de accesorios que corresponde a cada ámbito. */
    private const FAMILIAS = [
        'soldadura' => 'soldadura',
        'accesorio_dimensional' => 'dimensional',
        'accesorio_barrenos' => 'barrenos',
        'accesorio_limpieza' => 'limpieza',
        'pintura' => 'pintura',
    ];

    public function __construct(private readonly FichaDePieza $fichas) {}

    /**
     * @return array<string, mixed>
     */
    public function deInspeccion(Inspeccion $inspeccion, bool $reinspeccion): array
    {
        $inspeccion->load([
            'concepto', 'pieza', 'puntos.punto', 'defectos.defecto', 'muestreo', 'juntas.puntos.punto',
            'pintura.lecturas', 'adherencia.tiras', 'adherencia.fotos',
        ]);

        $campos = [
            'fase' => $inspeccion->fase->value,
            'fecha' => $reinspeccion ? now()->toDateString() : $inspeccion->fecha->toDateString(),
            'obra' => (string) $inspeccion->obra_id,
            'p1_subtipo' => $inspeccion->subtipo ? self::SUBTIPOS[$inspeccion->subtipo->value] : 'Perfil',
            'p2_subetapa' => $inspeccion->subetapa ? self::SUBETAPAS[$inspeccion->subetapa->value] : '',
            'concepto' => $this->texto($inspeccion->concepto_id),
            'consec' => $this->texto($inspeccion->consecutivo),
            'cant' => $this->texto($inspeccion->cantidad_lote) ?: '1',
            'kg' => $this->texto($inspeccion->kg),
            'tipo' => $this->texto($inspeccion->tipo_pieza_id),
            'linea' => $this->texto($inspeccion->linea),
            'modulo' => $this->texto($inspeccion->modulo),
            'equipo' => $this->texto($inspeccion->equipo_id),
            'operador' => $this->texto($inspeccion->operador_id),
            'responsable' => $this->texto($inspeccion->responsable_id),
            'supervisor' => $this->texto($inspeccion->supervisor_pintura_id),
            'soldador' => $this->texto($inspeccion->soldador_id),
        ];

        if (! $reinspeccion) {
            $campos = [...$campos, ...$this->capturado($inspeccion)];
        }

        $defectos = $reinspeccion ? collect() : $inspeccion->defectos->groupBy(fn ($defecto) => $defecto->defecto->ambito->value);
        $muestreo = $reinspeccion ? null : $inspeccion->muestreo;
        $pintura = $reinspeccion ? null : $inspeccion->pintura;

        return [
            ...$this->vacia(),
            'modo' => $reinspeccion ? 'reinspeccionar' : 'editar',
            'titulo' => $reinspeccion
                ? "Reinspección de {$inspeccion->marca}: se captura de nuevo lo que se revisa; la inspección {$inspeccion->folio} se conserva."
                : "Editando la inspección {$inspeccion->folio}: al guardar se sobrescribe, no se crea otra.",
            'destino' => $reinspeccion
                ? ['metodo' => 'post', 'url' => route('admin.qal.inspecciones.store', [], false)]
                : ['metodo' => 'put', 'url' => route('admin.qal.inspecciones.update', $inspeccion, false)],
            'campos' => $campos,
            'pieza' => $inspeccion->pieza ? $this->fichas->de($inspeccion->pieza) : null,
            'marcaTexto' => $inspeccion->lote ? "{$inspeccion->marca} · lote {$inspeccion->lote}" : $inspeccion->marca,
            'juntas' => $reinspeccion ? [] : $inspeccion->juntas->map(fn (Junta $junta): array => [
                'junta' => $junta->identificador,
                'tipo' => $junta->tipo->etiqueta(),
                'soldador' => $this->texto($junta->soldador_id),
                'esEmpate' => $junta->es_empate,
                'cordonId' => $junta->cordon_id,
                'puntos' => $junta->puntos->mapWithKeys(fn (JuntaPunto $punto): array => [
                    $punto->punto->clave => self::RESPUESTA_JUNTA[$punto->resultado->value],
                ])->all(),
                'espesorRequerido' => $this->texto($junta->espesor_requerido_mm),
                'espesorMedido' => $this->texto($junta->espesor_medido_mm),
            ])->values()->all(),
            'lecturas' => $pintura ? $this->rejilla($pintura->lecturas) : null,
            'mediciones' => $pintura?->mediciones_visibles,
            'adherencia' => ! $reinspeccion && $inspeccion->adherencia !== null,
            'fotosGuardadas' => $reinspeccion || $inspeccion->adherencia === null
                ? []
                : $inspeccion->adherencia->fotos->map(fn (Media $foto): array => [
                    'id' => $foto->id,
                    'nombre' => $foto->nombre_original,
                    'url' => Storage::disk('public')->url($foto->path),
                    'esImagen' => str_starts_with((string) $foto->mime, 'image/'),
                ])->values()->all(),
            'defectosSoldadura' => ($defectos->get('soldadura') ?? collect())
                ->mapWithKeys(fn ($defecto): array => [$defecto->defecto->nombre => $defecto->cantidad])
                ->all(),
            'defectosPintura' => ($defectos->get('pintura') ?? collect())->map(fn ($defecto) => $defecto->defecto->nombre)->values()->all(),
            'muestreo' => $muestreo ? [
                'conformes' => $muestreo->conformes,
                'fallas' => $this->fallas($muestreo->detalle_fallas, $muestreo->rechazadas),
                'disposicion' => $muestreo->disposicion ?? '',
            ] : null,
        ];
    }

    /**
     * Editar una entrega, o reinspeccionarla. La reinspección rehace el
     * muestreo desde cero: las unidades son otras, porque las malas se
     * repararon o se separaron.
     *
     * @return array<string, mixed>
     */
    public function deSublote(Sublote $sublote, bool $reinspeccion): array
    {
        $sublote->load(['lote', 'defectos.defecto']);
        $lote = $sublote->lote;
        $grupo = $sublote->grupoId();

        $numero = $reinspeccion
            ? (int) Sublote::query()
                ->where(fn ($consulta) => $consulta->where('id', $grupo)->orWhere('sublote_origen_id', $grupo))
                ->max('numero_inspeccion') + 1
            : $sublote->numero_inspeccion;

        return [
            ...$this->vacia(),
            'modo' => $reinspeccion ? 'reinspeccionar' : 'editar',
            'titulo' => $reinspeccion
                ? "Reinspección del sublote de {$lote->marca} ({$sublote->unidades} unidades): el muestreo se rehace desde cero y la inspección anterior queda como historial."
                : "Editando la inspección {$sublote->numero_inspeccion} del sublote de {$lote->marca}: al guardar se sobrescribe.",
            'destino' => $reinspeccion
                ? ['metodo' => 'post', 'url' => route('admin.qal.accesorios.sublotes.store', [], false)]
                : ['metodo' => 'put', 'url' => route('admin.qal.accesorios.sublotes.update', $sublote, false)],
            'modoCaptura' => 'acc',
            'campos' => [
                // La etapa es parte de lo que se inspeccionó: corregir o volver a
                // mirar la entrega no la mueve de 2ª a 3ª.
                ...$this->camposDelLote($lote, $sublote->fase),
                'fecha' => $reinspeccion ? now()->toDateString() : $sublote->fecha->toDateString(),
                'linea' => $this->texto($sublote->linea),
                'modulo' => $this->texto($sublote->modulo),
                'responsable' => $this->texto($sublote->responsable_id),
                'soldador' => $this->texto($sublote->soldador_id),
                'ac_unid' => (string) $sublote->unidades,
                'ac_nivel' => $sublote->nivel->value,
                'obs' => $reinspeccion ? '' : $this->texto($sublote->observaciones),
            ],
            'acc' => [
                'conformes' => $reinspeccion ? 0 : $sublote->conformes,
                'rechazadas' => $reinspeccion ? [] : $this->unidadesRechazadas($sublote),
                'disposicion' => $reinspeccion ? '' : $this->texto($sublote->disposicion),
                'origenId' => $reinspeccion ? $grupo : null,
                'numero' => $numero,
            ],
        ];
    }

    /**
     * Otra entrega de un lote que ya existe: hereda los datos de la marca.
     *
     * @return array<string, mixed>
     */
    public function deLote(LoteAccesorio $lote, FaseTransformacion $fase = FaseTransformacion::Segunda): array
    {
        $avance = $lote->load('sublotes')->avance($fase);

        return [
            ...$this->vacia(),
            'modo' => 'nuevo',
            'titulo' => "Nueva entrega de {$lote->marca} en {$fase->value}: van {$avance['recibidas']} de {$lote->total_unidades} unidades en {$avance['sublotes']} sublote(s).",
            'destino' => ['metodo' => 'post', 'url' => route('admin.qal.accesorios.sublotes.store', [], false)],
            'modoCaptura' => 'acc',
            'campos' => [...$this->camposDelLote($lote, $fase), 'fecha' => now()->toDateString()],
            'acc' => ['conformes' => 0, 'rechazadas' => [], 'disposicion' => '', 'origenId' => null, 'numero' => 1],
        ];
    }

    /**
     * Las llaves que el front espera siempre, en blanco.
     *
     * @return array<string, mixed>
     */
    private function vacia(): array
    {
        return [
            'modoCaptura' => 'pieza',
            'pieza' => null,
            'marcaTexto' => '',
            'juntas' => [],
            'lecturas' => null,
            'mediciones' => null,
            'adherencia' => false,
            'fotosGuardadas' => [],
            'defectosSoldadura' => [],
            'defectosPintura' => [],
            'muestreo' => null,
            'acc' => null,
        ];
    }

    /**
     * Lo que el inspector contestó: estatus, puntos y los datos de muestreo,
     * espesores y adherencia que el formulario lleva en el diccionario.
     *
     * @return array<string, string>
     */
    private function capturado(Inspeccion $inspeccion): array
    {
        $campos = [
            'status' => self::ESTATUS[$inspeccion->estatus->value],
            'iv' => $this->texto($inspeccion->avance_iv),
            'is' => $this->texto($inspeccion->avance_is),
            'obs' => $this->texto($inspeccion->observaciones),
        ];

        foreach ($inspeccion->puntos as $respuesta) {
            $campos[$respuesta->punto->clave] = $this->valorDePunto($respuesta);
        }

        if ($muestreo = $inspeccion->muestreo) {
            $campos['p1_lote'] = (string) $muestreo->tamano_lote;
            $campos['p1_nivel'] = $muestreo->nivel->value;
        }

        if ($pintura = $inspeccion->pintura) {
            $campos['p3_req'] = $this->texto($pintura->espesor_requerido_mils);
            $campos['p3_metodo'] = $this->texto($pintura->metodo);
            $campos['p3_area'] = $this->texto($pintura->area_m2);
            $campos['p3_rev'] = $this->texto($pintura->revision);
            $campos['p3_accion'] = $this->texto($pintura->accion);
        }

        if ($adherencia = $inspeccion->adherencia) {
            $campos['p3_adhres'] = $this->texto($adherencia->resultado);

            // La tira 1 no lleva sufijo: son las claves del formulario anterior
            // y las que reconocen los inspectores.
            foreach ($adherencia->tiras as $tira) {
                $sufijo = $tira->orden === 1 ? '' : (string) $tira->orden;
                $campos["p3_adhmet{$sufijo}"] = $tira->metodo;
                $campos["p3_adhclas{$sufijo}"] = $tira->clasificacion;
            }
        }

        return $campos;
    }

    private function valorDePunto(InspeccionPunto $respuesta): string
    {
        return match ($respuesta->punto->tipo_dato) {
            TipoDatoPunto::Contador => (string) (int) $respuesta->valor_numerico,
            TipoDatoPunto::Numero => $this->texto($respuesta->valor_numerico),
            default => $this->texto($respuesta->valor_texto),
        };
    }

    /**
     * La rejilla de 15 × 3 del formulario, con cada lectura en su casilla.
     *
     * @param  Collection<int, PinturaLectura>  $lecturas
     * @return list<list<string>>
     */
    private function rejilla(Collection $lecturas): array
    {
        $rejilla = array_fill(0, CalculadorEspesores::MEDICIONES_MAX, array_fill(0, CalculadorEspesores::LECTURAS_POR_MEDICION, ''));

        foreach ($lecturas as $lectura) {
            $rejilla[$lectura->medicion - 1][$lectura->lectura - 1] = $this->texto($lectura->valor_mils);
        }

        return $rejilla;
    }

    /**
     * El detalle de las piezas que fallaron en el muestreo se guarda como texto
     * («#1 rebaba», un renglón por pieza); aquí vuelve a ser una lista con una
     * entrada por pieza rechazada.
     *
     * @return list<array{detalle: string}>
     */
    private function fallas(?string $detalle, int $rechazadas): array
    {
        $renglones = array_values(array_filter(
            array_map(fn (string $renglon): string => trim((string) preg_replace('/^#\d+\s*/', '', trim($renglon))), preg_split('/\R/', (string) $detalle) ?: []),
            fn (string $renglon): bool => $renglon !== '' && $renglon !== 'sin detalle',
        ));

        return array_map(
            fn (int $indice): array => ['detalle' => $renglones[$indice] ?? ''],
            $rechazadas > 0 ? range(0, $rechazadas - 1) : [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function camposDelLote(LoteAccesorio $lote, FaseTransformacion $fase = FaseTransformacion::Segunda): array
    {
        return [
            'fase' => $fase->value,
            'obra' => (string) $lote->obra_id,
            'ac_marca' => $lote->marca,
            'ac_desc' => $this->texto($lote->descripcion),
            'ac_total' => (string) $lote->total_unidades,
            'ac_kg' => $this->texto($lote->kg_unitario),
            'ac_elem' => $this->texto($lote->elementos_unitarios),
        ];
    }

    /**
     * Cada unidad rechazada con sus defectos repartidos por familia, como las
     * marca el modal de la captura.
     *
     * @return list<array{soldadura: list<string>, dimensional: list<string>, barrenos: list<string>, limpieza: bool, pintura: list<string>}>
     */
    private function unidadesRechazadas(Sublote $sublote): array
    {
        $unidades = [];

        for ($unidad = 1; $unidad <= $sublote->rechazadas; $unidad++) {
            $unidades[$unidad] = ['soldadura' => [], 'dimensional' => [], 'barrenos' => [], 'limpieza' => false, 'pintura' => []];
        }

        /** @var SubloteDefecto $defecto */
        foreach ($sublote->defectos as $defecto) {
            $familia = self::FAMILIAS[$defecto->defecto->ambito->value] ?? null;

            if ($familia === null || ! isset($unidades[$defecto->unidad])) {
                continue;
            }

            if ($familia === 'limpieza') {
                $unidades[$defecto->unidad]['limpieza'] = true;
            } else {
                $unidades[$defecto->unidad][$familia][] = $defecto->defecto->nombre;
            }
        }

        return array_values($unidades);
    }

    /**
     * Números sin ceros de relleno (`120.500` → `120.5`) y nulos en blanco:
     * el formulario muestra lo que se tecleó, no el formato de la columna.
     */
    private function texto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = (string) $valor;

        return is_numeric($texto) && str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }
}
