<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class RequisicionLiberarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('costos.requisiciones.liberar') ?? false;
    }

    /**
     * Los metadatos de las OCs se persisten en costos_requisicion_ocs (tab
     * "Definir OC"); liberar no recibe payload, solo confirma y autoriza.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
