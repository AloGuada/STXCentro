import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosOrdenCompra } from '@/types/models';
import { ORDEN_COMPRA_ESTATUS_COLORS, ORDEN_COMPRA_ESTATUS_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/portal' }];

type Props = {
    ordenesCompra: CostosOrdenCompra[];
    stats: {
        ordenes_activas: number;
        facturas_pendientes: number;
        total_facturado: number;
    };
};

export default function PortalDashboard({ ordenesCompra, stats }: Props) {
    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Portal - Dashboard" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold mb-6">Dashboard</h1>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-title">Ordenes Activas</div>
                        <div className="stat-value text-primary">{stats.ordenes_activas}</div>
                    </div>
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-title">Facturas Pendientes</div>
                        <div className="stat-value text-warning">{stats.facturas_pendientes}</div>
                    </div>
                    <div className="stat bg-base-200 rounded-lg">
                        <div className="stat-title">Total Facturado</div>
                        <div className="stat-value text-success text-2xl">{formatMoney(stats.total_facturado)}</div>
                    </div>
                </div>

                <h2 className="text-lg font-medium mb-3">Ordenes de Compra Recientes</h2>
                {ordenesCompra.length === 0 ? (
                    <p className="text-base-content/60">No hay ordenes de compra.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Obra</th>
                                    <th className="text-right">Total</th>
                                    <th className="text-center">Facturas</th>
                                    <th>Estatus</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ordenesCompra.map((oc) => (
                                    <tr key={oc.id} className="hover">
                                        <td>
                                            <Link href={`/portal/ordenes-compra/${oc.id}`} className="link link-primary">
                                                {oc.folio}
                                            </Link>
                                        </td>
                                        <td>{oc.obra?.descripcion ?? '-'}</td>
                                        <td className="text-right">{formatMoney(oc.total)}</td>
                                        <td className="text-center">{oc.facturas_count ?? 0}</td>
                                        <td>
                                            <span className={`badge ${ORDEN_COMPRA_ESTATUS_COLORS[oc.estatus]}`}>
                                                {ORDEN_COMPRA_ESTATUS_LABELS[oc.estatus]}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
