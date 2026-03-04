import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPuesto } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Puestos', href: '/admin/rh/puestos' },
];

const columns: Column<RhPuesto>[] = [
    { key: 'nombre', label: 'Nombre' },
    {
        key: 'departamento_id',
        label: 'Departamento',
        render: (puesto) => puesto.departamento?.descripcion ?? '-',
    },
    { key: 'codigo', label: 'Codigo' },
    { key: 'ubicacion', label: 'Ubicacion' },
];

type Props = {
    puestos: PaginatedData<RhPuesto>;
    filters: { search?: string };
};

export default function PuestosIndex({ puestos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Puestos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={puestos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar puestos..."
                    createHref="/admin/rh/puestos/create"
                    createLabel="Nuevo Puesto"
                    emptyMessage="No hay puestos registrados"
                    getRowHref={(puesto) => `/admin/rh/puestos/${puesto.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
