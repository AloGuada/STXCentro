import { DataTable, type Column } from '@/components/data-table';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, PaginatedData } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Conceptos', href: '/admin/prod/conceptos' },
];

type ConceptoWithObra = Concepto & {
    obra: Obra;
};

const columns: Column<ConceptoWithObra>[] = [
    { key: 'marca', label: 'Marca' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'obra_id',
        label: 'Obra',
        render: (c) => <span>{c.obra?.no} - {c.obra?.descripcion}</span>,
    },
    {
        key: 'peso_unitario',
        label: 'Peso Unit. (kg)',
        render: (c) => <span className="font-mono text-sm">{Number(c.peso_unitario).toLocaleString('es-MX', { minimumFractionDigits: 3 })}</span>,
    },
    {
        key: 'version',
        label: 'Version',
        render: (c) => <span className="font-mono text-sm">{c.version}</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (c) => (
            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${c.activo ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'}`}>
                {c.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
];

type Props = {
    conceptos: PaginatedData<ConceptoWithObra>;
    obras: Obra[];
    filters: { search?: string; obra_id?: string };
};

export default function ConceptosIndex({ conceptos, obras, filters }: Props) {
    const handleObraFilter = (value: string) => {
        router.get('/admin/prod/conceptos', { obra_id: value || undefined, search: filters.search || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conceptos" />

            <div className="p-6">
                <div className="mb-4 flex items-center gap-4">
                    <div className="w-64">
                        <Select
                            value={filters.obra_id ?? ''}
                            onValueChange={handleObraFilter}
                            placeholder="Filtrar por obra"
                        >
                            <option value="">Todas las obras</option>
                            {obras.map((obra) => (
                                <option key={obra.id} value={obra.id}>
                                    {obra.no} - {obra.descripcion}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={conceptos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar conceptos..."
                    createHref="/admin/prod/conceptos/create"
                    createLabel="Nuevo Concepto"
                    emptyMessage="No hay conceptos registrados"
                    getRowHref={(c) => `/admin/prod/conceptos/${c.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
