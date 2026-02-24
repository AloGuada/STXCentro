import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Cliente, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Clientes', href: '/admin/cob/clientes' },
];

type ClienteRow = Cliente & { contactos_count: number };

const columns: Column<ClienteRow>[] = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'rfc', label: 'RFC' },
    { key: 'email', label: 'Email' },
    {
        key: 'activo',
        label: 'Activo',
        render: (cliente) => (
            <span className={`badge badge-sm ${cliente.activo ? 'badge-success' : 'badge-ghost'}`}>
                {cliente.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
    {
        key: 'contactos_count',
        label: 'Contactos',
        render: (cliente) => cliente.contactos_count ?? 0,
    },
];

type Props = {
    clientes: PaginatedData<ClienteRow>;
    filters: { search?: string };
};

export default function ClientesIndex({ clientes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clientes" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={clientes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar clientes..."
                    createHref="/admin/cob/clientes/create"
                    createLabel="Nuevo Cliente"
                    emptyMessage="No hay clientes registrados"
                    getRowHref={(cliente) => `/admin/cob/clientes/${cliente.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
