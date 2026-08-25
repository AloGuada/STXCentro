/**
 * Proveedor(es) adjudicado(s) de una requisición: lo que quedó seleccionado en
 * la(s) OC(s), no el mejor precio del comparativo.
 *
 * Con una sola OC se muestra el proveedor y su folio. Con varias se muestra el
 * primero y un "+N", y el detalle (folio de OC, proveedor y monto) vive en un
 * popover colgado del badge "+N" para no reventar el alto del renglón. El
 * popover va solo en el badge para que el click sobre el nombre del proveedor
 * siga abriendo el detalle del renglón.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { formatMoney } from '@/components/costos/monto';
import type { CostosRequisicionOcResumen } from '@/types/models';

const nombre = (oc: CostosRequisicionOcResumen) => oc.nombre_comercial || oc.razon_social;

const POP_WIDTH = 380;

type Props = {
    ocs: CostosRequisicionOcResumen[];
    /** Alinea el popover por la derecha del disparador (columnas a la derecha). */
    alinearDerecha?: boolean;
};

export default function OcsAdjudicadas({ ocs, alinearDerecha = false }: Props) {
    const [show, setShow] = useState(false);
    const [coords, setCoords] = useState<{ top: number; left: number } | null>(null);
    const triggerRef = useRef<HTMLSpanElement>(null);
    const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const calcularCoords = () => {
        if (!triggerRef.current) return;
        const rect = triggerRef.current.getBoundingClientRect();
        const margin = 8;
        let left = alinearDerecha ? rect.right - POP_WIDTH : rect.left;
        if (left < margin) left = margin;
        if (left + POP_WIDTH > window.innerWidth - margin) {
            left = window.innerWidth - POP_WIDTH - margin;
        }
        setCoords({ top: rect.bottom + 4, left });
    };

    useEffect(() => {
        if (!show) return;
        calcularCoords();
        const recalcular = () => calcularCoords();
        window.addEventListener('scroll', recalcular, true);
        window.addEventListener('resize', recalcular);
        return () => {
            window.removeEventListener('scroll', recalcular, true);
            window.removeEventListener('resize', recalcular);
        };
    }, [show]);

    const abrir = () => {
        if (closeTimer.current) clearTimeout(closeTimer.current);
        setShow(true);
    };

    const cerrarConDelay = () => {
        if (closeTimer.current) clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(() => setShow(false), 150);
    };

    if (ocs.length === 0) return null;

    if (ocs.length === 1) {
        const [oc] = ocs;
        return (
            <div>
                <div className="text-sm font-medium">{nombre(oc)}</div>
                {oc.folio && <div className="mt-0.5 font-mono text-[11px] text-base-content/50">{oc.folio}</div>}
            </div>
        );
    }

    const total = ocs.reduce((acc, oc) => acc + Number(oc.total ?? 0), 0);

    return (
        <>
            <span>
                {/* El nombre no abre el popover: así el click sigue cayendo en el
                    renglón y el usuario entra al detalle. El popover cuelga solo
                    del badge "+N". */}
                <span className="text-sm font-medium">{nombre(ocs[0])}</span>
                <span
                    ref={triggerRef}
                    onMouseEnter={abrir}
                    onMouseLeave={cerrarConDelay}
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        setShow((v) => !v);
                    }}
                    title="Ver todas las órdenes de compra"
                    className="ml-1 badge badge-ghost badge-xs cursor-help align-middle underline decoration-dotted underline-offset-2"
                >
                    +{ocs.length - 1}
                </span>
                <span className="mt-0.5 block text-[11px] text-base-content/50">{ocs.length} órdenes de compra</span>
            </span>

            {show && coords && createPortal(
                <div
                    onMouseEnter={abrir}
                    onMouseLeave={cerrarConDelay}
                    style={{ top: coords.top, left: coords.left, width: POP_WIDTH }}
                    className="fixed z-[100] rounded-lg border border-base-300 bg-base-100 p-3 text-left shadow-xl"
                >
                    <div className="mb-2 border-b border-base-300 pb-2 text-sm font-semibold">
                        Órdenes de compra ({ocs.length})
                    </div>
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="text-base-content/50">
                                <th className="pb-1 text-left font-normal">Folio OC</th>
                                <th className="pb-1 text-left font-normal">Proveedor</th>
                                <th className="pb-1 text-right font-normal">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ocs.map((oc) => (
                                <tr key={`${oc.proveedor_id}-${oc.numero_oc}`} className="border-t border-base-200">
                                    <td className="py-1 pr-2 font-mono">
                                        {oc.folio ?? <span className="text-base-content/40">OC {oc.numero_oc} (sin generar)</span>}
                                    </td>
                                    <td className="py-1 pr-2">{nombre(oc)}</td>
                                    <td className="py-1 text-right font-medium">{formatMoney(oc.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-base-300">
                                <td colSpan={2} className="pt-1 text-base-content/60">Neto a pagar</td>
                                <td className="pt-1 text-right font-semibold text-success">{formatMoney(total)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>,
                document.body,
            )}
        </>
    );
}
