import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiTecnico } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { CheckCircleIcon, XCircleIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tecnicos', href: '/admin/sti/tecnicos' },
];

const columns: Column<StiTecnico>[] = [
    { key: 'descripcion', label: 'Nombre' },
    {
        key: 'activo',
        label: 'Estado',
        render: (tecnico) => (
            <span className={`badge ${tecnico.activo ? 'badge-success' : 'badge-error'} gap-1`}>
                {tecnico.activo ? (
                    <>
                        <CheckCircleIcon className="size-3" />
                        Activo
                    </>
                ) : (
                    <>
                        <XCircleIcon className="size-3" />
                        Inactivo
                    </>
                )}
            </span>
        ),
    },
];

type Props = {
    tecnicos: PaginatedData<StiTecnico>;
    filters: { search?: string; todos?: string };
};

export default function TecnicosIndex({ tecnicos, filters }: Props) {
    const handleToggleInactivos = () => {
        router.get(
            '/admin/sti/tecnicos',
            { ...filters, todos: filters.todos === '1' ? undefined : '1' },
            { preserveState: true, preserveScroll: true }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tecnicos" />

            <div className="p-6">
                <div className="mb-4">
                    <label className="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={filters.todos === '1'}
                            onChange={handleToggleInactivos}
                        />
                        <span className="text-sm">Mostrar inactivos</span>
                    </label>
                </div>

                <DataTable
                    columns={columns}
                    data={tecnicos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tecnicos..."
                    createHref="/admin/sti/tecnicos/create"
                    createLabel="Nuevo Tecnico"
                    emptyMessage="No hay tecnicos registrados"
                    getRowHref={(tecnico) => `/admin/sti/tecnicos/${tecnico.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
