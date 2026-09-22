<?php

namespace App\Http\Requests\Admin\Qal;

use App\Models\Prod\Pieza;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Las piezas que se agregan al plan de una semana, con el grupo de trabajo que
 * las hace y el módulo donde las hace.
 */
class ProgramacionPiezaStoreRequest extends FormRequest
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
            'piezas' => ['required', 'array', 'min:1', 'max:500'],
            'piezas.*' => ['integer', 'distinct'],
            'grupo_trabajo_id' => ['required', 'integer', Rule::exists('prod_grupos_trabajo', 'id')->where('activo', true)],
            'modulo' => ['nullable', 'string', 'max:50'],
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
            'piezas.required' => 'Elige al menos una pieza.',
            'piezas.min' => 'Elige al menos una pieza.',
            'piezas.max' => 'Son demasiadas piezas para agregarlas de una vez.',
            'grupo_trabajo_id.required' => 'Elige el grupo de trabajo que las va a hacer.',
            'grupo_trabajo_id.exists' => 'Ese grupo de trabajo no está activo.',
            'modulo.max' => 'El módulo es demasiado largo: va como 1.2.',
        ];
    }

    /**
     * Las piezas son del catálogo vigente de la obra del plan.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $pedidas = collect($this->input('piezas'))->map(fn ($id): int => (int) $id);
                $validas = Pieza::query()
                    ->deCatalogoVigente()
                    ->where('activo', true)
                    ->whereKey($pedidas->all())
                    ->whereHas('catalogo', fn ($catalogo) => $catalogo->where('obra_id', $this->integer('obra_id')))
                    ->count();

                if ($validas !== $pedidas->count()) {
                    $validator->errors()->add('piezas', 'Hay piezas que no son del catálogo vigente de esta obra.');
                }
            },
        ];
    }
}
