import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiPlan } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Planes', href: '/admin/sti/planes' },
];

type PlanWithCounts = StiPlan & {
    checks_count: number;
    mantenimientos_count: number;
};

const columns: Column<PlanWithCounts>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'periodicidad',
        label: 'Periodicidad',
        render: (plan) => `${plan.periodicidad} dias`,
    },
    {
        key: 'checks_count',
        label: 'Checks',
        render: (plan) => <span className="font-mono text-sm">{plan.checks_count ?? 0}</span>,
    },
    {
        key: 'mantenimientos_count',
        label: 'Mant. Pendientes',
        render: (plan) => <span className="font-mono text-sm">{plan.mantenimientos_count ?? 0}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (plan) => (
            <span className={`badge badge-sm ${plan.activo ? 'badge-success' : 'badge-ghost'}`}>
                {plan.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
];

type Props = {
    planes: PaginatedData<PlanWithCounts>;
    filters: { search?: string };
};

export default function PlanesIndex({ planes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planes de Mantenimiento" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={planes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar planes..."
                    createHref="/admin/sti/planes/create"
                    createLabel="Nuevo Plan"
                    emptyMessage="No hay planes registrados"
                    getRowHref={(plan) => `/admin/sti/planes/${plan.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
