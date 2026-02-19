import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura } from '@/types/models';
import { FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    factura: CostosFactura;
};

export default function PortalFacturaShow({ factura }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Facturas', href: '/portal/facturas' },
        { title: factura.folio, href: `/portal/facturas/${factura.id}` },
    ];

    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={factura.folio} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{factura.folio}</h1>
                    <div className="flex items-center gap-2 mt-1">
                        <span className={`badge ${FACTURA_ESTATUS_COLORS[factura.estatus]}`}>
                            {FACTURA_ESTATUS_LABELS[factura.estatus]}
                        </span>
                        {factura.aprobada_costos && (
                            <span className="badge badge-success">Aprobada Costos</span>
                        )}
                        {factura.aceptada_contabilidad && (
                            <span className="badge badge-info">Aceptada Contabilidad</span>
                        )}
                        {factura.orden_compra && (
                            <Link href={`/portal/ordenes-compra/${factura.orden_compra.id}`} className="link link-primary text-sm">
                                OC: {factura.orden_compra.folio}
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Factura</span>
                            <p className="font-medium">
                                {factura.fecha_factura ? new Date(factura.fecha_factura).toLocaleDateString() : '-'}
                            </p>
                        </div>
                        {factura.uuid_fiscal && (
                            <div>
                                <span className="text-sm text-base-content/60">UUID Fiscal</span>
                                <p className="font-mono text-sm">{factura.uuid_fiscal}</p>
                            </div>
                        )}
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Subtotal</span>
                            <p className="font-medium">{formatMoney(factura.subtotal)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">IVA</span>
                            <p className="font-medium">{formatMoney(factura.iva)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Total</span>
                            <p className="font-medium text-lg">{formatMoney(factura.total)}</p>
                        </div>
                    </div>
                </div>

                {/* Entregas */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Entregas</h2>
                    {!factura.entregas || factura.entregas.length === 0 ? (
                        <p className="text-base-content/60">No hay entregas registradas.</p>
                    ) : (
                        <div className="space-y-3">
                            {factura.entregas.map((e) => (
                                <div key={e.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="flex justify-between">
                                        <span className="font-medium">Entrega #{e.id}</span>
                                        <span className="text-sm text-base-content/60">
                                            {new Date(e.fecha_entrega).toLocaleDateString()}
                                        </span>
                                    </div>
                                    {e.observaciones && (
                                        <p className="text-sm text-base-content/60 mt-1">{e.observaciones}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pago */}
                {factura.pago && (
                    <div>
                        <h2 className="text-lg font-medium mb-3">Pago</h2>
                        <div className="rounded-lg border border-base-300 p-4">
                            <div className="flex items-center gap-3">
                                <Link href={`/portal/pagos/${factura.pago.id}`} className="link link-primary font-medium">
                                    {factura.pago.folio}
                                </Link>
                                <span className={`badge ${PAGO_ESTATUS_COLORS[factura.pago.estatus]}`}>
                                    {PAGO_ESTATUS_LABELS[factura.pago.estatus]}
                                </span>
                                <span>{formatMoney(factura.pago.monto_pago)}</span>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
