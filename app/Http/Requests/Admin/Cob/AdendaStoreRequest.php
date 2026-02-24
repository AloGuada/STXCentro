<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class AdendaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:aumento,reduccion,cambio_especificacion,ampliacion_plazo'],
            'descripcion' => ['required', 'string'],
            'monto_modificacion' => ['required', 'numeric', 'min:0'],
            'fecha' => ['nullable', 'date'],
            'estado' => ['required', 'string', 'in:borrador,en_revision,aprobada,rechazada'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser aumento, reducción, cambio de especificación o ampliación de plazo.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'monto_modificacion.required' => 'El monto de modificación es obligatorio.',
            'monto_modificacion.numeric' => 'El monto de modificación debe ser un número.',
            'monto_modificacion.min' => 'El monto de modificación debe ser mayor o igual a 0.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser borrador, en revisión, aprobada o rechazada.',
        ];
    }
}
