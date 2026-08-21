<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El avance de montaje de una semana.
 *
 * `pz_montadas` acepta nulo porque la semana puede quedar registrada como
 * revisada antes de tener la cifra. Lo que no se acepta es que llegue vacía
 * *y* sin nota ni bandera: eso sería guardar una fila que no dice nada.
 */
class ObraMontajeRequest extends FormRequest
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
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            // 53 y no 52: los años que arrancan en jueves tienen una semana más.
            'semana' => ['required', 'integer', 'min:1', 'max:53'],
            'pz_montadas' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sin_incidencias' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pz_montadas.min' => 'Las piezas montadas no pueden ser negativas.',
        ];
    }
}
