<?php

namespace App\Http\Requests\Admin;

use App\Enums\FormaPago;
use App\Enums\TipoProveedor;
use App\Models\Banco;
use App\Models\Proveedor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas cruzadas de la cuenta bancaria del proveedor, compartidas por el alta y
 * la edición. Encapsula: titular = razón social (solo Proveedor formal) y la
 * regla cuenta/CLABE según el banco pagador.
 */
class ProveedorBancoValidator
{
    public static function validar(FormRequest $request, Validator $validator): void
    {
        $tipo = $request->input('tipo_proveedor');

        // Servicio no captura banca ni titular. Cheque/efectivo tampoco valida
        // datos bancarios (no hay cuenta destino).
        if ($tipo === TipoProveedor::Servicio->value
            || $request->input('forma_pago') !== FormaPago::Transferencia->value) {
            return;
        }

        // El titular debe coincidir con la razón social solo para Proveedor
        // formal; un Tercero puede cobrarse en una cuenta a otro nombre.
        if ($tipo === TipoProveedor::Proveedor->value
            && ! Proveedor::titularCoincide($request->input('titular_cuenta'), $request->input('razon_social'))) {
            $validator->errors()->add('titular_cuenta', 'El titular de la cuenta debe coincidir con la razón social del proveedor.');
        }

        $banco = $request->input('banco_id') ? Banco::find($request->input('banco_id')) : null;

        if (! $banco) {
            $validator->errors()->add('banco_id', 'Seleccione el banco.');

            return;
        }

        // Banco pagador (mismo banco de la empresa): solo número de cuenta con la
        // longitud definida en el catálogo. Cualquier otro banco: CLABE de 18.
        if ($banco->es_pagador) {
            $digitos = $banco->digitos_cuenta ?? 10;
            if (! preg_match('/^\d{'.$digitos.'}$/', (string) $request->input('numero_cuenta'))) {
                $validator->errors()->add('numero_cuenta', "El número de cuenta de {$banco->nombre} debe tener {$digitos} dígitos.");
            }
        } elseif (! preg_match('/^\d{18}$/', (string) $request->input('clabe'))) {
            $validator->errors()->add('clabe', 'La CLABE debe tener 18 dígitos.');
        }
    }
}
