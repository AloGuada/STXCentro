<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class EntregaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.entregas.editar') ?? false;
    }

    /**
     * Sólo los datos de captura. Las cantidades, los precios y la factura ligada
     * quedan fuera a propósito: mueven saldo de partidas, presupuesto y estatus
     * de la factura, y para eso el camino es cancelar y volver a capturar.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_entrega' => ['required', 'date'],
            'recibido_por' => ['required', 'exists:usuarios,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'archivo' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_entrega.required' => 'La fecha de entrega es obligatoria.',
            'recibido_por.required' => 'Indica quién recibió el material.',
            'recibido_por.exists' => 'El usuario seleccionado no existe.',
            'archivo.max' => 'La evidencia no puede pasar de 10 MB.',
        ];
    }
}
