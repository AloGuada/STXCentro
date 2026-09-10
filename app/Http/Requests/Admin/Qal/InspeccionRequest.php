<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\NivelAql;
use App\Enums\Qal\Subetapa;
use App\Enums\Qal\SubtipoPrimera;
use App\Enums\Qal\TipoJunta;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\PuntoInspeccion;
use App\Services\Qal\CalculadorAql;
use App\Services\Qal\CalculadorEspesores;
use App\Services\Qal\ReglasInspeccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La captura de una inspección: cabecera, puntos y lo propio de cada fase.
 *
 * Cada fase trae sus secciones y ninguna otra: el muestreo es de 1ª, las juntas
 * de 2ª en soldado, los espesores y la adherencia de pintura. Una sección que no
 * toca se rechaza en vez de ignorarse, porque guardarla a medias sería guardar
 * algo que el inspector no vio en pantalla.
 *
 * La pieza se referencia distinto según la fase: en 1ª por la marca y el
 * consecutivo, en 2ª y pintura por la pieza física que se escaneó.
 */
class InspeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fase = $this->fase();
        $primera = $fase === FaseTransformacion::Primera;
        $segunda = $fase === FaseTransformacion::Segunda;
        $tercera = $fase === FaseTransformacion::Tercera;
        $soldado = $segunda && $this->input('subetapa') === Subetapa::Soldado->value;
        $ambitos = $this->ambitosDeDefecto($fase, $soldado);

        return [
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'fase' => ['required', Rule::enum(FaseTransformacion::class)],
            'subetapa' => [Rule::requiredIf($segunda), 'nullable', Rule::enum(Subetapa::class)],
            'subtipo' => [Rule::requiredIf($primera), 'nullable', Rule::enum(SubtipoPrimera::class)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],

            'concepto_id' => [Rule::requiredIf($primera), 'nullable', 'integer', 'exists:conceptos,id'],
            'consecutivo' => [Rule::requiredIf($primera), 'nullable', 'integer', 'min:1'],
            'cantidad_lote' => ['nullable', 'integer', 'min:1'],
            'prod_pieza_id' => [Rule::requiredIf($fase !== null && ! $primera), 'nullable', 'integer', 'exists:prod_piezas,id'],

            'kg' => ['required', 'numeric', 'min:0', 'max:999999'],
            'folio_strumis' => ['nullable', 'string', 'max:40'],
            'tipo_pieza_id' => ['nullable', 'integer', 'exists:qal_tipos_pieza,id'],
            'linea' => ['nullable', 'string', 'max:10'],
            'modulo' => ['nullable', 'string', 'max:60'],
            'equipo_id' => ['nullable', 'integer', 'exists:qal_equipos,id'],
            'operador_id' => ['nullable', 'integer', 'exists:qal_operadores,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:qal_responsables,id'],
            'supervisor_pintura_id' => ['nullable', 'integer', 'exists:qal_supervisores_pintura,id'],
            'soldador_id' => ['nullable', 'integer', 'exists:qal_soldadores,id'],
            'estatus' => ['required', Rule::enum(EstatusInspeccion::class)],
            'avance_iv' => ['nullable', Rule::in(['Ok', 'X', 'n/a'])],
            'avance_is' => ['nullable', Rule::in(['Ok', 'X', 'n/a'])],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            // Clave del punto → respuesta, con las mismas claves del formato.
            'puntos' => ['nullable', 'array'],
            'puntos.*' => ['nullable', 'string', 'max:160'],

            'defectos' => [Rule::prohibitedIf($ambitos === []), 'nullable', 'array'],
            'defectos.*.defecto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('qal_defectos', 'id')->whereIn('ambito', array_map(fn (AmbitoDefecto $ambito): string => $ambito->value, $ambitos)),
            ],
            'defectos.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],

            'muestreo' => [Rule::prohibitedIf(! $primera), 'nullable', 'array'],
            'muestreo.tamano_lote' => ['required_with:muestreo', 'integer', 'min:1', 'max:100000'],
            'muestreo.nivel' => ['required_with:muestreo', Rule::enum(NivelAql::class)],
            'muestreo.conformes' => ['required_with:muestreo', 'integer', 'min:0'],
            'muestreo.rechazadas' => ['required_with:muestreo', 'integer', 'min:0'],
            'muestreo.disposicion' => ['nullable', 'string', 'max:120'],
            'muestreo.detalle_fallas' => ['nullable', 'string', 'max:2000'],

            'pintura' => [Rule::prohibitedIf(! $tercera), 'nullable', 'array'],
            'pintura.espesor_requerido_mils' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'pintura.metodo' => ['nullable', 'string', 'max:60'],
            'pintura.area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pintura.mediciones_visibles' => [
                'required_with:pintura',
                'integer',
                'between:'.CalculadorEspesores::MEDICIONES_MIN.','.CalculadorEspesores::MEDICIONES_MAX,
            ],
            'pintura.revision' => ['nullable', Rule::in(['R1', 'R2', 'R3'])],
            'pintura.accion' => ['nullable', Rule::in(['A', 'R', 'RM'])],
            'pintura.lecturas' => ['nullable', 'array', 'max:'.CalculadorEspesores::MEDICIONES_MAX],
            'pintura.lecturas.*' => ['nullable', 'array', 'max:'.CalculadorEspesores::LECTURAS_POR_MEDICION],
            'pintura.lecturas.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],

            'adherencia' => [Rule::prohibitedIf(! $tercera), 'nullable', 'array'],
            'adherencia.metodo' => ['required_with:adherencia', Rule::in(['A', 'B'])],
            'adherencia.resultado' => ['nullable', Rule::in(['Aceptado', 'Rechazado'])],
            'adherencia.tiras' => ['nullable', 'array', 'max:3'],
            'adherencia.tiras.*' => ['nullable', 'string', 'regex:/^[0-5][AB]$/'],
            'fotos' => ['nullable', 'array', 'max:12'],
            // Fotos de la tablet, o el escaneo en PDF cuando la prueba se hizo
            // en papel.
            'fotos.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            // Al corregir: la evidencia guardada que se retira.
            'fotos_quitar' => ['nullable', 'array'],
            'fotos_quitar.*' => ['integer'],

            'juntas' => [Rule::prohibitedIf(! $soldado), 'nullable', 'array', 'max:300'],
            'juntas.*.identificador' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'juntas.*.tipo' => ['required', Rule::enum(TipoJunta::class)],
            'juntas.*.soldador_id' => ['nullable', 'integer', 'exists:qal_soldadores,id'],
            'juntas.*.espesor_requerido_mm' => ['nullable', 'numeric', 'gt:0', 'max:200'],
            'juntas.*.espesor_medido_mm' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'juntas.*.es_empate' => ['nullable', 'boolean'],
            'juntas.*.observaciones' => ['nullable', 'string', 'max:500'],
            'juntas.*.puntos' => ['nullable', 'array'],
            'juntas.*.puntos.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Lo que cruza campos y no cabe en una regla: que la pieza sea de la obra,
     * que cada respuesta exista en su punto, que la muestra no se exceda.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validarIdentidad($validator);
                $this->validarPieza($validator);
                $this->validarEstatus($validator);
                $this->validarPuntos($validator);
                $this->validarJuntas($validator);
                $this->validarMuestreo($validator);
                $this->validarAdherencia($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.required' => 'Falta la obra.',
            'fase.required' => 'Falta la transformación.',
            'subetapa.required' => 'Elige la sub-etapa: Armado/Vestido o Soldado.',
            'subtipo.required' => 'Elige si es perfil o placa.',
            'fecha.before_or_equal' => 'La fecha de inspección no puede ser futura.',
            'concepto_id.required' => 'Falta la marca de la pieza.',
            'consecutivo.required' => 'Falta el número de pieza dentro del lote.',
            'prod_pieza_id.required' => 'Escanea el QR de la pieza o teclea su código.',
            'kg.required' => 'Falta el peso de la pieza (kg).',
            'estatus.required' => 'Elige el status de la pieza.',
            'defectos.prohibited' => 'Esta inspección no lleva defectos de catálogo.',
            'defectos.*.defecto_id.exists' => 'El defecto no es de la lista de esta etapa.',
            'defectos.*.defecto_id.distinct' => 'Un defecto va una sola vez, con su cantidad.',
            'muestreo.prohibited' => 'El muestreo de lote es sólo de 1ª.',
            'pintura.prohibited' => 'Los espesores son sólo de pintura.',
            'adherencia.prohibited' => 'La prueba de adherencia es sólo de pintura.',
            'juntas.prohibited' => 'Las juntas se mapean sólo en 2ª, en soldado.',
            'juntas.*.identificador.required' => 'Cada junta necesita su número.',
            'juntas.*.identificador.distinct' => 'Hay dos juntas con el mismo número.',
            'juntas.*.tipo.required' => 'Cada junta necesita su tipo: filete o ranura.',
            'pintura.mediciones_visibles.between' => 'Van de 5 a 15 mediciones de espesor.',
            'adherencia.tiras.*.regex' => 'La clasificación de la tira va de 5A a 0A o de 5B a 0B.',
            'fotos.*.mimes' => 'La evidencia de adherencia va en foto (JPG, PNG, WebP) o PDF.',
            'fotos.*.max' => 'Cada archivo de evidencia puede pesar hasta 10 MB.',
        ];
    }

    public function fase(): ?FaseTransformacion
    {
        return FaseTransformacion::tryFrom((string) $this->input('fase'));
    }

    public function subetapa(): ?Subetapa
    {
        return $this->fase() === FaseTransformacion::Segunda
            ? Subetapa::tryFrom((string) $this->input('subetapa'))
            : null;
    }

    public function subtipo(): ?SubtipoPrimera
    {
        return $this->fase() === FaseTransformacion::Primera
            ? SubtipoPrimera::tryFrom((string) $this->input('subtipo'))
            : null;
    }

    /**
     * Qué lista de defectos lee cada formulario: soldadura en 2ª soldado,
     * pintura en 3ª. 1ª y armado no marcan defectos de catálogo; lo que falla
     * ahí se contesta en sus puntos.
     *
     * @return list<AmbitoDefecto>
     */
    private function ambitosDeDefecto(?FaseTransformacion $fase, bool $soldado): array
    {
        return match (true) {
            $soldado => [AmbitoDefecto::Soldadura],
            $fase === FaseTransformacion::Tercera => [AmbitoDefecto::Pintura],
            default => [],
        };
    }

    /**
     * Al corregir no cambia de qué pieza ni de qué etapa es la inspección:
     * folio y número de inspección dependen de eso. Si se capturó en la pieza
     * equivocada, se borra y se captura de nuevo.
     */
    private function validarIdentidad(Validator $validator): void
    {
        $inspeccion = $this->route('inspeccion');

        if (! $inspeccion instanceof Inspeccion) {
            return;
        }

        $mismaPieza = $inspeccion->fase === FaseTransformacion::Primera
            ? (int) $inspeccion->concepto_id === $this->integer('concepto_id') && (int) $inspeccion->consecutivo === $this->integer('consecutivo')
            : (int) $inspeccion->prod_pieza_id === $this->integer('prod_pieza_id');

        $misma = (int) $inspeccion->obra_id === $this->integer('obra_id')
            && $inspeccion->fase === $this->fase()
            && $inspeccion->subetapa === $this->subetapa()
            && $mismaPieza;

        if (! $misma) {
            $validator->errors()->add('fase', 'Al corregir no cambian la obra, la transformación, la sub-etapa ni la pieza: si se capturó mal, bórrala y captúrala de nuevo.');
        }
    }

    private function validarPieza(Validator $validator): void
    {
        $obraId = $this->integer('obra_id');

        if ($this->fase() === FaseTransformacion::Primera) {
            $concepto = Concepto::query()->find($this->integer('concepto_id'));

            if ($concepto?->obra_id !== $obraId) {
                $validator->errors()->add('concepto_id', 'La marca no es de la obra elegida.');
            }

            if ($this->filled('cantidad_lote') && $this->integer('consecutivo') > $this->integer('cantidad_lote')) {
                $validator->errors()->add('consecutivo', 'El número de pieza no puede ser mayor que las piezas del lote.');
            }

            return;
        }

        $pieza = Pieza::query()->with('catalogo:id,obra_id')->find($this->integer('prod_pieza_id'));

        if ($pieza?->catalogo?->obra_id !== $obraId) {
            $validator->errors()->add('prod_pieza_id', 'La pieza escaneada no es de la obra elegida.');
        }
    }

    private function validarEstatus(Validator $validator): void
    {
        $fase = $this->fase();
        $estatus = EstatusInspeccion::from((string) $this->input('estatus'));

        if ($fase !== null && ! app(ReglasInspeccion::class)->admiteEstatus($fase, $this->subetapa(), $estatus)) {
            $validator->errors()->add('estatus', 'En Armado/Vestido la pieza no se libera: queda pendiente o rechazada.');
        }
    }

    /**
     * Las claves que no son de este formulario no se validan: el registrador
     * sólo guarda las del formulario, y el front conserva en memoria lo que se
     * capturó antes de cambiar de sub-etapa.
     */
    private function validarPuntos(Validator $validator): void
    {
        $fase = $this->fase();

        if ($fase === null) {
            return;
        }

        $puntos = PuntoInspeccion::query()
            ->paraFormulario($fase, $this->subetapa(), $this->subtipo())
            ->get()
            ->keyBy('clave');

        foreach ((array) $this->input('puntos', []) as $clave => $valor) {
            $punto = $puntos->get($clave);

            if ($punto === null || blank($valor)) {
                continue;
            }

            if (! $punto->admite(trim((string) $valor))) {
                $validator->errors()->add("puntos.{$clave}", "«{$valor}» no es una respuesta de «{$punto->etiqueta}».");
            }
        }

        foreach ($puntos->where('obligatorio', true) as $punto) {
            if (blank($this->input("puntos.{$punto->clave}"))) {
                $validator->errors()->add("puntos.{$punto->clave}", "Falta «{$punto->etiqueta}».");
            }
        }
    }

    private function validarJuntas(Validator $validator): void
    {
        $juntas = (array) $this->input('juntas', []);

        if ($juntas === []) {
            return;
        }

        $puntos = PuntoInspeccion::query()
            ->where('ambito', AmbitoPunto::Junta->value)
            ->where('activo', true)
            ->get()
            ->keyBy('clave');

        foreach ($juntas as $indice => $junta) {
            foreach ((array) ($junta['puntos'] ?? []) as $clave => $valor) {
                if (blank($valor)) {
                    continue;
                }

                $punto = $puntos->get($clave);

                if ($punto === null || ! $punto->admite(trim((string) $valor))) {
                    $validator->errors()->add("juntas.{$indice}.puntos.{$clave}", "La junta {$junta['identificador']} trae una respuesta que no existe en el mapeo.");
                }
            }
        }
    }

    private function validarMuestreo(Validator $validator): void
    {
        if (! $this->filled('muestreo')) {
            return;
        }

        $plan = app(CalculadorAql::class)->plan(
            $this->integer('muestreo.tamano_lote'),
            NivelAql::from((string) $this->input('muestreo.nivel')),
        );

        $vistas = $this->integer('muestreo.conformes') + $this->integer('muestreo.rechazadas');

        if ($plan !== null && $vistas > $plan['muestra']) {
            $validator->errors()->add('muestreo.conformes', "Se marcaron {$vistas} piezas y la muestra de este lote es de {$plan['muestra']}.");
        }
    }

    private function validarAdherencia(Validator $validator): void
    {
        if ($this->hasFile('fotos') && ! $this->filled('adherencia')) {
            $validator->errors()->add('fotos', 'Las fotos son evidencia de la prueba de adherencia: ábrela antes de adjuntarlas.');
        }

        $metodo = (string) $this->input('adherencia.metodo');

        foreach ((array) $this->input('adherencia.tiras', []) as $indice => $clasificacion) {
            if (filled($clasificacion) && ! str_ends_with((string) $clasificacion, $metodo)) {
                $validator->errors()->add("adherencia.tiras.{$indice}", 'La tira '.($indice + 1)." no es del método {$metodo}.");
            }
        }
    }
}
