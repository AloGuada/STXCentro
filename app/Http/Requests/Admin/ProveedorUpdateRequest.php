<?php

namespace App\Http\Requests\Admin;

use App\Models\Proveedor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorUpdateRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:50', Rule::unique('proveedores', 'codigo')->ignore($this->route('proveedor'))],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'rfc' => ['required', 'string', 'max:13', Rule::unique('proveedores', 'rfc')->ignore($this->route('proveedor'))],
            'tipo_persona' => ['required', 'in:fisica,moral'],
            'regimen_fiscal_id' => ['required', 'exists:regimenes_fiscales,id'],
            'codigo_postal' => ['required', 'string', 'max:10'],
            'domicilio_fiscal' => ['required', 'string'],
            'domicilio_compra' => ['nullable', 'string'],
            'giro' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],

            // Cuenta bancaria
            'banco' => ['required', 'string', 'max:255'],
            'titular_cuenta' => ['required', 'string', 'max:255'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'clabe' => ['nullable', 'string', 'digits:18'],
            'moneda_cuenta' => ['required', 'in:MXN,USD,EUR'],

            // Adjuntos: opcionales en edición (se conservan los existentes)
            'constancia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'caratula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            // Portal / crédito
            'tiene_acceso_portal' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'maneja_credito' => ['boolean'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'dias_credito_default' => ['nullable', 'integer', 'min:0'],
            'respetar_fecha_factura' => ['boolean'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'tipo_proveedor' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $banco = $this->input('banco');

            if (Proveedor::esBanorte($banco)) {
                if (! $this->filled('numero_cuenta')) {
                    $validator->errors()->add('numero_cuenta', 'Para Banorte debe capturar el número de cuenta.');
                }
            } elseif (! $this->filled('clabe')) {
                $validator->errors()->add('clabe', 'La CLABE es obligatoria para bancos distintos de Banorte.');
            }

            if (! Proveedor::titularCoincide($this->input('titular_cuenta'), $this->input('razon_social'))) {
                $validator->errors()->add('titular_cuenta', 'El titular de la cuenta debe coincidir con la razón social del proveedor.');
            }
        });
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
            'tipo_persona.required' => 'El tipo de persona es obligatorio.',
            'regimen_fiscal_id.required' => 'El régimen fiscal es obligatorio.',
            'clabe.digits' => 'La CLABE debe tener 18 dígitos.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
