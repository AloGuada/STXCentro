<?php

namespace App\Http\Requests\Admin\Rh;

use Illuminate\Foundation\Http\FormRequest;

class PersonaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'datos_extra' => ['nullable', 'array'],
            'datos_extra.imss' => ['nullable', 'string', 'max:255'],
            'datos_extra.curp' => ['nullable', 'string', 'max:18'],
            'datos_extra.rfc' => ['nullable', 'string', 'max:13'],
            'datos_extra.estado_civil' => ['nullable', 'string', 'max:50'],
            'datos_extra.hijos' => ['nullable', 'integer', 'min:0'],
            'datos_extra.domicilio' => ['nullable', 'string', 'max:500'],
            'datos_extra.cp' => ['nullable', 'string', 'max:10'],
            'datos_extra.localidad' => ['nullable', 'string', 'max:255'],
            'datos_extra.nombre_padre' => ['nullable', 'string', 'max:255'],
            'datos_extra.nombre_madre' => ['nullable', 'string', 'max:255'],
            'datos_extra.cuenta_banco' => ['nullable', 'string', 'max:255'],
            'datos_extra.banco_op' => ['nullable', 'string', 'max:255'],
            'datos_extra.c_infonavit' => ['nullable', 'string', 'max:255'],
            'datos_extra.c_fonacot' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
        ];
    }
}
