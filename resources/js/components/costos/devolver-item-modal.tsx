import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    entregaDetalleId: number;
    partidaDescripcion: string;
    unidad: string;
    cantidadDisponible: number;
    open: boolean;
    onClose: () => void;
};

export function DevolverItemModal({
    entregaDetalleId,
    partidaDescripcion,
    unidad,
    cantidadDisponible,
    open,
    onClose,
}: Props) {
    const [cantidad, setCantidad] = useState<string>(String(cantidadDisponible));
    const [motivo, setMotivo] = useState<string>('');
    const [fecha, setFecha] = useState<string>(new Date().toISOString().slice(0, 10));
    const [evidencia, setEvidencia] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    if (!open) return null;

    const submit = () => {
        const cantidadNum = Number(cantidad);
        if (!Number.isFinite(cantidadNum) || cantidadNum <= 0) {
            setErrorMsg('Indica una cantidad válida.');
            return;
        }
        if (cantidadNum > cantidadDisponible + 0.001) {
            setErrorMsg(`La cantidad excede lo disponible (${cantidadDisponible.toLocaleString('es-MX')} ${unidad}).`);
            return;
        }
        if (motivo.trim().length < 5) {
            setErrorMsg('Indica un motivo claro (mínimo 5 caracteres).');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        const data: Record<string, string | number | File> = {
            entrega_detalle_id: entregaDetalleId,
            cantidad: cantidadNum,
            motivo,
            fecha,
        };
        if (evidencia) data.evidencia = evidencia;

        router.post('/admin/costos/devoluciones', data, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                setSubmitting(false);
                setErrorMsg(Object.values(errors)[0] ?? 'No se pudo registrar la devolución.');
            },
            onSuccess: () => {
                setMotivo('');
                setEvidencia(null);
                onClose();
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-xl">
                <h3 className="font-bold text-lg mb-2">Registrar devolución</h3>
                <p className="mb-3 text-sm text-base-content/60">
                    {partidaDescripcion}
                    <br />
                    Disponible: <strong>{cantidadDisponible.toLocaleString('es-MX')} {unidad}</strong>
                </p>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label-text text-xs">Cantidad *</label>
                            <input
                                type="number"
                                step="0.01"
                                max={cantidadDisponible}
                                className="input input-bordered input-sm w-full"
                                value={cantidad}
                                onChange={(e) => setCantidad(e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="label-text text-xs">Fecha *</label>
                            <input
                                type="date"
                                className="input input-bordered input-sm w-full"
                                value={fecha}
                                onChange={(e) => setFecha(e.target.value)}
                            />
                        </div>
                    </div>

                    <div>
                        <label className="label-text text-xs">Motivo *</label>
                        <textarea
                            className="textarea textarea-bordered w-full text-sm"
                            rows={3}
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            placeholder="Defecto, error de envío, exceso, etc."
                        />
                    </div>

                    <div>
                        <label className="label-text text-xs">Evidencia (opcional)</label>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            className="file-input file-input-bordered file-input-sm w-full"
                            onChange={(e) => setEvidencia(e.target.files?.[0] ?? null)}
                        />
                    </div>
                </div>

                {errorMsg && <p className="alert alert-error mt-3 text-sm">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancelar</Button>
                    <Button onClick={submit} disabled={submitting}>
                        {submitting ? 'Guardando...' : 'Registrar devolución'}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
