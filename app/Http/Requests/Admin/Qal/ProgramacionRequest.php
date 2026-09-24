<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Las notas del plan de una semana. Las piezas entran por su propio
 * formulario (`ProgramacionPiezaStoreRequest`).
 */
class ProgramacionRequest extends FormRequest
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
        return [
            'obra_id' => ['required', 'integer', Rule::exists('qal_obras', 'obra_id')],
            'fase' => ['required', Rule::in(['2', '3'])],
            'semana' => ['required', 'string', 'regex:/^\d{4}-S(0[1-9]|[1-4]\d|5[0-3])$/'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.exists' => 'La obra no está dada de alta en Calidad.',
            'fase.in' => 'Se programa 2ª (fabricación) o 3ª (pintura).',
            'semana.regex' => 'La semana va como 2026-S37.',
            'notas.max' => 'Las notas son demasiado largas.',
        ];
    }
}
