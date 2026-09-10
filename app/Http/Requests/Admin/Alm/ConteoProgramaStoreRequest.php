<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;

class ConteoProgramaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.conteos.crear') ?? false;
    }

    /**
     * Lo que se elige en el modal: desde cuándo, qué días de la semana (ISO,
     * 1 = lunes … 7 = domingo, sin repetir) y cuántos artículos por día. La
     * duración no se pide: la calcula el sistema.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'almacen_id' => ['required', 'integer', 'exists:alm_almacenes,id'],
            'fecha_inicio' => ['required', 'date'],
            'dias_semana' => ['required', 'array', 'min:1', 'max:7'],
            'dias_semana.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'articulos_por_dia' => ['required', 'integer', 'between:1,500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'almacen_id.required' => 'Elige el almacén que se va a contar.',
            'fecha_inicio.required' => 'Indica desde qué día empieza el inventario.',
            'dias_semana.required' => 'Marca al menos un día de la semana.',
            'dias_semana.min' => 'Marca al menos un día de la semana.',
            'articulos_por_dia.required' => 'Indica cuántos artículos se cuentan por día.',
            'articulos_por_dia.between' => 'Los artículos por día van de 1 a 500.',
        ];
    }
}
