<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelar unidades de una partida de la orden de compra. El tope contra lo
 * pedido y lo recibido lo revisa {@see \App\Services\Costos\CanceladorDeUnidades},
 * que es quien conoce el saldo.
 */
class CancelarUnidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.ordenes-compra.cancelar') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'numeric', 'min:0.0001'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.min' => 'La cantidad a cancelar debe ser mayor que cero.',
            'motivo.required' => 'Explique por qué se cancelan esas unidades.',
            'motivo.min' => 'El motivo debe explicar por qué ya no se va a surtir (al menos 10 caracteres).',
        ];
    }
}
