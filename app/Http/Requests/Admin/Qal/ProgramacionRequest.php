<?php

namespace App\Http\Requests\Admin\Qal;

use App\Services\Qal\LectorDeProgramacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El plan de una semana tal como se pega: la lista de marcas, las bajas con su
 * motivo y las notas. El texto se lee al guardar con `LectorDeProgramacion`.
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
            'marcas' => ['nullable', 'string', 'max:20000'],
            'bajas' => ['nullable', 'string', 'max:10000'],
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
            'marcas.max' => 'La lista de marcas es demasiado larga para una semana.',
        ];
    }

    /**
     * Una baja sin motivo no se guarda: es lo que explica por qué la semana
     * trae menos piezas de las programadas, y sin él se lee como incumplimiento
     * del taller.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sinMotivo = collect(app(LectorDeProgramacion::class)->bajas((string) $this->input('bajas', '')))
                    ->whereNull('motivo')
                    ->pluck('marca');

                if ($sinMotivo->isNotEmpty()) {
                    $validator->errors()->add(
                        'bajas',
                        'Cada baja lleva su motivo, como «MARCA: motivo». Falta en: '.$sinMotivo->implode(', ').'.',
                    );
                }
            },
        ];
    }
}
