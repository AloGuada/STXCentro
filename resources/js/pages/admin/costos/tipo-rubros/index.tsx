import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosTipoRubro, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/tipo-rubros' },
    { title: 'Tipo Rubros', href: '/admin/costos/tipo-rubros' },
];

const columns: Column<CostosTipoRubro>[] = [
    { key: 'descripcion', label: 'Descripción' },
    {
        key: 'rubros_count',
        label: 'Rubros',
        render: (tr) => tr.rubros_count ?? 0,
    },
    {
        key: 'created_at',
        label: 'Creado',
        render: (tr) => new Date(tr.created_at).toLocaleDateString(),
    },
];

type Props = {
    tipoRubros: PaginatedData<CostosTipoRubro>;
    filters: { search?: string };
};

export default function TipoRubrosIndex({ tipoRubros, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipo Rubros" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipoRubros}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipo rubros..."
                    createHref="/admin/costos/tipo-rubros/create"
                    createLabel="Nuevo Tipo Rubro"
                    emptyMessage="No hay tipos de rubro registrados"
                    getRowHref={(tr) => `/admin/costos/tipo-rubros/${tr.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
