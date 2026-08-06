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
     * Datos de captura más la factura ligada (opcional: se puede ligar,
     * cambiar o desligar aquí, que es el error de dedazo más común al recibir). Las cantidades y los precios siguen fuera a propósito:
     * mueven saldo de partidas y presupuesto, y para eso el camino es cancelar
     * y volver a capturar.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_entrega' => ['required', 'date'],
            // `uuid` antes de `exists`: en PostgreSQL comparar un texto suelto
            // contra una columna uuid revienta con 22P02 (500) en vez de fallar
            // la validación. El guardia lo corta antes de tocar la base.
            'recibido_por' => ['required', 'uuid', 'exists:usuarios,id'],
            'factura_id' => ['nullable', 'integer', 'exists:costos_facturas,id'],
            'completa_factura' => ['nullable', 'boolean'],
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
            'factura_id.exists' => 'La factura seleccionada no existe.',
            'archivo.max' => 'La evidencia no puede pasar de 10 MB.',
        ];
    }
}
