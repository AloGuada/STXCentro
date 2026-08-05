<?php

namespace App\Http\Requests\Admin;

use App\Enums\FormaPago;
use App\Enums\TipoProveedor;
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
        $rules = [
            'codigo' => ['required', 'string', 'max:50', Rule::unique('proveedores', 'codigo')->ignore($this->route('proveedor'))],
            'tipo_proveedor' => ['required', Rule::enum(TipoProveedor::class)],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_correo' => ['nullable', 'email', 'max:255'],
        ];

        // Servicio (luz/agua): número de servicio y referencia, sin banca.
        if ($this->input('tipo_proveedor') === TipoProveedor::Servicio->value) {
            return $rules + [
                'email' => ['nullable', 'email', 'max:255'],
                'numero_servicio' => ['required', 'string', 'max:100'],
                'referencia_servicio' => ['required', 'string', 'max:100'],
            ];
        }

        $esProveedor = $this->input('tipo_proveedor') === TipoProveedor::Proveedor->value;
        $transferencia = $this->input('forma_pago') === FormaPago::Transferencia->value;

        return $rules + [
            'rfc' => [$esProveedor ? 'required' : 'nullable', 'string', 'max:13', Rule::unique('proveedores', 'rfc')->ignore($this->route('proveedor'))],
            'tipo_persona' => [$esProveedor ? 'required' : 'nullable', 'in:fisica,moral'],
            'regimen_fiscal_id' => [$esProveedor ? 'required' : 'nullable', 'exists:regimenes_fiscales,id'],
            'codigo_postal' => [$esProveedor ? 'required' : 'nullable', 'string', 'max:10'],
            'domicilio_fiscal' => [$esProveedor ? 'required' : 'nullable', 'string'],
            'domicilio_compra' => ['nullable', 'string'],
            'giro' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string'],
            // El email es la cuenta del portal: solo se exige si se le da acceso.
            'email' => [$this->boolean('tiene_acceso_portal') ? 'required' : 'nullable', 'email', 'max:255'],

            'forma_pago' => ['required', Rule::enum(FormaPago::class)],
            'banco_id' => ['nullable', 'exists:bancos,id'],
            'titular_cuenta' => [$transferencia ? 'required' : 'nullable', 'string', 'max:255'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'clabe' => ['nullable', 'string', 'max:18'],
            'tarjeta' => ['nullable', 'string', 'max:30'],
            'moneda_cuenta' => ['required', 'in:MXN,USD,EUR'],

            // Adjuntos opcionales en edición (se conservan los existentes).
            'constancia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'caratula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            'tiene_acceso_portal' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'maneja_credito' => ['boolean'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'dias_credito_default' => ['nullable', 'integer', 'min:0'],
            'respetar_fecha_factura' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            ProveedorBancoValidator::validar($this, $validator);
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
            'tipo_proveedor.required' => 'El tipo es obligatorio.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.unique' => 'Este RFC ya está registrado.',
            'tipo_persona.required' => 'El tipo de persona es obligatorio.',
            'regimen_fiscal_id.required' => 'El régimen fiscal es obligatorio.',
            'numero_servicio.required' => 'El número de servicio es obligatorio.',
            'referencia_servicio.required' => 'La referencia es obligatoria.',
            'email.required' => 'El correo es obligatorio cuando el proveedor tiene acceso al portal.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
