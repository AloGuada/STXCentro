<?php

namespace App\Http\Requests\Admin\Cob;

use Illuminate\Foundation\Http\FormRequest;

class ObraCobUpdateRequest extends FormRequest
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
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'tipo_contrato' => ['nullable', 'string', 'max:255'],
            'monto' => ['nullable', 'numeric', 'min:0'],
            'monto_iva' => ['nullable', 'numeric', 'min:0'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'garantia' => ['nullable', 'numeric', 'min:0'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'porcentaje_fabricacion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'porcentaje_montaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'porcentaje_otros' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descripcion_otros' => ['nullable', 'string', 'max:255'],
            'activa' => ['required', 'boolean'],
            'porcentaje_obra' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.min' => 'El monto debe ser mayor o igual a 0.',
            'monto_iva.numeric' => 'El monto IVA debe ser un número.',
            'monto_iva.min' => 'El monto IVA debe ser mayor o igual a 0.',
            'anticipo.numeric' => 'El anticipo debe ser un número.',
            'anticipo.min' => 'El anticipo debe ser mayor o igual a 0.',
            'garantia.numeric' => 'La garantía debe ser un número.',
            'garantia.min' => 'La garantía debe ser mayor o igual a 0.',
            'peso.numeric' => 'El peso debe ser un número.',
            'peso.min' => 'El peso debe ser mayor o igual a 0.',
            'porcentaje_fabricacion.numeric' => 'El porcentaje de fabricación debe ser un número.',
            'porcentaje_fabricacion.min' => 'El porcentaje de fabricación debe ser mayor o igual a 0.',
            'porcentaje_fabricacion.max' => 'El porcentaje de fabricación no puede ser mayor a 100.',
            'porcentaje_montaje.numeric' => 'El porcentaje de montaje debe ser un número.',
            'porcentaje_montaje.min' => 'El porcentaje de montaje debe ser mayor o igual a 0.',
            'porcentaje_montaje.max' => 'El porcentaje de montaje no puede ser mayor a 100.',
            'porcentaje_otros.numeric' => 'El porcentaje de otros debe ser un número.',
            'porcentaje_otros.min' => 'El porcentaje de otros debe ser mayor o igual a 0.',
            'porcentaje_otros.max' => 'El porcentaje de otros no puede ser mayor a 100.',
            'activa.required' => 'El campo activa es obligatorio.',
            'porcentaje_obra.numeric' => 'El porcentaje de obra debe ser un número.',
            'porcentaje_obra.min' => 'El porcentaje de obra debe ser mayor o igual a 0.',
            'porcentaje_obra.max' => 'El porcentaje de obra no puede ser mayor a 100.',
        ];
    }
}
