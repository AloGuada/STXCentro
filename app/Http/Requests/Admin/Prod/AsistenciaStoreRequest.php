<?php

namespace App\Http\Requests\Admin\Prod;

use App\Enums\Prod\EstadoAsistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsistenciaStoreRequest extends FormRequest
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
            'marcas' => ['required', 'array', 'min:1'],
            'marcas.*.grupo_empleado_id' => ['required', 'exists:prod_grupo_empleados,id'],
            'marcas.*.fecha' => ['required', 'date'],
            'marcas.*.estado' => ['required', Rule::in(EstadoAsistencia::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marcas.required' => 'No hay asistencia que guardar.',
        ];
    }
}
