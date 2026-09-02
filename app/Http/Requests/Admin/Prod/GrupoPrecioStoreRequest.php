<?php

namespace App\Http\Requests\Admin\Prod;

use App\Enums\Prod\TipoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrupoPrecioStoreRequest extends FormRequest
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
            'obra_id' => ['required', 'exists:obras,id'],
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo_pago' => ['required', Rule::enum(TipoPago::class)],
            // Una tarifa por proceso: procesoId => precio por kilo. Los procesos
            // que la obra no paga se descartan al guardar. Solo aplica si el
            // grupo paga por kilo.
            'precios' => ['nullable', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0'],
            // Pasos con precio fijo por pieza. Solo aplica si el grupo paga por
            // subproceso; el nombre es la identidad del paso dentro del proceso.
            'subprocesos' => ['nullable', 'array'],
            'subprocesos.*.id' => ['nullable', 'integer', 'exists:prod_grupo_precio_subprocesos,id'],
            'subprocesos.*.proceso_id' => ['required_with:subprocesos', 'integer', 'exists:prod_procesos,id'],
            'subprocesos.*.nombre' => ['required_with:subprocesos', 'string', 'max:255'],
            'subprocesos.*.precio' => ['required_with:subprocesos', 'numeric', 'min:0'],
            'subprocesos.*.orden' => ['nullable', 'integer', 'min:0'],
            'subprocesos.*.activo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obra_id.required' => 'La obra es obligatoria.',
            'obra_id.exists' => 'La obra seleccionada no existe.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'precios.*.min' => 'El precio por kilo no puede ser negativo.',
            'tipo_pago.required' => 'Elige si el grupo paga por kilo o por subproceso.',
            'subprocesos.*.nombre.required_with' => 'Cada subproceso necesita un nombre.',
            'subprocesos.*.proceso_id.required_with' => 'Cada subproceso necesita un proceso.',
            'subprocesos.*.precio.required_with' => 'Cada subproceso necesita un precio.',
            'subprocesos.*.precio.min' => 'El precio del subproceso no puede ser negativo.',
        ];
    }
}
