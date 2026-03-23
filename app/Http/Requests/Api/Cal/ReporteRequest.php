<?php

namespace App\Http\Requests\Api\Cal;

use App\Http\Requests\Api\ApiFormRequest;

class ReporteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plano_id' => ['required', 'exists:cal_piezas_planos,id'],
            'strumis_id' => ['nullable', 'string', 'max:255'],
            'consecutivo' => ['nullable', 'string', 'max:255'],
            'inspector_id' => ['nullable', 'exists:usuarios,id'],
            'plantilla' => ['nullable', 'string', 'max:255'],
            'aprobado' => ['nullable', 'date'],
            'rechazado' => ['nullable', 'date'],
            'es_plantilla' => ['nullable', 'boolean'],
            'linea' => ['nullable', 'integer'],
            'modulo' => ['nullable', 'integer'],
            'comentario' => ['nullable', 'string'],
            'folio' => ['nullable', 'string', 'max:255'],
            'soldador_id' => ['nullable', 'exists:cal_soldadores,id'],
        ];
    }
}
