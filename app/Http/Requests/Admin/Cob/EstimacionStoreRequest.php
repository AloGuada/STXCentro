<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimacionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nivel' => ['required', Rule::in(['proyecto', 'obra', 'partida'])],
            'obra_id' => ['nullable', 'required_unless:nivel,proyecto', 'integer', 'exists:obras,id'],
            'partida_ids' => ['array', 'required_if:nivel,partida'],
            'partida_ids.*' => ['integer', 'exists:cob_partidas,id'],
            // El número es consecutivo del proyecto y lo asigna el servidor; aquí solo se ignora si llega.
            'folio' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'fecha_emision' => ['nullable', 'date'],
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
            'monto_estimado' => ['required', 'numeric', 'min:0'],
            'monto_total' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['required', 'string', 'max:3'],
            'comentarios' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folio.max' => 'El folio no debe exceder 255 caracteres.',
            'tipo.max' => 'El tipo no debe exceder 255 caracteres.',
            'monto_estimado.required' => 'El monto estimado es obligatorio.',
            'monto_estimado.numeric' => 'El monto estimado debe ser un número.',
            'monto_estimado.min' => 'El monto estimado debe ser mayor o igual a 0.',
            'monto_total.numeric' => 'El monto total debe ser un número.',
            'monto_total.min' => 'El monto total debe ser mayor o igual a 0.',
            'moneda.required' => 'La moneda es obligatoria.',
            'moneda.max' => 'La moneda no debe exceder 3 caracteres.',
        ];
    }
}
