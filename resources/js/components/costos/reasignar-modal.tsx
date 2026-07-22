import { router } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { CostosObraRubro, CostosSolicitudPagoDetalle, Obra } from '@/types/models';
import { blankCentroCostoRow, type CentroCostoRow, DetallesCentroCostoGrid, filaCentroCostoTieneDatos } from './detalles-centro-costo-grid';

type Props = {
    open: boolean;
    onClose: () => void;
    url: string;
    obras: Obra[];
    obraRubros: CostosObraRubro[];
    detallesActuales: CostosSolicitudPagoDetalle[];
    /** En una solicitud pagada el total queda fijo: solo se redistribuye. */
    totalBloqueado?: boolean;
    montoPagado?: number;
};

const fmtMoney = (n: number) => n.toLocaleString('es-MX', { minimumFractionDigits: 2 });

export function ReasignarModal({ open, onClose, url, obras, obraRubros, detallesActuales, totalBloqueado = false, montoPagado }: Props) {
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
            obra_id: d.obra_rubro?.obra_id != null ? String(d.obra_rubro.obra_id) : '',
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

        if (totalBloqueado && montoPagado != null) {
            const suma = payload.reduce((s, d) => s + d.monto, 0);
            if (Math.abs(suma - montoPagado) > 0.01) {
                setError(`La suma ($${fmtMoney(suma)}) debe igualar el monto pagado ($${fmtMoney(montoPagado)}).`);
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
                    Se revertirán los cargos actuales y se aplicarán los nuevos. Queda registro de la operación.
                    {totalBloqueado && ' La solicitud está pagada: la suma debe conservar el monto pagado.'}
                </p>

                <DetallesCentroCostoGrid obras={obras} obraRubros={obraRubros} detalles={detalles} onChange={setDetalles} disabled={processing} />

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
                    <Button onClick={submit} disabled={processing || motivo.trim().length < 10}>
                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                        Reasignar
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop" onClick={processing ? undefined : onClose}></div>
        </dialog>
    );
}
