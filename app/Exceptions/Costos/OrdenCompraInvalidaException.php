<?php

namespace App\Exceptions\Costos;

use RuntimeException;

/**
 * Se lanza cuando faltan datos para generar las órdenes de compra de una
 * requisición (uso de CFDI, centro de costos, monedas mezcladas, proveedor
 * bloqueado). Lleva el arreglo de errores clave => mensaje para devolverlo al
 * formulario con `back()->withErrors()`.
 */
class OrdenCompraInvalidaException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errores
     */
    public function __construct(public readonly array $errores)
    {
        parent::__construct(reset($errores) ?: 'No se pueden generar las órdenes de compra.');
    }
}
