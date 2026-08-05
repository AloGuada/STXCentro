import { useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import type { PortalTableroFactura } from '@/types/models';

/**
 * Acuse sellado por almacén. Con él la factura deja de estar pendiente de
 * recepción y entra a la cadena de aprobación.
 */
export function SubirComprobanteRecepcionModal({ factura, onClose }: { factura: PortalTableroFactura; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm<{ comprobante: File | null }>({ comprobante: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/portal/facturas/${factura.id}/comprobante`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-lg">
                <h3 className="text-lg font-bold">Comprobante de recepción</h3>
                <p className="mt-1 text-sm text-base-content/60">Factura {factura.folio}</p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <FormField label="Archivo" htmlFor="comprobante" error={errors.comprobante} required>
                        <input
                            id="comprobante"
                            type="file"
                            accept=".pdf,image/*"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('comprobante', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <p className="text-xs text-base-content/60">
                        Adjunta la remisión o el acuse sellado por almacén (PDF o foto).
                    </p>

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing || !data.comprobante}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Subir comprobante
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={() => !processing && onClose()}></div>
        </dialog>
    );
}
