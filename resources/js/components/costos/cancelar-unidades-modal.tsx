import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Cancelar las unidades de una partida que el proveedor ya no va a surtir.
 *
 * No surte efecto al guardar: queda pendiente de que la autorice el jefe de
 * compras, y mientras tanto la orden se reporta como pendiente de aprobación.
 */
type Props = {
    detalleId: number;
    partidaDescripcion: string;
    unidad: string;
    cantidadCancelable: number;
    /** Lo que falta por llegar; si es más que lo cancelable, la diferencia ya está facturada o pagada. */
    sinRecibir?: number;
    /** Qué ampara esa diferencia: sus facturas o lo pagado por su solicitud de pago. */
    amparadoPor?: 'factura' | 'pago' | null;
    open: boolean;
    onClose: () => void;
};

export function CancelarUnidadesModal({ detalleId, partidaDescripcion, unidad, cantidadCancelable, sinRecibir, amparadoPor, open, onClose }: Props) {
    const amparado = Math.max(0, Number(sinRecibir ?? cantidadCancelable) - cantidadCancelable);
    const [cantidad, setCantidad] = useState<string>(String(cantidadCancelable));
    const [motivo, setMotivo] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    if (!open) return null;

    const submit = () => {
        const cantidadNum = Number(cantidad);
        if (!Number.isFinite(cantidadNum) || cantidadNum <= 0) {
            setErrorMsg('Indica una cantidad válida.');
            return;
        }
        if (cantidadNum > cantidadCancelable + 0.001) {
            setErrorMsg(`Sólo quedan ${cantidadCancelable.toLocaleString('es-MX')} ${unidad} por cancelar.`);
            return;
        }
        if (motivo.trim().length < 10) {
            setErrorMsg('Explica por qué ya no se va a surtir (mínimo 10 caracteres).');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        router.post(
            `/admin/costos/ordenes-compra/partidas/${detalleId}/cancelaciones`,
            { cantidad: cantidadNum, motivo },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setSubmitting(false);
                    setErrorMsg(Object.values(errors)[0] ?? 'No se pudo registrar la cancelación.');
                },
                onSuccess: () => {
                    setMotivo('');
                    onClose();
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-xl">
                <h3 className="mb-2 text-lg font-bold">Cancelar unidades de la partida</h3>
                <p className="mb-3 text-sm text-base-content/60">
                    {partidaDescripcion}
                    <br />
                    Por cancelar: <strong>{cantidadCancelable.toLocaleString('es-MX')} {unidad}</strong>
                    {amparado > 0.001 && (
                        <>
                            <br />
                            <span className="text-warning">
                                {amparadoPor === 'pago'
                                    ? `Otras ${amparado.toLocaleString('es-MX')} ${unidad} no han llegado pero ya están pagadas: para cancelarlas hay que resolver con el proveedor la devolución de lo pagado.`
                                    : `Otras ${amparado.toLocaleString('es-MX')} ${unidad} no han llegado pero ya están facturadas: para cancelarlas hay que cancelar la factura o registrar su nota de crédito.`}
                            </span>
                        </>
                    )}
                </p>

                <div className="space-y-3">
                    <div>
                        <label className="label-text text-xs">Cantidad *</label>
                        <input
                            type="number"
                            step="0.01"
                            max={cantidadCancelable}
                            className="input input-bordered input-sm w-full"
                            value={cantidad}
                            onChange={(e) => setCantidad(e.target.value)}
                        />
                    </div>

                    <div>
                        <label className="label-text text-xs">Motivo *</label>
                        <textarea
                            className="textarea textarea-bordered w-full text-sm"
                            rows={3}
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            placeholder="El proveedor ya no va a surtir el resto, se compró con otro, etc."
                        />
                    </div>
                </div>

                <p className="mt-3 text-xs text-base-content/60">
                    Las unidades salen de la orden cuando el jefe de compras autorice la cancelación. Mientras tanto la orden
                    queda pendiente de aprobación.
                </p>

                {errorMsg && <p className="alert alert-error mt-3 text-sm">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>
                        Cerrar
                    </Button>
                    <Button onClick={submit} disabled={submitting}>
                        {submitting ? 'Guardando...' : 'Solicitar cancelación'}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
