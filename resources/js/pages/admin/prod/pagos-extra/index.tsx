import { DataTable, type Column } from '@/components/data-table';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdCorte, ProdGrupoTrabajo, ProdPagoExtra, ProdTipoPagoExtra } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Pagos Extra', href: '/admin/prod/pagos-extra' },
];

type PagoExtraRow = ProdPagoExtra & {
    tipo: ProdTipoPagoExtra;
    corte: ProdCorte;
    grupo_trabajo: ProdGrupoTrabajo;
};

const columns: Column<PagoExtraRow>[] = [
    {
        key: 'tipo_id',
        label: 'Tipo',
        render: (p) => <span>{p.tipo?.descripcion}</span>,
    },
    {
        key: 'grupo_trabajo_id',
        label: 'Grupo',
        render: (p) => <span>{p.grupo_trabajo?.descripcion}</span>,
    },
    {
        key: 'corte_id',
        label: 'Semana',
        render: (p) => <span className="font-mono text-sm">S{p.corte?.semana}</span>,
    },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'precio',
        label: 'Precio',
        render: (p) => <span className="font-mono text-sm">${Number(p.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>,
    },
    {
        key: 'dias',
        label: 'Dias',
        render: (p) => <span className="font-mono text-sm">{p.dias}</span>,
    },
    {
        key: 'personas',
        label: 'Personas',
        render: (p) => <span className="font-mono text-sm">{p.personas}</span>,
    },
    {
        key: 'id',
        label: 'Monto',
        render: (p) => <span className="font-mono text-sm font-medium">${Number(p.monto).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>,
    },
];

type Props = {
    pagosExtra: PaginatedData<PagoExtraRow>;
    cortes: ProdCorte[];
    gruposTrabajo: ProdGrupoTrabajo[];
    filters: { corte_id?: string; grupo_trabajo_id?: string };
};

export default function PagosExtraIndex({ pagosExtra, cortes, gruposTrabajo, filters }: Props) {
    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        router.get('/admin/prod/pagos-extra', { ...filters, ...newFilters }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pagos Extra" />

            <div className="p-6">
                {/* Tabs */}
                <div className="tabs tabs-bordered mb-4">
                    <Link href="/admin/prod/registros" className="tab">Registros</Link>
                    <Link href="/admin/prod/pagos-extra" className="tab tab-active">Pagos Extra</Link>
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-4">
                    <div className="w-48">
                        <Select
                            value={filters.corte_id ?? ''}
                            onValueChange={(v) => applyFilters({ corte_id: v || undefined })}
                            placeholder="Filtrar por corte"
                        >
                            <option value="">Todos los cortes</option>
                            {cortes.map((c) => (
                                <option key={c.id} value={c.id}>Semana {c.semana}</option>
                            ))}
                        </Select>
                    </div>
                    <div className="w-48">
                        <Select
                            value={filters.grupo_trabajo_id ?? ''}
                            onValueChange={(v) => applyFilters({ grupo_trabajo_id: v || undefined })}
                            placeholder="Filtrar por grupo"
                        >
                            <option value="">Todos los grupos</option>
                            {gruposTrabajo.map((g) => (
                                <option key={g.id} value={g.id}>{g.descripcion}</option>
                            ))}
                        </Select>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={pagosExtra}
                    createHref="/admin/prod/pagos-extra/create"
                    createLabel="Nuevo Pago Extra"
                    emptyMessage="No hay pagos extra registrados"
                />
            </div>
        </AppLayout>
    );
}
