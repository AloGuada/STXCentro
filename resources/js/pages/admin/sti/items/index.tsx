import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    ITEM_ESTADO_COLORS,
    ITEM_ESTADO_LABELS,
    type PaginatedData,
    type StiItem,
    type StiItemEstado,
    type StiItemTipo,
} from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Inventario', href: '/admin/sti/items' },
];

const columns: Column<StiItem>[] = [
    { key: 'descripcion', label: 'Descripción' },
    {
        key: 'tipo',
        label: 'Tipo',
        render: (item) => item.tipo?.descripcion ?? '-',
    },
    {
        key: 'no_serie',
        label: 'No. Serie',
        render: (item) => item.no_serie ?? '-',
    },
    {
        key: 'costo',
        label: 'Costo',
        render: (item) => (
            <span className="font-mono text-sm">
                ${Number(item.costo).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
            </span>
        ),
    },
    {
        key: 'estado',
        label: 'Estado',
        render: (item) => (
            <span className={`badge badge-sm ${ITEM_ESTADO_COLORS[item.estado] ?? ''}`}>
                {ITEM_ESTADO_LABELS[item.estado] ?? item.estado}
            </span>
        ),
    },
    {
        key: 'grupo',
        label: 'Equipo Asignado',
        render: (item) => item.grupo?.equipo?.descripcion ?? '-',
    },
];

type Props = {
    items: PaginatedData<StiItem>;
    tipos: StiItemTipo[];
    filters: { search?: string; tipo_id?: string; estado?: string };
};

export default function ItemsIndex({ items, tipos, filters }: Props) {
    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/admin/sti/items',
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventario" />

            <div className="p-6">
                {/* Filtros */}
                <div className="mb-4 flex items-center gap-4">
                    <select
                        className="select select-bordered select-sm"
                        value={filters.tipo_id ?? ''}
                        onChange={(e) => handleFilterChange('tipo_id', e.target.value)}
                    >
                        <option value="">Todos los tipos</option>
                        {tipos.map((tipo) => (
                            <option key={tipo.id} value={tipo.id}>
                                {tipo.descripcion}
                            </option>
                        ))}
                    </select>
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estado ?? ''}
                        onChange={(e) => handleFilterChange('estado', e.target.value)}
                    >
                        <option value="">Todos los estados</option>
                        {(Object.entries(ITEM_ESTADO_LABELS) as [StiItemEstado, string][]).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </div>

                <DataTable
                    columns={columns}
                    data={items}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar items..."
                    createHref="/admin/sti/items/create"
                    createLabel="Nuevo Item"
                    emptyMessage="No hay items registrados"
                    getRowHref={(item) => `/admin/sti/items/${item.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
