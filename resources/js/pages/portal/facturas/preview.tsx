import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckIcon, Loader2Icon, XIcon } from 'lucide-react';
import { type FormEvent } from 'react';

type FiscalData = {
    uuid_fiscal: string | null;
    folio_fiscal: string | null;
    fecha_factura: string | null;
    subtotal: number;
    total: number;
    iva_trasladado: number;
    iva_retenido: number;
    isr_retenido: number;
    rfc_emisor: string | null;
    rfc_receptor: string | null;
    impuestos_detalle: unknown;
};

type Props = {
    ordenCompra: { id: number; folio: string; total: number; moneda: string };
    fiscal: FiscalData;
    archivos: { xml_original: string; pdf_original: string | null };
    notas: string | null;
};

const money = (n: number | null | undefined) =>
    Number(n ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function PortalFacturaPreview({ ordenCompra, fiscal, archivos, notas }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Órdenes de Compra', href: '/portal/ordenes-compra' },
        { title: ordenCompra.folio, href: `/portal/ordenes-compra/${ordenCompra.id}` },
        { title: 'Confirmar factura', href: '/portal/facturas/preview' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        notas: notas ?? '',
        pdf: null as File | null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/portal/facturas', { forceFormData: true });
    };

    const cancelar = () => {
        if (!confirm('¿Descartar esta factura y volver a la orden? Los archivos cargados se eliminarán.')) return;
        router.post('/portal/facturas/cancel-preview');
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Confirmar factura" />

            <div className="p-6 max-w-3xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Confirma los datos del CFDI</h1>
                    <p className="text-sm text-base-content/60 mt-1">
                        Estos datos se tomaron directamente del XML que subiste para la OC{' '}
                        <span className="font-semibold text-base-content">{ordenCompra.folio}</span>. Revísalos y confirma para
                        registrar la factura.
                    </p>
                </div>

                <div className="rounded-2xl border border-base-300 bg-base-100 shadow-sm p-6 mb-6">
                    <h2 className="font-semibold mb-4">Datos fiscales</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-6 text-sm">
                        <Row label="UUID fiscal" value={fiscal.uuid_fiscal ?? '—'} mono />
                        <Row label="Folio fiscal" value={fiscal.folio_fiscal ?? '—'} />
                        <Row label="Fecha de factura" value={fiscal.fecha_factura ?? '—'} />
                        <Row label="Moneda" value={ordenCompra.moneda.toUpperCase()} />
                        <Row label="RFC emisor" value={fiscal.rfc_emisor ?? '—'} mono />
                        <Row label="RFC receptor" value={fiscal.rfc_receptor ?? '—'} mono />
                    </div>

                    <div className="border-t border-base-300 my-4" />

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <Stat label="Subtotal" value={`$${money(fiscal.subtotal)}`} />
                        <Stat label="IVA trasladado" value={`$${money(fiscal.iva_trasladado)}`} />
                        <Stat label="IVA retenido" value={`$${money(fiscal.iva_retenido)}`} />
                        <Stat label="ISR retenido" value={`$${money(fiscal.isr_retenido)}`} />
                    </div>

                    <div className="mt-4 border-t border-base-300 pt-3 flex items-center justify-between">
                        <span className="text-sm text-base-content/60">Total CFDI</span>
                        <span className="text-2xl font-bold text-primary">${money(fiscal.total)}</span>
                    </div>
                </div>

                <div className="rounded-2xl border border-base-300 bg-base-100 shadow-sm p-6 mb-6">
                    <h2 className="font-semibold mb-3">Archivos</h2>
                    <div className="text-sm space-y-1">
                        <div>
                            <span className="text-base-content/60">XML: </span>
                            <span className="font-mono">{archivos.xml_original}</span>
                        </div>
                        <div>
                            <span className="text-base-content/60">PDF: </span>
                            {archivos.pdf_original ? (
                                <span className="font-mono">{archivos.pdf_original}</span>
                            ) : (
                                <span className="text-base-content/40 italic">no cargado</span>
                            )}
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="rounded-2xl border border-base-300 bg-base-100 shadow-sm p-6 space-y-4">
                    <h2 className="font-semibold">Datos editables</h2>

                    <FormField
                        label={archivos.pdf_original ? 'Reemplazar PDF (opcional)' : 'Agregar PDF (opcional)'}
                        htmlFor="pdf"
                        error={errors.pdf}
                    >
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

                    <div className="flex items-center justify-end gap-2 pt-2 border-t border-base-300">
                        <Button type="button" variant="outline" onClick={cancelar} disabled={processing}>
                            <XIcon className="size-4" /> Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2Icon className="size-4 animate-spin" /> : <CheckIcon className="size-4" />}
                            Confirmar y registrar
                        </Button>
                    </div>
                </form>
            </div>
        </PortalLayout>
    );
}

function Row({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <div className="text-xs text-base-content/60">{label}</div>
            <div className={mono ? 'font-mono text-sm' : 'text-sm'}>{value}</div>
        </div>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-base-content/60">{label}</div>
            <div className="font-semibold">{value}</div>
        </div>
    );
}
