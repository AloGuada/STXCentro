<?php

namespace App\Contracts\Costos;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contrato que cualquier modelo debe cumplir para entrar en la cadena de
 * aprobaciones polimorfica de costos. Cada implementacion declara su
 * `tipoAprobacion()` (string identificador, p.ej. 'solicitud_pago' o
 * 'requisicion'), que se usa para filtrar `costos_permisos` y armar la
 * cadena correcta. AprobacionController dispara los hooks
 * `onAprobacionCompleta()` / `onAprobacionRechazada()` cuando el flujo
 * llega a un terminal.
 */
interface Aprobable
{
    /**
     * Identificador para filtrar permisos y aprobadores (por departamento).
     */
    public function tipoAprobacion(): string;

    /**
     * Cadena de aprobaciones (MorphMany hacia Aprobacion via aprobable_*).
     */
    public function cadenaAprobacion(): MorphMany;

    /**
     * Hook disparado cuando todas las aprobaciones se cubrieron en orden.
     */
    public function onAprobacionCompleta(?string $userId = null): void;

    /**
     * Hook disparado cuando un aprobador rechazo en cualquier nivel.
     */
    public function onAprobacionRechazada(string $motivo, ?string $userId = null): void;
}
