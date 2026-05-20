import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

type Props = {
    ordenCompra: CostosOrdenCompra;
};

export default function PortalOrdenCompraShow({ ordenCompra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Ordenes de Compra', href: '/portal/ordenes-compra' },
        { title: ordenCompra.folio, href: `/portal/ordenes-compra/${ordenCompra.id}` },
    ];

    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;
    const [showFacturaForm, setShowFacturaForm] = useState(false);
    const tieneEntregas = (ordenCompra.entregas?.length ?? 0) > 0;
    const puedeFacturar = ['pendiente_factura', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && tieneEntregas;

    const { data, setData, post, processing, errors } = useForm({
        orden_compra_id: ordenCompra.id,
        uuid_fiscal: '',
        folio_fiscal: '',
        xml: null as File | null,
        pdf: null as File | null,
        total: '',
        fecha_factura: '',
        notas: '',
    });

    const handleFacturaSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/portal/facturas', {
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
                    {puedeFacturar && <Button onClick={() => setShowFacturaForm(true)}>Subir Factura</Button>}
                </div>

                {['pendiente_factura', 'pendiente_aprobacion'].includes(ordenCompra.estatus) && !tieneEntregas && (
                    <div className="alert alert-warning mb-6">
                        <span>
                            Esta orden aún no tiene recepción registrada por almacén. Podrás subir tu factura en cuanto se confirme la entrega.
                        </span>
                    </div>
                )}

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
                                                    {FACTURA_ESTATUS_LABELS[f.estatus]}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Subir Factura Modal */}
                {showFacturaForm && (
                    <dialog className="modal modal-open">
                        <div className="modal-box w-11/12 max-w-2xl">
                            <h3 className="font-bold text-lg mb-4">Subir Factura</h3>
                            <form onSubmit={handleFacturaSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="UUID Fiscal" htmlFor="uuid_fiscal" error={errors.uuid_fiscal}>
                                        <Input
                                            id="uuid_fiscal"
                                            value={data.uuid_fiscal}
                                            onChange={(e) => setData('uuid_fiscal', e.target.value)}
                                            placeholder="Opcional"
                                        />
                                    </FormField>
                                    <FormField label="Folio Fiscal" htmlFor="folio_fiscal" error={errors.folio_fiscal}>
                                        <Input
                                            id="folio_fiscal"
                                            value={data.folio_fiscal}
                                            onChange={(e) => setData('folio_fiscal', e.target.value)}
                                            placeholder="Opcional"
                                        />
                                    </FormField>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Archivo XML" htmlFor="xml" error={errors.xml}>
                                        <input
                                            id="xml"
                                            type="file"
                                            accept=".xml"
                                            className="file-input file-input-bordered file-input-sm w-full"
                                            onChange={(e) => setData('xml', e.target.files?.[0] ?? null)}
                                        />
                                    </FormField>
                                    <FormField label="Archivo PDF" htmlFor="pdf" error={errors.pdf}>
                                        <input
                                            id="pdf"
                                            type="file"
                                            accept=".pdf"
                                            className="file-input file-input-bordered file-input-sm w-full"
                                            onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                                        />
                                    </FormField>
                                </div>

                                <FormField label="Total" htmlFor="total" error={errors.total} required>
                                    <Input
                                        id="total"
                                        type="number"
                                        step="0.01"
                                        value={data.total}
                                        onChange={(e) => setData('total', e.target.value)}
                                    />
                                </FormField>

                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Fecha Factura" htmlFor="fecha_factura" error={errors.fecha_factura}>
                                        <Input
                                            id="fecha_factura"
                                            type="date"
                                            value={data.fecha_factura}
                                            onChange={(e) => setData('fecha_factura', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Notas" htmlFor="notas" error={errors.notas}>
                                        <Input id="notas" value={data.notas} onChange={(e) => setData('notas', e.target.value)} />
                                    </FormField>
                                </div>

                                <div className="modal-action">
                                    <Button type="button" variant="outline" onClick={() => setShowFacturaForm(false)}>
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                                        Subir Factura
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
