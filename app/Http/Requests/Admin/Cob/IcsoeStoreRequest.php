<?php

namespace App\Http\Requests\Admin\Cob;

use App\Enums\Cob\IcsoeMetodo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IcsoeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cob.icsoe.crear') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metodo' => ['required', Rule::in(IcsoeMetodo::valores())],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'superficie_m2' => ['nullable', 'required_if:metodo,superficie', 'numeric', 'min:0.01'],
            'costo_m2' => ['nullable', 'required_if:metodo,superficie', 'numeric', 'min:0.01'],
            'porcentaje_mo' => ['nullable', 'required_if:metodo,porcentaje', 'numeric', 'between:0.01,100'],
            'prima_riesgo' => ['required', 'numeric', 'between:0,100'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'superficie_m2.required_if' => 'Captura la superficie de construcción para estimar por metros cuadrados.',
            'costo_m2.required_if' => 'Captura el costo por m² publicado en el DOF.',
            'porcentaje_mo.required_if' => 'Captura el porcentaje de mano de obra a acumular.',
            'fecha_fin.after_or_equal' => 'La fecha de término no puede ser anterior a la de inicio.',
        ];
    }
}
