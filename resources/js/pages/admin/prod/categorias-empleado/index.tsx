import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, ProdCategoriaEmpleado } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Categorias de empleado', href: '/admin/prod/categorias-empleado' },
];

const columns: Column<ProdCategoriaEmpleado>[] = [
    { key: 'nombre', label: 'Categoria', render: (c) => <span className="font-medium">{c.nombre}</span> },
    {
        key: 'valor',
        label: 'Valor (peso)',
        className: 'text-right',
        render: (c) => <span className="font-mono text-sm">{Number(c.valor).toLocaleString('es-MX')}</span>,
    },
    {
        key: 'empleados_count',
        label: 'Empleados',
        className: 'text-right',
        render: (c) => <span className="font-mono text-sm">{c.empleados_count ?? 0}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        className: 'text-center',
        render: (c) => (
            <span className={`badge badge-sm ${c.activo ? 'badge-success' : 'badge-ghost'}`}>
                {c.activo ? 'Activa' : 'Inactiva'}
            </span>
        ),
    },
];

type Props = {
    categorias: PaginatedData<ProdCategoriaEmpleado>;
    filters: { search?: string };
};

export default function CategoriasEmpleadoIndex({ categorias, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorias de empleado" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Categorías de empleado</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        El valor es un <strong>peso</strong>, no dinero: reparte el excedente del destajo una vez
                        cubierto el sueldo base de todo el grupo.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={categorias}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar categoria..."
                    createHref="/admin/prod/categorias-empleado/create"
                    createLabel="Nueva categoria"
                    emptyMessage="No hay categorias registradas"
                    getRowHref={(c) => `/admin/prod/categorias-empleado/${c.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
