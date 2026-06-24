<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoEstimacionRequest extends FormRequest
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
            'estado' => ['required', 'string', 'in:ingresada,autorizada,facturada,pago_parcial,pagado'],
            'fecha_cambio' => ['nullable', 'date'],
            'folio' => ['nullable', 'string', 'max:255'],
            'comentario' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
