import { router, useForm } from '@inertiajs/react';
import { CheckIcon, Loader2Icon, XIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { formatMoney } from '@/components/costos/monto';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { PortalFacturaPreview } from '@/types/models';

/**
 * Paso 2 del alta de factura: los datos que el servidor leyó del CFDI. Hasta que
 * el proveedor confirma aquí no existe ninguna factura; cancelar borra los
 * archivos temporales.
 */
export function ConfirmarFacturaModal({ preview }: { preview: PortalFacturaPreview }) {
    const { fiscal, archivos, moneda } = preview;

    const { data, setData, post, processing, errors } = useForm<{ notas: string; pdf: File | null }>({
        notas: preview.notas ?? '',
        pdf: null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/portal/facturas', { forceFormData: true, preserveScroll: true });
    };

    const cancelar = () => {
        if (!confirm('¿Descartar esta factura? Los archivos cargados se eliminarán.')) return;
        router.post('/portal/facturas/cancel-preview', {}, { preserveScroll: true });
    };

    return (
        <dialog className="modal modal-open">
            <div className="modal-box w-11/12 max-w-3xl">
                <h3 className="text-lg font-bold">Confirma los datos del CFDI</h3>
                <p className="mt-1 text-sm text-base-content/60">
                    Se tomaron del XML que subiste para la orden{' '}
                    <span className="font-semibold text-base-content">{preview.orden_compra_folio}</span>.
                </p>

                <div className="mt-4 rounded-xl border border-base-300 p-4">
                    <div className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <Dato label="UUID fiscal" valor={fiscal.uuid_fiscal ?? '—'} mono />
                        <Dato label="Folio fiscal" valor={fiscal.folio_fiscal ?? '—'} />
                        <Dato label="Fecha de factura" valor={fiscal.fecha_factura ?? '—'} />
                        <Dato label="Moneda" valor={moneda.toUpperCase()} />
                        <Dato label="RFC emisor" valor={fiscal.rfc_emisor ?? '—'} mono />
                        <Dato label="RFC receptor" valor={fiscal.rfc_receptor ?? '—'} mono />
                    </div>

                    <div className="my-4 border-t border-base-300" />

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Dato label="Subtotal" valor={formatMoney(fiscal.subtotal, moneda)} />
                        <Dato label="IVA trasladado" valor={formatMoney(fiscal.iva_trasladado, moneda)} />
                        <Dato label="IVA retenido" valor={formatMoney(fiscal.iva_retenido, moneda)} />
                        <Dato label="ISR retenido" valor={formatMoney(fiscal.isr_retenido, moneda)} />
                    </div>

                    <div className="mt-4 flex items-center justify-between border-t border-base-300 pt-3">
                        <span className="text-sm text-base-content/60">Total CFDI</span>
                        <span className="text-2xl font-bold text-primary">{formatMoney(fiscal.total, moneda)}</span>
                    </div>
                </div>

                <div className="mt-3 text-sm">
                    <span className="text-base-content/60">XML: </span>
                    <span className="font-mono">{archivos.xml_original}</span>
                    <span className="ml-3 text-base-content/60">PDF: </span>
                    {archivos.pdf_original ? (
                        <span className="font-mono">{archivos.pdf_original}</span>
                    ) : (
                        <span className="italic text-base-content/40">no cargado</span>
                    )}
                </div>

                <form onSubmit={submit} className="mt-4 space-y-4 border-t border-base-300 pt-4">
                    <FormField
                        label={archivos.pdf_original ? 'Reemplazar PDF (opcional)' : 'Agregar PDF (opcional)'}
                        htmlFor="pdf-confirmar"
                        error={errors.pdf}
                    >
                        <input
                            id="pdf-confirmar"
                            type="file"
                            accept=".pdf,application/pdf"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <FormField label="Notas (opcional)" htmlFor="notas-confirmar" error={errors.notas}>
                        <Input id="notas-confirmar" value={data.notas} onChange={(e) => setData('notas', e.target.value)} />
                    </FormField>

                    <div className="modal-action">
                        <Button type="button" variant="outline" onClick={cancelar} disabled={processing}>
                            <XIcon className="size-4" /> Descartar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2Icon className="size-4 animate-spin" /> : <CheckIcon className="size-4" />}
                            Confirmar y registrar
                        </Button>
                    </div>
                </form>
            </div>
        </dialog>
    );
}

function Dato({ label, valor, mono }: { label: string; valor: string; mono?: boolean }) {
    return (
        <div>
            <div className="text-xs text-base-content/60">{label}</div>
            <div className={mono ? 'font-mono text-sm' : 'text-sm'}>{valor}</div>
        </div>
    );
}
