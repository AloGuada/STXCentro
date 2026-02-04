import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departamentos', href: '/admin/departamentos' },
];

const columns: Column<Departamento>[] = [
    { key: 'descripcion', label: 'Descripción' },
    { key: 'manager', label: 'Manager' },
    {
        key: 'manager_usuario',
        label: 'Usuario Manager',
        render: (dept) => dept.manager_usuario?.name ?? '-',
    },
];

type Props = {
    departamentos: PaginatedData<Departamento>;
    filters: { search?: string };
};

export default function DepartamentosIndex({ departamentos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Departamentos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={departamentos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar departamentos..."
                    createHref="/admin/departamentos/create"
                    createLabel="Nuevo Departamento"
                    emptyMessage="No hay departamentos registrados"
                    getRowHref={(dept) => `/admin/departamentos/${dept.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
