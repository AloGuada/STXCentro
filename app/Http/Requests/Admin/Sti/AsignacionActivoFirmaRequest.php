<?php

namespace App\Http\Requests\Admin\Sti;

use Illuminate\Foundation\Http\FormRequest;

class AsignacionActivoFirmaRequest extends FormRequest
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
            'firma_empleado' => ['required', 'string', 'max:60000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firma_empleado.required' => 'La firma es obligatoria.',
            'firma_empleado.max' => 'La firma es demasiado grande. Borrala e intenta de nuevo.',
            'firma_empleado.regex' => 'La firma no tiene un formato valido. Borrala e intenta de nuevo.',
        ];
    }
}
