import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { type FormEvent, useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS_PORTAL, ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';

type Props = {
    ordenCompra: CostosOrdenCompra;
    puedeFacturar: boolean;
};

export default function PortalOrdenCompraShow({ ordenCompra, puedeFacturar }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Ordenes de Compra', href: '/portal/ordenes-compra' },
        { title: ordenCompra.folio, href: `/portal/ordenes-compra/${ordenCompra.id}` },
    ];

    const formatMoney = (n: number) => fmtMonto(n, ordenCompra.moneda);
    const [showFacturaForm, setShowFacturaForm] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        orden_compra_id: ordenCompra.id,
        xml: null as File | null,
        pdf: null as File | null,
        notas: '',
    });

    const handleFacturaSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/portal/facturas/preview', {
            forceFormData: true,
            onSuccess: () => setShowFacturaForm(false),
        });
    };

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={ordenCompra.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{ordenCompra.folio}</h1>
                        <div className="flex items-center gap-2 mt-1">
                            {ordenCompra.retrasada ? (
                                <span className="badge badge-error">ENTREGA RETRASADA</span>
                            ) : (
                                <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[ordenCompra.estatus]}`}>
                                    {ORDEN_COMPRA_ESTATUS_LABELS[ordenCompra.estatus]}
                                </span>
                            )}
                        </div>
                    </div>
                    {puedeFacturar && (
                        <Button onClick={() => setShowFacturaForm(true)}>
                            Subir Factura
                        </Button>
                    )}
                </div>

                <div className="alert alert-info mb-6">
                    <span>
                        Puedes subir tu factura cualquier día. Tras subirla, adjunta el comprobante de recepción en la factura para que continúe a pago.
                    </span>
                </div>

                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Obra</span>
                            <p className="font-medium">{ordenCompra.obra?.descripcion ?? '-'}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Departamento</span>
                            <p className="font-medium">{ordenCompra.departamento?.descripcion ?? '-'}</p>
                        </div>
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Total</span>
                            <p className="font-medium text-lg">{formatMoney(ordenCompra.total)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Moneda</span>
                            <p className="font-medium">{ordenCompra.moneda?.toUpperCase()}</p>
                        </div>
                    </div>
                </div>

                {/* Facturas */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Facturas</h2>
                    {!ordenCompra.facturas || ordenCompra.facturas.length === 0 ? (
                        <p className="text-base-content/60">No hay facturas registradas.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Folio</th>
                                        <th className="text-right">Total</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ordenCompra.facturas.map((f) => (
                                        <tr key={f.id}>
                                            <td>
                                                <Link href={`/portal/facturas/${f.id}`} className="link link-primary">
                                                    {f.folio}
                                                </Link>
                                            </td>
                                            <td className="text-right">{formatMoney(f.total)}</td>
                                            <td>
                                                <span className={`badge ${FACTURA_ESTATUS_COLORS[f.estatus]}`}>
                                                    {FACTURA_ESTATUS_LABELS_PORTAL[f.estatus]}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Subir Factura Modal — Paso 1: sube CFDI */}
                {showFacturaForm && (
                    <dialog className="modal modal-open">
                        <div className="modal-box w-11/12 max-w-xl">
                            <h3 className="font-bold text-lg mb-1">Subir factura</h3>
                            <p className="text-sm text-base-content/60 mb-4">
                                Carga el XML del CFDI. En el siguiente paso revisarás los datos extraídos antes de confirmar.
                            </p>
                            <form onSubmit={handleFacturaSubmit} className="space-y-4">
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
                                    <Button type="button" variant="outline" onClick={() => setShowFacturaForm(false)}>
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing || !data.xml}>
                                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                                        Continuar
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowFacturaForm(false)}></div>
                    </dialog>
                )}
            </div>
        </PortalLayout>
    );
}
