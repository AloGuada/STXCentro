import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeTipo } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacen, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/almacenes' },
    { title: 'Almacenes', href: '/admin/almacen/almacenes' },
];

const columns: Column<AlmAlmacen>[] = [
    {
        key: 'clave',
        label: 'Clave',
        render: (a) => <span className="badge badge-ghost font-mono font-medium">{a.clave}</span>,
    },
    { key: 'nombre', label: 'Nombre', render: (a) => <span className="font-medium">{a.nombre}</span> },
    {
        key: 'obra_id',
        label: 'Obra',
        render: (a) =>
            a.obra ? (
                <span>
                    <span className="font-mono">{a.obra.no}</span>
                    <span className="text-base-content/60"> · {a.obra.descripcion}</span>
                </span>
            ) : (
                <span className="badge badge-sm badge-info badge-outline">Central</span>
            ),
    },
    {
        key: 'tipo',
        label: 'Tipo',
        render: (a) => <span className="badge badge-sm badge-ghost">{etiquetaDeTipo(a.tipo)}</span>,
    },
    {
        key: 'responsable_id',
        label: 'Responsable',
        render: (a) =>
            a.responsable ? a.responsable.name : <span className="text-base-content/40">Sin asignar</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        className: 'text-center',
        render: (a) => (
            <span className={`badge badge-sm ${a.activo ? 'badge-success' : 'badge-ghost'}`}>
                {a.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
];

type Props = {
    almacenes: PaginatedData<AlmAlmacen>;
    filters: { search?: string };
};

export default function AlmacenesIndex({ almacenes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Almacenes" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Almacenes</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Dónde vive el material. Un almacén con obra es de esa obra; uno sin obra es central y surte a
                        todas.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={almacenes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por clave, nombre u obra..."
                    createHref="/admin/almacen/almacenes/create"
                    createLabel="Nuevo almacén"
                    emptyMessage="No hay almacenes registrados"
                    getRowHref={(a) => `/admin/almacen/almacenes/${a.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
