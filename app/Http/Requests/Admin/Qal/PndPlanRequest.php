<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\MetodoPnd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que se pactó con el cliente: cuántas pruebas por método y su justificación.
 *
 * `pactado` es lo que decide si el método existe en el contrato. Un método sin
 * palomita no se guarda en cero: se borra su fila, porque la ausencia significa
 * «no entra en este contrato» y el cero significa «se pactaron cero».
 */
class PndPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // La nota deja escrito de dónde sale el número —«10% de las juntas
            // de penetración completa, cláusula 7.3»— y evita la discusión de
            // dentro de seis meses sobre si eran 60 u 80.
            'nota' => ['nullable', 'string', 'max:2000'],
            'plan' => ['present', 'array'],
            'plan.*.metodo' => ['required', Rule::enum(MetodoPnd::class)],
            'plan.*.pactado' => ['required', 'boolean'],
            'plan.*.comprometidas' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.*.comprometidas.min' => 'Las pruebas comprometidas no pueden ser negativas.',
        ];
    }
}
