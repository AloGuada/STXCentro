<?php

namespace App\Http\Requests\Admin;

use App\Enums\FormaPago;
use App\Enums\TipoProveedor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $rules = [
            'tipo_proveedor' => ['required', Rule::enum(TipoProveedor::class)],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_correo' => ['nullable', 'email', 'max:255'],
        ];

        // Servicio (luz/agua): se paga por banca con número de servicio y
        // referencia. Sin datos fiscales ni cuenta bancaria.
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
            // Datos fiscales: obligatorios para Proveedor formal; laxos para Tercero.
            'rfc' => [$esProveedor ? 'required' : 'nullable', 'string', 'max:13', 'unique:proveedores,rfc'],
            'tipo_persona' => [$esProveedor ? 'required' : 'nullable', 'in:fisica,moral'],
            'regimen_fiscal_id' => [$esProveedor ? 'required' : 'nullable', 'exists:regimenes_fiscales,id'],
            'codigo_postal' => [$esProveedor ? 'required' : 'nullable', 'string', 'max:10'],
            'domicilio_fiscal' => [$esProveedor ? 'required' : 'nullable', 'string'],
            'domicilio_compra' => ['nullable', 'string'],
            'giro' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string'],
            // El email es la cuenta del portal: solo se exige si se le da acceso.
            'email' => [$this->boolean('tiene_acceso_portal') ? 'required' : 'nullable', 'email', 'max:255'],

            // Forma de pago y cuenta bancaria (la regla cuenta/CLABE según banco
            // pagador se resuelve en withValidator()).
            'forma_pago' => ['required', Rule::enum(FormaPago::class)],
            'banco_id' => ['nullable', 'exists:bancos,id'],
            'titular_cuenta' => [$transferencia ? 'required' : 'nullable', 'string', 'max:255'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'clabe' => ['nullable', 'string', 'max:18'],
            'tarjeta' => ['nullable', 'string', 'max:30'],
            'moneda_cuenta' => ['required', 'in:MXN,USD,EUR'],

            // Adjuntos: constancia solo para Proveedor; carátula solo si transferencia.
            'constancia' => [$esProveedor ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'caratula' => [$transferencia ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            // Portal / crédito
            'tiene_acceso_portal' => ['boolean'],
            'password' => [$this->boolean('tiene_acceso_portal') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
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
            'tipo_proveedor.required' => 'El tipo es obligatorio.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.unique' => 'Este RFC ya está registrado.',
            'tipo_persona.required' => 'El tipo de persona es obligatorio.',
            'regimen_fiscal_id.required' => 'El régimen fiscal es obligatorio.',
            'constancia.required' => 'La constancia de situación fiscal es obligatoria.',
            'caratula.required' => 'La carátula bancaria es obligatoria.',
            'numero_servicio.required' => 'El número de servicio es obligatorio.',
            'referencia_servicio.required' => 'La referencia es obligatoria.',
            'email.required' => 'El correo es obligatorio cuando el proveedor tiene acceso al portal.',
            'password.required' => 'La contraseña es obligatoria cuando el proveedor tiene acceso al portal.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
