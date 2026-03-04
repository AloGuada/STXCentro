import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPersona } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Personas', href: '/admin/rh/personas' },
];

const columns: Column<RhPersona>[] = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'apellido', label: 'Apellido' },
    { key: 'email', label: 'Email' },
    { key: 'telefono', label: 'Telefono' },
];

type Props = {
    personas: PaginatedData<RhPersona>;
    filters: { search?: string };
};

export default function PersonasIndex({ personas, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Personas" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={personas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar personas..."
                    createHref="/admin/rh/personas/create"
                    createLabel="Nueva Persona"
                    emptyMessage="No hay personas registradas"
                    getRowHref={(persona) => `/admin/rh/personas/${persona.id}`}
                />
            </div>
        </AppLayout>
    );
}
