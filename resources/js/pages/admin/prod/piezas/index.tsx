import { DataTable, type Column } from '@/components/data-table';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData, Pieza } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Piezas', href: '/admin/prod/piezas' },
];

type PiezaWithObra = Pieza & {
    obra: Obra;
};

const columns: Column<PiezaWithObra>[] = [
    { key: 'marca', label: 'Marca' },
    { key: 'descripcion', label: 'Descripcion' },
    {
        key: 'obra_id',
        label: 'Obra',
        render: (pieza) => <span>{pieza.obra?.no} - {pieza.obra?.descripcion}</span>,
    },
    {
        key: 'peso',
        label: 'Peso (kg)',
        render: (pieza) => <span className="font-mono text-sm">{Number(pieza.peso).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</span>,
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        render: (pieza) => <span className="font-mono text-sm">{pieza.cantidad}</span>,
    },
    {
        key: 'version',
        label: 'Version',
        render: (pieza) => <span className="font-mono text-sm">{pieza.version}</span>,
    },
];

type Props = {
    piezas: PaginatedData<PiezaWithObra>;
    obras: Obra[];
    filters: { search?: string; obra_id?: string };
};

export default function PiezasIndex({ piezas, obras, filters }: Props) {
    const handleObraFilter = (value: string) => {
        router.get('/admin/prod/piezas', { obra_id: value || undefined, search: filters.search || undefined }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Piezas" />

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
                    data={piezas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar piezas..."
                    createHref="/admin/prod/piezas/create"
                    createLabel="Nueva Pieza"
                    emptyMessage="No hay piezas registradas"
                    getRowHref={(pieza) => `/admin/prod/piezas/${pieza.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
