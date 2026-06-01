<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class DevolucionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.devoluciones.crear') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'entrega_detalle_id' => ['required', 'exists:costos_entrega_detalle,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'fecha' => ['required', 'date'],
            'evidencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'motivo.min' => 'Indica un motivo claro de la devolución.',
        ];
    }
}
