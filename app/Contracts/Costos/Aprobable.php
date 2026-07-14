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
     * True si el documento tiene presupuesto reservado vigente (apartado sin
     * vencer). La cadena de aprobaciones lo usa para saltar niveles marcados.
     */
    public function tienePresupuestoReservado(): bool;

    /**
     * IDs de los centros de costo (rubros) que toca el documento. La cadena de
     * aprobaciones lo usa para restringir el salto de niveles a ciertos centros.
     *
     * @return list<int>
     */
    public function centrosDeCostoIds(): array;

    /**
     * True si el documento debe saltarse el primer nivel de la cadena (la
     * verificación de costos). La cadena de aprobaciones omite ese nivel.
     */
    public function saltaVerificacionCostos(): bool;

    /**
     * UUID del aprobador de la firma adicional (ad-hoc) elegida al crear el
     * documento, o null si no aplica. La cadena de aprobaciones la inserta como
     * un nivel 0 que firma antes que la cadena configurada.
     */
    public function firmaAdicionalAprobadorId(): ?string;

    /**
     * Hook disparado cuando todas las aprobaciones se cubrieron en orden.
     */
    public function onAprobacionCompleta(?string $userId = null): void;

    /**
     * Hook disparado cuando un aprobador rechazo en cualquier nivel.
     */
    public function onAprobacionRechazada(string $motivo, ?string $userId = null): void;
}
