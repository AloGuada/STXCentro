import { calcularDatosProyecto } from '@/components/cob/calculos';
import { formatearMXN } from '@/components/cob/money-display';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Dashboard', href: '/admin/cob/dashboard' },
];

type Props = {
    obras: Obra[];
};

export default function CobDashboardIndex({ obras }: Props) {
    const proyectos = obras.map((obra) => calcularDatosProyecto(obra));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Cobranza" />

            <div className="p-6">
                <h1 className="mb-6 text-2xl font-semibold">Portafolio de Obras</h1>

                <div className="overflow-x-auto rounded-box border border-base-300">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>No Obra</th>
                                <th>Descripcion</th>
                                <th>Cliente</th>
                                <th className="text-right">Presupuesto</th>
                                <th className="text-right">Facturado</th>
                                <th className="text-right">Cobrado</th>
                                <th className="text-right">Por Cobrar</th>
                                <th>% Cobrado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectos.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-base-content/60 py-8 text-center">
                                        No hay obras registradas
                                    </td>
                                </tr>
                            ) : (
                                proyectos.map((p) => (
                                    <tr key={p.obra.id} className="hover cursor-pointer">
                                        <td>
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {p.obra.no}
                                            </Link>
                                        </td>
                                        <td>
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {p.obra.descripcion}
                                            </Link>
                                        </td>
                                        <td>
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {p.obra.cliente?.nombre ?? '-'}
                                            </Link>
                                        </td>
                                        <td className="text-right">
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {formatearMXN(p.presupuestoEjecutar)}
                                            </Link>
                                        </td>
                                        <td className="text-right">
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {formatearMXN(p.totalFacturado)}
                                            </Link>
                                        </td>
                                        <td className="text-right">
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {formatearMXN(p.totalCobrado)}
                                            </Link>
                                        </td>
                                        <td className="text-right">
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                {formatearMXN(p.porCobrar)}
                                            </Link>
                                        </td>
                                        <td>
                                            <Link href={`/admin/cob/obras/${p.obra.id}`} className="block">
                                                <div className="flex items-center gap-2">
                                                    <progress
                                                        className="progress progress-primary w-20"
                                                        value={Math.min(p.porcentajeCobrado, 100)}
                                                        max="100"
                                                    />
                                                    <span className="text-sm">{p.porcentajeCobrado.toFixed(1)}%</span>
                                                </div>
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
