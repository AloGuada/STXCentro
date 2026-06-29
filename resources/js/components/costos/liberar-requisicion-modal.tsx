import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { CostosRequisicion } from '@/types/models';

type Props = {
    requisicion: CostosRequisicion;
    open: boolean;
    onClose: () => void;
};

/**
 * Modal de liberación: confirma la conversión a OCs. Los grupos (proveedor +
 * numero_oc) salen de las selecciones; sus metadatos (modo de pago, fecha,
 * notas) ya están persistidos desde el tab "Definir OC". Liberar no manda
 * payload: solo confirma y el backend lee de costos_requisicion_ocs.
 */
export function LiberarRequisicionModal({ requisicion, open, onClose }: Props) {
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    // Grupos OC y detección de monedas mezcladas a partir de las selecciones.
    const { ocCount, contadoCount, monedasMezcladas } = useMemo(() => {
        const monedasPorGrupo = new Map<string, Set<string>>();
        requisicion.detalles?.forEach((d) => {
            d.selecciones?.forEach((s) => {
                const key = `${s.proveedor_id}|${s.numero_oc ?? 1}`;
                if (!monedasPorGrupo.has(key)) monedasPorGrupo.set(key, new Set());
                monedasPorGrupo.get(key)!.add(s.cotizacion_precio?.moneda ?? 'mxn');
            });
        });
        const contado = (requisicion.ocs ?? []).filter((oc) => oc.modo_pago === 'contado').length;
        return {
            ocCount: monedasPorGrupo.size,
            contadoCount: contado,
            monedasMezcladas: Array.from(monedasPorGrupo.values()).some((set) => set.size > 1),
        };
    }, [requisicion.detalles, requisicion.ocs]);

    if (!open) return null;

    const submit = () => {
        if (ocCount === 0) {
            setErrorMsg('Aún no hay OCs. Asigna partidas a una OC en el tab "Definir OC".');
            return;
        }
        if (monedasMezcladas) {
            setErrorMsg('Hay una OC con partidas en monedas distintas. Sepáralas por moneda (otra OC) antes de liberar.');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        router.post(`/admin/costos/requisiciones/${requisicion.id}/liberar`, {}, {
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
                    Se generarán <strong>{ocCount}</strong> orden(es) de compra con los datos definidos en el tab "Definir OC".
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
                    <Button onClick={submit} disabled={submitting || ocCount === 0}>
                        {submitting ? 'Liberando...' : `Liberar y generar ${ocCount} OC${ocCount === 1 ? '' : 's'}`}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
