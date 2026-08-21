<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una incidencia de montaje.
 *
 * `pz_defecto` arranca en 1: una incidencia que no afecta a ninguna pieza no es
 * una incidencia, y el cero era el truco con el que la aplicación anterior
 * marcaba «semana revisada sin hallazgos». Eso ahora es una bandera de la
 * semana en `qal_obra_montaje` y ya no necesita una fila fantasma aquí.
 *
 * Se exige descripción o folio —al menos uno— porque una incidencia sin
 * ninguno de los dos no se puede seguir ni cerrar: es un número suelto.
 */
class ObraIncidenciaRequest extends FormRequest
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
            'semana' => ['required', 'integer', 'min:1', 'max:53'],
            'fecha' => ['required', 'date'],
            'area' => ['required', Rule::enum(AreaIncidencia::class)],
            'departamento' => ['required', Rule::enum(DepartamentoIncidencia::class)],
            'pz_defecto' => ['required', 'integer', 'min:1', 'max:100000'],
            'folio' => ['nullable', 'string', 'max:255', 'required_without:descripcion'],
            'descripcion' => ['nullable', 'string', 'max:2000', 'required_without:folio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pz_defecto.min' => '¿A cuántas piezas afecta? Una incidencia afecta al menos a una.',
            'folio.required_without' => 'Pon al menos una descripción o un folio de no conformidad.',
            'descripcion.required_without' => 'Pon al menos una descripción o un folio de no conformidad.',
        ];
    }
}
