<?php

namespace App\Http\Requests\Portal;

use App\Models\Costos\OrdenCompra;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PortalFacturaStoreRequest extends FormRequest
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
            'orden_compra_id' => ['required', 'exists:costos_ordenes_compra,id'],
            'uuid_fiscal' => ['nullable', 'string', 'max:255', 'unique:costos_facturas,uuid_fiscal'],
            'folio_fiscal' => ['nullable', 'string', 'max:255'],
            'xml' => ['nullable', 'file', 'mimes:xml', 'max:5120'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'total' => ['required', 'numeric', 'min:0.01'],
            'fecha_factura' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ocId = $this->input('orden_compra_id');
            if (! $ocId) {
                return;
            }

            $tieneEntrega = OrdenCompra::query()
                ->where('id', $ocId)
                ->whereHas('entregas')
                ->exists();

            if (! $tieneEntrega) {
                $v->errors()->add(
                    'orden_compra_id',
                    'Esta orden de compra aún no tiene recepción de almacén. No es posible facturar.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden_compra_id.required' => 'Seleccione una orden de compra.',
            'total.required' => 'El total es requerido.',
            'uuid_fiscal.unique' => 'Ya existe una factura registrada con este UUID fiscal.',
        ];
    }
}
