<?php

namespace App\Http\Requests\Admin\Qal;

use App\Enums\Qal\OrigenFirmante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Un lugar de firma.
 *
 * La persona sólo viaja cuando el lugar es de una persona fija; cuando firma
 * quien elaboró el documento se descarta, porque cambia con cada hoja. Una
 * persona fija puede quedar sin elegir: su raya sale en blanco.
 */
class FirmanteRequest extends FormRequest
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
            'etiqueta' => ['required', 'string', 'max:20'],
            'cargo' => ['required', 'string', 'max:80'],
            'origen' => ['required', Rule::enum(OrigenFirmante::class)],
            'usuario_id' => ['exclude_if:origen,creador', 'nullable', 'uuid', 'exists:usuarios,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'etiqueta.required' => 'Pon qué dice sobre la firma: Elaboró, Revisó, Aprobó…',
            'cargo.required' => 'Pon el cargo que se imprime bajo el nombre.',
            'usuario_id.exists' => 'Ese usuario ya no existe.',
        ];
    }
}
