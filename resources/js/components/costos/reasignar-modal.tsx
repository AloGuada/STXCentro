import { router } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { CostosObraRubro, CostosSolicitudPagoDetalle, PresupuestoOption } from '@/types/models';
import { blankCentroCostoRow, type CentroCostoRow, DetallesCentroCostoGrid, filaCentroCostoTieneDatos } from './detalles-centro-costo-grid';

type Props = {
    open: boolean;
    onClose: () => void;
    url: string;
    presupuestos: PresupuestoOption[];
    obraRubros: CostosObraRubro[];
    /** El catálogo se pide al abrir el modal; mientras llega no se captura. */
    cargandoCatalogo?: boolean;
    detallesActuales: CostosSolicitudPagoDetalle[];
    /**
     * En una solicitud pagada el reparto además tiene que cuadrar con lo que
     * salió del banco, así que la suma se valida antes de mandar.
     */
    totalBloqueado?: boolean;
    /** El monto de la solicitud. Reasignar nunca lo cambia. */
    montoSolicitud?: number;
};

const fmtMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

export function ReasignarModal({ open, onClose, url, presupuestos, obraRubros, cargandoCatalogo = false, detallesActuales, totalBloqueado = false, montoSolicitud }: Props) {
    const [motivo, setMotivo] = useState('');
    const [detalles, setDetalles] = useState<CentroCostoRow[]>([]);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Precargar los detalles actuales cada vez que se abre el modal.
    useEffect(() => {
        if (!open) {
            return;
        }
        const rows: CentroCostoRow[] = detallesActuales.map((d) => ({
            presupuesto_id: d.obra_rubro?.presupuesto_id != null ? String(d.obra_rubro.presupuesto_id) : '',
            obra_rubro_id: String(d.obra_rubro_id),
            monto: String(d.subtotal),
            concepto: d.concepto,
        }));
        rows.push(blankCentroCostoRow());
        setDetalles(rows);
        setMotivo('');
        setError(null);
        // Solo al abrir; no reaccionar a cambios de referencia de props mientras está abierto.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    if (!open) {
        return null;
    }

    const suma = detalles
        .filter(filaCentroCostoTieneDatos)
        .reduce((acumulado, d) => acumulado + (parseFloat(d.monto) || 0), 0);
    const descuadre = montoSolicitud != null && Math.abs(suma - montoSolicitud) > 0.01;

    const submit = () => {
        if (motivo.trim().length < 10) {
            setError('El motivo debe tener al menos 10 caracteres.');
            return;
        }

        const payload = detalles.filter(filaCentroCostoTieneDatos).map((d) => ({
            obra_rubro_id: Number(d.obra_rubro_id),
            monto: parseFloat(d.monto) || 0,
            concepto: d.concepto ?? null,
        }));

        if (payload.length === 0) {
            setError('Agregue al menos un centro de costos.');
            return;
        }

        if (payload.some((d) => !d.obra_rubro_id || d.monto <= 0)) {
            setError('Cada renglón necesita un centro de costos y un monto mayor a cero.');
            return;
        }

        if (totalBloqueado && montoSolicitud != null) {
            const suma = payload.reduce((s, d) => s + d.monto, 0);
            if (Math.abs(suma - montoSolicitud) > 0.01) {
                setError(`La suma ($${fmtMoney(suma)}) debe igualar el monto pagado ($${fmtMoney(montoSolicitud)}).`);
                return;
            }
        }

        setProcessing(true);
        setError(null);
        router.post(url, { motivo, detalles: payload }, {
            preserveScroll: true,
            onError: (errors) => {
                setProcessing(false);
                setError(errors.motivo ?? errors.detalles ?? errors.estatus ?? 'No se pudo completar la reasignación.');
            },
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-3xl">
                <h3 className="mb-1 text-lg font-bold">Reasignar centros de costos</h3>
                <p className="mb-4 text-sm text-base-content/60">
                    Se revertirán los cargos actuales y se aplicarán los nuevos. Queda registro de la operación. Sólo se
                    mueve el gasto entre centros de costos: el monto de la solicitud no cambia.
                    {totalBloqueado && ' La solicitud está pagada: la suma debe conservar el monto pagado.'}
                </p>

                {cargandoCatalogo && (
                    <p className="mb-2 flex items-center gap-2 text-sm text-base-content/60">
                        <Loader2Icon className="size-4 animate-spin" /> Cargando centros de costos...
                    </p>
                )}

                <DetallesCentroCostoGrid presupuestos={presupuestos} obraRubros={obraRubros} detalles={detalles} onChange={setDetalles} disabled={processing || cargandoCatalogo} />

                {montoSolicitud != null && (
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span className="text-base-content/60">
                            Monto de la solicitud: <span className="font-mono">${fmtMoney(montoSolicitud)}</span>
                        </span>
                        {/* El desglose puede sumar distinto (la solicitud que se
                            comprueba después no cuadra con lo pedido); se avisa,
                            pero no se bloquea salvo que ya esté pagada. */}
                        {descuadre && (
                            <span className="text-warning">
                                El reparto suma ${fmtMoney(suma)}: se cargará eso al presupuesto y la solicitud seguirá
                                en ${fmtMoney(montoSolicitud)}.
                            </span>
                        )}
                    </div>
                )}

                <label className="form-control mt-4 w-full">
                    <div className="label">
                        <span className="label-text">Motivo (mínimo 10 caracteres)</span>
                    </div>
                    <textarea
                        className="textarea textarea-bordered w-full"
                        rows={3}
                        maxLength={500}
                        value={motivo}
                        onChange={(e) => setMotivo(e.target.value)}
                        disabled={processing}
                        placeholder="Describa el motivo de la reasignación"
                    />
                </label>

                {error && <p className="mt-2 text-sm text-error">{error}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Volver
                    </Button>
                    <Button onClick={submit} disabled={processing || cargandoCatalogo || motivo.trim().length < 10}>
                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                        Reasignar
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={processing ? undefined : onClose}></div>
        </dialog>
    );
}
