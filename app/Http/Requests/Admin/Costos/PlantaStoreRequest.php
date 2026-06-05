<?php

namespace App\Http\Requests\Admin\Costos;

use App\Models\Obra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PlantaStoreRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'El nombre del proyecto de planta es obligatorio.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (Obra::where('es_planta', true)->exists()) {
                $validator->errors()->add('descripcion', 'Ya existe un proyecto de planta. Solo puede haber uno.');
            }
        });
    }
}
