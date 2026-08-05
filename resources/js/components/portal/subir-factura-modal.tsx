import { useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PortalTableroOrden } from '@/types/models';

/**
 * Paso 1 del alta de factura: se sube el CFDI y el servidor lo lee. Los datos
 * extraídos se revisan en el paso 2 antes de registrar nada, así que este modal
 * no crea la factura.
 *
 * Si el XML es rechazado (UUID repetido, monto mayor al saldo por facturar) la
 * respuesta vuelve al tablero con errores: el modal se queda abierto para que el
 * proveedor vea el motivo y reemplace el archivo.
 */
export function SubirFacturaModal({ orden, onClose }: { orden: PortalTableroOrden; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm<{
        orden_compra_id: number;
        xml: File | null;
        pdf: File | null;
        notas: string;
        origen: string;
    }>({
        orden_compra_id: orden.id,
        xml: null,
        pdf: null,
        notas: '',
        // Marca el flujo como iniciado en el tablero: el paso 2 vuelve aquí
        // como modal en lugar de mandar al proveedor a la pantalla anterior.
        origen: 'tablero',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/portal/facturas/preview', {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-xl">
                <h3 className="text-lg font-bold">Subir factura</h3>
                <p className="mt-1 text-sm text-base-content/60">
                    {orden.folio} · en el siguiente paso revisas los datos del CFDI antes de confirmar.
                </p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <FormField label="Archivo XML del CFDI" htmlFor="xml" error={errors.xml} required>
                        <input
                            id="xml"
                            type="file"
                            accept=".xml,application/xml,text/xml"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('xml', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <FormField label="Archivo PDF (opcional)" htmlFor="pdf" error={errors.pdf}>
                        <input
                            id="pdf"
                            type="file"
                            accept=".pdf,application/pdf"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <FormField label="Notas (opcional)" htmlFor="notas" error={errors.notas}>
                        <Input id="notas" value={data.notas} onChange={(e) => setData('notas', e.target.value)} />
                    </FormField>

                    <div className="modal-action">
                        <Button type="button" variant="ghost" onClick={onClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing || !data.xml}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Continuar
                        </Button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={() => !processing && onClose()}></div>
        </dialog>
    );
}
