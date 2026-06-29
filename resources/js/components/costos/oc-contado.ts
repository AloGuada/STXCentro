import type { CostosOrdenCompra } from '@/types/models';

/**
 * Flujo de una OC de contado: el pago (anticipo) va por una Solicitud de Pago,
 * así que el orden es aprobar la solicitud → pagar → recibir → subir factura →
 * completar. Compartido por el stepper del show y la columna "En proceso" del
 * listado para mantenerlos en sync.
 */
export const CONTADO_STEPS: { key: string; label: string; badge: string }[] = [
    { key: 'aprobacion', label: 'Aprob. solicitud', badge: 'badge badge-warning' },
    { key: 'pago', label: 'Pendiente pago', badge: 'badge badge-warning' },
    { key: 'recepcion', label: 'Recepción', badge: 'badge badge-info' },
    { key: 'factura', label: 'Subir factura', badge: 'badge badge-info' },
    { key: 'completada', label: 'Completada', badge: 'badge badge-success' },
];

/** Índice del paso actual del flujo de contado (-1 si cancelada). */
export function getContadoStep(oc: CostosOrdenCompra): number {
    if (oc.estatus === 'cancelada') return -1;

    const tieneFactura = (oc.facturas ?? []).some((f) => f.estatus !== 'cancelada');
    if (tieneFactura) return 4;

    const sols = oc.solicitudes_pago ?? [];
    if (sols.some((s) => s.estatus === 'pendiente_firma')) return 0;
    if (sols.some((s) => s.estatus === 'aprobada')) return 1;

    // Anticipo pagado (o sin solicitudes registradas todavía): toca recibir o facturar.
    if (oc.pagada_anticipo_contado || sols.length > 0) {
        return oc.estatus === 'pendiente_factura' ? 3 : 2;
    }
    return 0;
}
