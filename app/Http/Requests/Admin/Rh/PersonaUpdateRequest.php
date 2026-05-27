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
            'cv' => ['nullable', 'sometimes', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'foto' => ['nullable', 'sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'imss' => ['nullable', 'string', 'max:255'],
            'curp' => ['nullable', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:255'],
            'numero_ine' => ['nullable', 'string', 'max:255'],
            'estado_civil' => ['nullable', 'string', 'max:255'],
            'hijos' => ['nullable', 'integer', 'min:0'],
            'domicilio' => ['nullable', 'string', 'max:500'],
            'cp' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'nombre_padre' => ['nullable', 'string', 'max:255'],
            'nombre_madre' => ['nullable', 'string', 'max:255'],
            'cuenta_banco' => ['nullable', 'string', 'max:255'],
            'banco_op' => ['nullable', 'string', 'max:255'],
            'c_infonavit' => ['nullable', 'string', 'max:255'],
            'c_fonacot' => ['nullable', 'string', 'max:255'],
            'tramite_banco' => ['nullable', 'boolean'],
            'texto_cv' => ['nullable', 'string'],
            'contacto_emergencia_1_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_emergencia_1_telefono' => ['nullable', 'string', 'max:255'],
            'contacto_emergencia_2_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_emergencia_2_telefono' => ['nullable', 'string', 'max:255'],
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
