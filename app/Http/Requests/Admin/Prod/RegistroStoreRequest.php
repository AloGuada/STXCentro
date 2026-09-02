<?php

namespace App\Http\Requests\Admin\Prod;

use Illuminate\Foundation\Http\FormRequest;

class RegistroStoreRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'piezas' => ['required', 'array', 'min:1'],
            'piezas.*' => ['integer', 'exists:prod_piezas,id'],
            'proceso_id' => ['required', 'exists:prod_procesos,id'],
            // Obligatorio o prohibido segun la modalidad del grupo de precios de
            // la pieza, que aqui todavia no se conoce: lo resuelve el controlador
            // pieza por pieza con ModalidadDePago.
            'subproceso_id' => ['nullable', 'integer', 'exists:prod_grupo_precio_subprocesos,id'],
            'grupo_trabajo_id' => ['required', 'exists:prod_grupos_trabajo,id'],
            'porcentaje' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'piezas.required' => 'Selecciona al menos una pieza.',
            'piezas.min' => 'Selecciona al menos una pieza.',
            'piezas.*.exists' => 'Alguna de las piezas seleccionadas ya no existe.',
            'proceso_id.required' => 'El proceso es obligatorio.',
            'proceso_id.exists' => 'El proceso seleccionado no existe.',
            'subproceso_id.exists' => 'El subproceso seleccionado no existe.',
            'grupo_trabajo_id.required' => 'El grupo de trabajo es obligatorio.',
            'grupo_trabajo_id.exists' => 'El grupo de trabajo seleccionado no existe.',
            'porcentaje.min' => 'El porcentaje debe ser mayor a 0.',
            'porcentaje.max' => 'El porcentaje no puede pasar de 100.',
        ];
    }
}
