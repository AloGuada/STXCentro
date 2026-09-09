<?php

namespace App\Http\Requests\Admin\Alm;

use Illuminate\Foundation\Http\FormRequest;

class ConteoCierreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alm.conteos.cerrar') ?? false;
    }

    /**
     * La hoja firmada es opcional: el documento con valor contable es el
     * ajuste que genera el cierre; esto es el papel escaneado que lo respalda,
     * y no siempre lo tienen a la mano al cerrar.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'firmado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firmado.mimes' => 'La hoja firmada va en PDF o como foto (JPG o PNG).',
            'firmado.max' => 'La hoja firmada no puede pesar más de 10 MB.',
        ];
    }
}
