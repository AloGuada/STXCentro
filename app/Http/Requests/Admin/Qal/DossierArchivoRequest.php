<?php

namespace App\Http\Requests\Admin\Qal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Los PDF que se suben de una vez a una sección del dosier. Los límites son
 * los de `resources/js/lib/uploads.ts`: 15 MB por archivo y 20 por envío.
 */
class DossierArchivoRequest extends FormRequest
{
    public const MAXIMO_KB = 15 * 1024;

    public const MAXIMO_POR_ENVIO = 20;

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
            'archivos' => ['required', 'array', 'max:'.self::MAXIMO_POR_ENVIO],
            'archivos.*' => ['file', 'mimes:pdf', 'max:'.self::MAXIMO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivos.required' => 'Elige al menos un PDF.',
            'archivos.max' => 'Van máximo '.self::MAXIMO_POR_ENVIO.' archivos por envío.',
            'archivos.*.mimes' => 'Sólo se suben PDF.',
            'archivos.*.max' => 'Cada PDF puede pesar hasta 15 MB.',
        ];
    }
}
