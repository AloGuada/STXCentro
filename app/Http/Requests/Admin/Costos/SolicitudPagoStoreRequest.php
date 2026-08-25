<?php

namespace App\Http\Requests\Admin\Costos;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudPagoStoreRequest extends FormRequest
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
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'firma_adicional_aprobador_id' => ['nullable', 'uuid', 'exists:usuarios,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'tipo_solicitud_id' => ['required', 'exists:costos_tipo_solicitud,id'],
            'concepto' => ['required', 'string', 'max:75'],
            'comentarios' => ['nullable', 'string', 'max:250'],
            'tipo_pago' => ['required', 'string', 'in:transferencia,cheque,efectivo'],
            'tipo_moneda' => ['required', 'string', 'in:mxn,usd,eur'],
            'tipo_cambio' => ['nullable', 'numeric', 'min:0.000001'],
            'fecha_pago_solicitada' => ['nullable', 'date'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.obra_rubro_id' => ['required', 'exists:costos_obra_rubros,id'],
            'detalles.*.concepto' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'monto_total' => ['nullable', 'numeric', 'min:0.01'],
            'archivos' => ['nullable', 'array'],
            'archivos.*' => ['nullable', 'array'],
            'archivos.*.*' => ['file', 'max:10240'],
        ];
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator) {
            // Los tipos de solicitud sin rubros capturan el total directamente;
            // sin él la solicitud quedaría en cero.
            $tipo = \App\Models\Costos\TipoSolicitud::find($this->input('tipo_solicitud_id'));
            if ($tipo && ! $tipo->rubros && empty($this->input('detalles', [])) && ! $this->filled('monto_total')) {
                $validator->errors()->add('monto_total', 'Captura el total del pago.');
            }

            $this->validarFechaPago($validator);
        });
    }

    /**
     * La fecha de pago debe ser un viernes no anterior al corte configurado.
     */
    protected function validarFechaPago(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if (! $this->filled('fecha_pago_solicitada') || $validator->errors()->has('fecha_pago_solicitada')) {
            return;
        }

        $fecha = \Illuminate\Support\Carbon::parse($this->input('fecha_pago_solicitada'));

        if (! \App\Models\Costos\ConfiguracionCostos::actual()->fechaPagoValida($fecha)) {
            $validator->errors()->add(
                'fecha_pago_solicitada',
                $fecha->dayOfWeek !== \Illuminate\Support\Carbon::FRIDAY
                    ? 'La fecha de pago debe ser un viernes.'
                    : 'La fecha de pago ya pasó el corte; elige un viernes posterior.',
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departamento_id.required' => 'El departamento es obligatorio.',
            'tipo_solicitud_id.required' => 'El tipo de solicitud es obligatorio.',
            'concepto.required' => 'El concepto es obligatorio.',
            'concepto.max' => 'El concepto no puede superar los 75 caracteres.',
            'comentarios.max' => 'Los comentarios no pueden superar los 250 caracteres.',
            'tipo_pago.required' => 'El tipo de pago es obligatorio.',
            'detalles.*.obra_rubro_id.required' => 'El centro de costos es obligatorio.',
            'detalles.*.concepto.required' => 'El concepto del detalle es obligatorio.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            'archivos.*.*.file' => 'El documento adjunto no es un archivo válido.',
            'archivos.*.*.max' => 'Cada archivo no puede superar los 10 MB.',
        ];
    }
}
