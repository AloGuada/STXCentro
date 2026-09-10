<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que vuelve. Los renglones son de resguardo, no de vale: la persona
 * entrega lo que trae, aunque venga de varios préstamos.
 */
class DevolucionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.devoluciones.crear') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'recibido_por' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'renglones' => ['required', 'array', 'min:1'],
            'renglones.*.detalle_id' => ['required', 'integer', 'exists:alm_prestamo_detalle,id', 'distinct'],
            'renglones.*.cantidad' => ['nullable', 'numeric', 'gt:0'],
            'renglones.*.condicion_retorno' => ['nullable', 'string', 'max:255'],
            'renglones.*.en_reparacion' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Indica cuándo entregó.',
            'fecha.before_or_equal' => 'Nada se devuelve antes de que ocurra: la fecha no puede ser futura.',
            'renglones.required' => 'Palomea al menos un renglón que vuelve.',
            'renglones.*.detalle_id.distinct' => 'Un renglón viene dos veces.',
        ];
    }
}
