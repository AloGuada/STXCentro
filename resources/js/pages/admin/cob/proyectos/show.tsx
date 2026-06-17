import { Head, Link } from '@inertiajs/react';
import { Fragment } from 'react';
import { formatearMXN } from '@/components/cob/money-display';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Proyecto } from '@/types/models';

type Props = {
    proyecto: Proyecto;
};

function ObraFila({ obra, hijo = false }: { obra: Obra; hijo?: boolean }) {
    return (
        <tr className="hover">
            <td className={hijo ? 'pl-8' : ''}>
                {hijo && <span className="text-base-content/40 mr-1">↳</span>}
                <Link href={`/admin/cob/obras/${obra.id}`} className="link link-primary font-medium">
                    {obra.no} — {obra.descripcion}
                </Link>
            </td>
            <td>
                <span className={`badge badge-sm ${obra.tipo === 'adicional' ? 'badge-warning' : 'badge-ghost'}`}>
                    {obra.tipo ?? 'base'}
                </span>
            </td>
            <td className="text-right">{obra.partidas?.length ?? 0}</td>
            <td>
                <span className={`badge badge-sm ${obra.estatus === 'abierta' ? 'badge-success' : 'badge-ghost'}`}>
                    {obra.estatus}
                </span>
            </td>
        </tr>
    );
}

export default function ProyectoShow({ proyecto }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/dashboard' },
        { title: 'Proyectos', href: '/admin/cob/proyectos' },
        { title: proyecto.descripcion, href: `/admin/cob/proyectos/${proyecto.id}` },
    ];

    const obrasBase = (proyecto.obras ?? []).filter((o) => o.tipo !== 'adicional');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${proyecto.no} - Proyecto`} />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{proyecto.descripcion}</h1>
                    <p className="text-base-content/60 text-sm">
                        {proyecto.no} · {proyecto.cliente?.nombre ?? 'Sin cliente'}
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {[
                        ['Contrato', proyecto.tipo_contrato ?? '-'],
                        ['Monto', proyecto.monto != null ? formatearMXN(Number(proyecto.monto)) : '-'],
                        ['Anticipo', proyecto.anticipo != null ? formatearMXN(Number(proyecto.anticipo)) : '-'],
                        ['Garantía', proyecto.garantia != null ? formatearMXN(Number(proyecto.garantia)) : '-'],
                    ].map(([label, valor]) => (
                        <div key={label} className="rounded-box border border-base-300 p-3">
                            <div className="text-base-content/60 text-xs">{label}</div>
                            <div className="font-medium">{valor}</div>
                        </div>
                    ))}
                </div>

                <div className="rounded-box border border-base-300">
                    <div className="border-b border-base-300 px-4 py-2 font-semibold">Obras y adicionales</div>
                    <table className="table text-sm">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Tipo</th>
                                <th className="text-right">Partidas</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            {obrasBase.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="text-base-content/60 py-6 text-center">
                                        Este proyecto no tiene obras
                                    </td>
                                </tr>
                            ) : (
                                obrasBase.map((obra) => (
                                    <Fragment key={obra.id}>
                                        <ObraFila obra={obra} />
                                        {(obra.sub_obras ?? []).map((sub) => (
                                            <ObraFila key={sub.id} obra={sub} hijo />
                                        ))}
                                    </Fragment>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
