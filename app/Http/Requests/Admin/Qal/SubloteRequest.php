<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\AmbitoDefecto;
use App\Enums\Qal\NivelAql;
use App\Models\Qal\Sublote;
use App\Services\Qal\CalculadorAql;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Una entrega de accesorios: los datos de la marca (del lote entero) y el
 * muestreo de esta entrega.
 *
 * Cada unidad rechazada trae los defectos por los que falló; con eso se cuentan
 * las rechazadas, así que no hay un número aparte que pueda contradecirlas.
 */
class SubloteRequest extends FormRequest
{
    /**
     * Las familias con las que se clasifica una unidad rechazada. Los
     * accesorios que fallan por soldadura usan la lista de soldadura.
     */
    private const AMBITOS = [
        AmbitoDefecto::Soldadura,
        AmbitoDefecto::AccesorioDimensional,
        AmbitoDefecto::AccesorioBarrenos,
        AmbitoDefecto::AccesorioLimpieza,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'marca' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'total_unidades' => ['required', 'integer', 'min:1', 'max:1000000'],
            'kg_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'elementos_unitarios' => ['nullable', 'integer', 'min:1', 'max:9999'],

            'unidades' => ['required', 'integer', 'min:1', 'lte:total_unidades'],
            'nivel' => ['required', Rule::enum(NivelAql::class)],
            'conformes' => ['required', 'integer', 'min:0'],
            'rechazadas' => ['nullable', 'array'],
            'rechazadas.*.defectos' => ['required', 'array', 'min:1'],
            'rechazadas.*.defectos.*' => [
                'integer',
                Rule::exists('qal_defectos', 'id')->whereIn('ambito', array_map(fn (AmbitoDefecto $ambito): string => $ambito->value, self::AMBITOS)),
            ],
            'disposicion' => ['nullable', 'string', 'max:120'],

            'linea' => ['nullable', 'string', 'max:10'],
            'modulo' => ['nullable', 'string', 'max:60'],
            'responsable_id' => ['nullable', 'integer', 'exists:qal_responsables,id'],
            'soldador_id' => ['nullable', 'integer', 'exists:qal_soldadores,id'],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            // Sólo al crear: editar no cambia de qué grupo es la inspección.
            'sublote_origen_id' => [Rule::prohibitedIf($this->sublote() !== null), 'nullable', 'integer', 'exists:qal_sublotes,id'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validarMuestra($validator);
                $this->validarOrigen($validator);
                $this->validarEdicion($validator);
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
            'marca.required' => 'Falta la marca del accesorio.',
            'total_unidades.required' => 'Falta el total de unidades de la marca.',
            'unidades.required' => 'Faltan las unidades de esta entrega.',
            'unidades.lte' => 'La entrega no puede traer más unidades que el total de la marca.',
            'fecha.before_or_equal' => 'La fecha de inspección no puede ser futura.',
            'rechazadas.*.defectos.required' => 'Cada unidad rechazada necesita al menos un defecto.',
            'rechazadas.*.defectos.min' => 'Cada unidad rechazada necesita al menos un defecto.',
            'rechazadas.*.defectos.*.exists' => 'El defecto no es de las familias de accesorios.',
            'sublote_origen_id.prohibited' => 'Editar una inspección no la mueve a otro sublote.',
        ];
    }

    public function sublote(): ?Sublote
    {
        $sublote = $this->route('sublote');

        return $sublote instanceof Sublote ? $sublote : null;
    }

    /** El sublote nunca mira más unidades de las que trajo la entrega. */
    private function validarMuestra(Validator $validator): void
    {
        $plan = app(CalculadorAql::class)->plan(
            $this->integer('unidades'),
            NivelAql::from((string) $this->input('nivel')),
            tope: $this->integer('unidades'),
        );

        $vistas = $this->integer('conformes') + count((array) $this->input('rechazadas', []));

        if ($plan !== null && $vistas > $plan['muestra']) {
            $validator->errors()->add('conformes', "Se marcaron {$vistas} unidades y la muestra de esta entrega es de {$plan['muestra']}.");
        }
    }

    /**
     * Se reinspecciona un sublote de la misma marca y sólo si su última
     * inspección no lo liberó: uno aceptado ya salió a obra.
     */
    private function validarOrigen(Validator $validator): void
    {
        if (! $this->filled('sublote_origen_id')) {
            return;
        }

        $origen = Sublote::query()->with('lote')->find($this->integer('sublote_origen_id'));

        if (! $this->esDelLote($origen?->lote?->obra_id, $origen?->lote?->marca)) {
            $validator->errors()->add('sublote_origen_id', 'La reinspección es de un sublote de otra marca u otra obra.');

            return;
        }

        $ultima = Sublote::query()
            ->where(fn ($consulta) => $consulta->where('id', $origen->grupoId())->orWhere('sublote_origen_id', $origen->grupoId()))
            ->orderByDesc('numero_inspeccion')
            ->first();

        if ($ultima->liberado()) {
            $validator->errors()->add('sublote_origen_id', 'Ese sublote ya está liberado: no hay nada que reinspeccionar.');
        }
    }

    private function validarEdicion(Validator $validator): void
    {
        $sublote = $this->sublote();

        if ($sublote !== null && ! $this->esDelLote($sublote->lote->obra_id, $sublote->lote->marca)) {
            $validator->errors()->add('marca', 'Editar no cambia la marca ni la obra: si se capturó en la marca equivocada, bórralo y captúralo en la otra.');
        }
    }

    private function esDelLote(?int $obraId, ?string $marca): bool
    {
        return $obraId === $this->integer('obra_id')
            && $marca === mb_strtoupper(trim((string) $this->input('marca')));
    }
}
