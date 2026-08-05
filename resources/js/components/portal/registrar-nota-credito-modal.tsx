import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    facturaId: number;
    saldoFacturado: number;
    open: boolean;
    onClose: () => void;
};

const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

export function PortalRegistrarNotaCreditoModal({ facturaId, saldoFacturado, open, onClose }: Props) {
    const [monto, setMonto] = useState<string>('');
    const [concepto, setConcepto] = useState<string>('');
    const [fecha, setFecha] = useState<string>(new Date().toISOString().slice(0, 10));
    const [xml, setXml] = useState<File | null>(null);
    const [pdf, setPdf] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);

    if (!open) return null;

    const submit = () => {
        const montoNum = Number(monto);
        if (!Number.isFinite(montoNum) || montoNum <= 0) {
            setErrorMsg('Indica un monto válido.');
            return;
        }
        if (!xml && montoNum > saldoFacturado + 0.001) {
            setErrorMsg(`El monto excede el saldo facturado disponible (${formatMoney(saldoFacturado)}).`);
            return;
        }
        if (!concepto.trim()) {
            setErrorMsg('Captura un concepto.');
            return;
        }

        setErrorMsg(null);
        setSubmitting(true);

        const data: Record<string, string | number | File> = {
            factura_id: facturaId,
            monto: montoNum,
            concepto,
            fecha_emision: fecha,
        };
        if (xml) data.xml = xml;
        if (pdf) data.pdf = pdf;

        router.post('/portal/notas-credito', data, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                setSubmitting(false);
                setErrorMsg(Object.values(errors)[0] ?? 'No se pudo registrar la nota.');
            },
            onSuccess: () => {
                setMonto('');
                setConcepto('');
                setXml(null);
                setPdf(null);
                onClose();
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-xl">
                <h3 className="font-bold text-lg mb-3">Subir nota de crédito</h3>
                <p className="mb-3 text-sm text-base-content/60">
                    Saldo facturado disponible: <strong>{formatMoney(saldoFacturado)}</strong>
                </p>

                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label-text text-xs">Monto *</label>
                            <input
                                type="number"
                                step="0.01"
                                className="input input-bordered input-sm w-full"
                                value={monto}
                                onChange={(e) => setMonto(e.target.value)}
                            />
                            <p className="text-[10px] text-base-content/50 mt-1">
                                Si subes XML, el monto se sobrescribe con el del CFDI.
                            </p>
                        </div>
                        <div>
                            <label className="label-text text-xs">Fecha de emisión *</label>
                            <input
                                type="date"
                                className="input input-bordered input-sm w-full"
                                value={fecha}
                                onChange={(e) => setFecha(e.target.value)}
                            />
                        </div>
                    </div>

                    <div>
                        <label className="label-text text-xs">Concepto *</label>
                        <textarea
                            className="textarea textarea-bordered w-full text-sm"
                            rows={2}
                            value={concepto}
                            onChange={(e) => setConcepto(e.target.value)}
                            placeholder="Devolución parcial, descuento por pronto pago, etc."
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label-text text-xs">XML CFDI (opcional)</label>
                            <input
                                type="file"
                                accept=".xml"
                                className="file-input file-input-bordered file-input-sm w-full"
                                onChange={(e) => setXml(e.target.files?.[0] ?? null)}
                            />
                        </div>
                        <div>
                            <label className="label-text text-xs">PDF (opcional)</label>
                            <input
                                type="file"
                                accept=".pdf"
                                className="file-input file-input-bordered file-input-sm w-full"
                                onChange={(e) => setPdf(e.target.files?.[0] ?? null)}
                            />
                        </div>
                    </div>
                </div>

                {errorMsg && <p className="alert alert-error mt-3 text-sm">{errorMsg}</p>}

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose} disabled={submitting}>Cancelar</Button>
                    <Button onClick={submit} disabled={submitting}>
                        {submitting ? 'Guardando...' : 'Subir nota'}
                    </Button>
                </div>
            </div>
        </dialog>
    );
}
