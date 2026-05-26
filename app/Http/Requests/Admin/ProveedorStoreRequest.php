<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', 'unique:proveedores,codigo'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', 'unique:proveedores,rfc'],
            'direccion' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => [$this->boolean('tiene_acceso_portal') ? 'required' : 'nullable', 'email', 'max:255'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'tiene_acceso_portal' => ['boolean'],
            'password' => [$this->boolean('tiene_acceso_portal') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'maneja_credito' => ['boolean'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'dias_credito_default' => ['nullable', 'integer', 'min:0'],
            'respetar_fecha_factura' => ['boolean'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'tipo_proveedor' => ['nullable', 'string', 'max:100'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está registrado.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.unique' => 'Este RFC ya está registrado.',
            'email.required' => 'El email es obligatorio cuando el proveedor tiene acceso al portal.',
            'password.required' => 'La contraseña es obligatoria cuando el proveedor tiene acceso al portal.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
