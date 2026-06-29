import { useForm } from '@inertiajs/react';

/**
 * Modal para que Compras suba la factura (CFDI XML + PDF) de una OC de contado
 * ya pagada. El backend parsea el CFDI para completar los datos fiscales
 * (UUID, impuestos, total); no genera un segundo pago.
 */
export function SubirFacturaContadoModal({
    ordenCompraId,
    open,
    onClose,
}: {
    ordenCompraId: number;
    open: boolean;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        xml: File | null;
        pdf: File | null;
        notas: string;
    }>({ xml: null, pdf: null, notas: '' });

    if (!open) {
        return null;
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/ordenes-compra/${ordenCompraId}/factura-contado`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box">
                <h3 className="text-lg font-bold">Subir factura (CFDI)</h3>
                <p className="mt-1 text-sm text-base-content/60">
                    Adjunta el XML y el PDF. Se leerán del CFDI los datos fiscales (UUID, impuestos y total). El anticipo ya
                    fue pagado, esto solo registra la factura.
                </p>

                <form onSubmit={submit} className="mt-4 space-y-3">
                    <div className="form-control">
                        <label className="mb-1 text-sm font-medium">XML del CFDI</label>
                        <input
                            type="file"
                            accept=".xml,text/xml,application/xml"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('xml', e.target.files?.[0] ?? null)}
                        />
                        {errors.xml && <span className="mt-1 text-xs text-error">{errors.xml}</span>}
                    </div>

                    <div className="form-control">
                        <label className="mb-1 text-sm font-medium">PDF de la factura</label>
                        <input
                            type="file"
                            accept="application/pdf"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                        />
                        {errors.pdf && <span className="mt-1 text-xs text-error">{errors.pdf}</span>}
                    </div>

                    <div className="form-control">
                        <label className="mb-1 text-sm font-medium">Notas (opcional)</label>
                        <textarea
                            className="textarea textarea-bordered w-full"
                            rows={2}
                            value={data.notas}
                            onChange={(e) => setData('notas', e.target.value)}
                        />
                    </div>

                    <div className="modal-action">
                        <button type="button" className="btn" onClick={onClose} disabled={processing}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="btn btn-primary"
                            disabled={processing || !data.xml || !data.pdf}
                        >
                            {processing ? 'Subiendo...' : 'Subir factura'}
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onClose} />
        </dialog>
    );
}
