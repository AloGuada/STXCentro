import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { OcOverride } from '@/components/costos/cotizacion-tree';
import { Button } from '@/components/ui/button';
import type { CostosRequisicion } from '@/types/models';

type Props = {
    requisicion: CostosRequisicion;
    ocs: OcOverride[];
    open: boolean;
    onClose: () => void;
};

/**
 * Modal de liberación: confirma la conversión a OCs. El array `ocs[]` viene
 * pre-armado por <CotizacionTree> en el tab de Cotización (modo_pago, moneda,
 * envío y notas por (proveedor, numero_oc)). Compras solo confirma y dispara
 * la liberación.
 */
export function LiberarRequisicionModal({ requisicion, ocs, open, onClose }: Props) {
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    if (!open) return null;

    const contadoCount = ocs.filter((oc) => oc.modo_pago === 'contado').length;

    // Una OC (grupo proveedor+numero_oc) no puede mezclar monedas de cotización.
    const grupoConMonedasMezcladas = (() => {
        const porGrupo = new Map<string, Set<string>>();
        requisicion.detalles?.forEach((d) => {
            d.selecciones?.forEach((s) => {
                const key = `${s.proveedor_id}|${s.numero_oc ?? 1}`;
                const moneda = s.cotizacion_precio?.moneda ?? 'mxn';
                if (!porGrupo.has(key)) porGrupo.set(key, new Set());
                porGrupo.get(key)!.add(moneda);
            });
        });
        return Array.from(porGrupo.values()).some((set) => set.size > 1);
    })();

    const submit = () => {
        if (ocs.length === 0) {
            setErrorMsg('Aún no hay OCs en el preview. Asigna cantidades en el tab Cotización.');
            return;
        }

        if (grupoConMonedasMezcladas) {
            setErrorMsg('Hay una OC con partidas en monedas distintas. Sepáralas por moneda (cambia el OC#) antes de liberar.');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        router.post(`/admin/costos/requisiciones/${requisicion.id}/liberar`, {
            ocs: ocs.map((oc) => ({
                proveedor_id: oc.proveedor_id,
                numero_oc: oc.numero_oc,
                modo_pago: oc.modo_pago,
                fecha_entrega: oc.fecha_entrega,
                notas: oc.notas.trim() || null,
            })),
        }, {
            preserveScroll: true,
            onError: (errors) => {
                setSubmitting(false);
                setErrorMsg(Object.values(errors)[0] ?? 'No se pudo liberar la requisición.');
            },
            onSuccess: () => onClose(),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-md">
                <h3 className="mb-2 text-lg font-bold">Liberar requisición</h3>
                <p className="mb-3 text-sm text-base-content/60">
                    Se generarán <strong>{ocs.length}</strong> orden(es) de compra con los datos capturados en el preview de cotización.
                    Se aplicará el impacto presupuestal y las OCs serán visibles en el portal de los proveedores.
                </p>

                {contadoCount > 0 && (
                    <div className="alert alert-info mb-3 text-sm">
                        <span>
                            {contadoCount === 1
                                ? '1 OC es de contado: se generará una solicitud de pago de anticipo (pendiente de firma) para gestionar el pago por adelantado.'
                                : `${contadoCount} OC son de contado: se generará una solicitud de pago de anticipo (pendiente de firma) por cada una para gestionar el pago por adelantado.`}
                        </span>
                    </div>
                )}

                {errorMsg && <p className="alert alert-error mb-3 text-sm">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancelar</Button>
                    <Button onClick={submit} disabled={submitting || ocs.length === 0}>
                        {submitting ? 'Liberando...' : `Liberar y generar ${ocs.length} OC${ocs.length === 1 ? '' : 's'}`}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
