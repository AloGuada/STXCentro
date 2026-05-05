import type { OcOverride } from '@/components/costos/cotizacion-tree';
import { Button } from '@/components/ui/button';
import type { CostosRequisicion } from '@/types/models';
import { router } from '@inertiajs/react';
import { useState } from 'react';

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

    const submit = () => {
        if (ocs.length === 0) {
            setErrorMsg('Aún no hay OCs en el preview. Asigna cantidades en el tab Cotización.');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        router.post(`/admin/costos/requisiciones/${requisicion.id}/liberar`, {
            ocs: ocs.map((oc) => ({
                proveedor_id: oc.proveedor_id,
                numero_oc: oc.numero_oc,
                modo_pago: oc.modo_pago,
                moneda: oc.moneda,
                envio: oc.envio || 0,
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
