import { Head, Link, router } from '@inertiajs/react';
import { SearchInput } from '@/components/data-table/search-input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Proyecto } from '@/types/models';

type EstatusFiltro = 'abierta' | 'cerrada' | 'todas';

const ESTATUS_FILTROS: { key: EstatusFiltro; label: string }[] = [
    { key: 'abierta', label: 'Abiertos' },
    { key: 'cerrada', label: 'Cerrados' },
    { key: 'todas', label: 'Todos' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Proyectos', href: '/admin/cob/proyectos' },
];

type Props = {
    proyectos: Proyecto[];
    filters: { search?: string; estatus: EstatusFiltro };
};

export default function ProyectosIndex({ proyectos, filters }: Props) {
    const cambiarEstatus = (estatus: EstatusFiltro) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, estatus }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proyectos - Cobranza" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Proyectos</h1>
                    <div className="flex items-center gap-2">
                        <div role="tablist" className="tabs tabs-boxed tabs-sm">
                            {ESTATUS_FILTROS.map(({ key, label }) => (
                                <button
                                    key={key}
                                    role="tab"
                                    className={`tab ${filters.estatus === key ? 'tab-active' : ''}`}
                                    onClick={() => cambiarEstatus(key)}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <SearchInput placeholder="Buscar proyectos..." defaultValue={filters.search} className="max-w-xs" />
                        <Link href="/admin/cob/proyectos/create" className="btn btn-primary btn-sm">
                            Nuevo proyecto
                        </Link>
                    </div>
                </div>

                <div className="overflow-auto rounded-box border border-base-300">
                    <table className="table table-zebra text-sm">
                        <thead className="bg-base-100">
                            <tr>
                                <th>Cliente</th>
                                <th>No</th>
                                <th>Proyecto</th>
                                <th>Contrato</th>
                                <th className="text-right">Obras</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            {proyectos.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-base-content/60 py-8 text-center">
                                        No hay proyectos registrados
                                    </td>
                                </tr>
                            ) : (
                                proyectos.map((p) => (
                                    <tr key={p.id} className="hover">
                                        <td>{p.cliente?.nombre ?? '-'}</td>
                                        <td>{p.no}</td>
                                        <td>
                                            <Link href={`/admin/cob/proyectos/${p.id}`} className="link link-primary font-medium">
                                                {p.descripcion}
                                            </Link>
                                        </td>
                                        <td>{p.tipo_contrato ?? '-'}</td>
                                        <td className="text-right">{p.obras?.length ?? 0}</td>
                                        <td>
                                            <span className={`badge badge-sm ${p.estatus === 'abierta' ? 'badge-success' : 'badge-ghost'}`}>
                                                {p.estatus}
                                            </span>
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
