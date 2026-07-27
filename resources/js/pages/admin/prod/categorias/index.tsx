import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdCategoria } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Categorias', href: '/admin/prod/categorias' },
];

const columns: Column<ProdCategoria>[] = [
    { key: 'nombre', label: 'Nombre', render: (c) => <span className="font-medium">{c.nombre}</span> },
    {
        key: 'conceptos_count',
        label: 'Piezas',
        className: 'text-right',
        render: (c) => <span className="font-mono text-sm">{c.conceptos_count ?? 0}</span>,
    },
];

type Props = {
    categorias: PaginatedData<ProdCategoria>;
    filters: { search?: string };
};

export default function CategoriasIndex({ categorias, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorias" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Categorias de piezas</h1>
                    <p className="mt-1 text-sm text-base-content/60">Catálogo de categorías para clasificar las piezas.</p>
                </div>

                <DataTable
                    columns={columns}
                    data={categorias}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar categorias..."
                    createHref="/admin/prod/categorias/create"
                    createLabel="Nueva categoria"
                    emptyMessage="No hay categorias registradas"
                    getRowHref={(c) => `/admin/prod/categorias/${c.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
