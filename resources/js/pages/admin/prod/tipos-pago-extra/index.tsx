import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdTipoPagoExtra } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Tipos Pago Extra', href: '/admin/prod/tipos-pago-extra' },
];

const columns: Column<ProdTipoPagoExtra>[] = [
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'orden',
        label: 'Orden',
        render: (t) => <span className="font-mono text-sm">{t.orden}</span>,
    },
    {
        key: 'desgloce',
        label: 'Desgloce',
        render: (t) => (
            <span className={`badge badge-sm ${t.desgloce ? 'badge-success' : 'badge-ghost'}`}>
                {t.desgloce ? 'Si' : 'No'}
            </span>
        ),
    },
];

type Props = {
    tipos: PaginatedData<ProdTipoPagoExtra>;
    filters: { search?: string };
};

export default function TiposPagoExtraIndex({ tipos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos Pago Extra" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={tipos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar tipos..."
                    createHref="/admin/prod/tipos-pago-extra/create"
                    createLabel="Nuevo Tipo"
                    emptyMessage="No hay tipos de pago extra registrados"
                    getRowHref={(t) => `/admin/prod/tipos-pago-extra/${t.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
