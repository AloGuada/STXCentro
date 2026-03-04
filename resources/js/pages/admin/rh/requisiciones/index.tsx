import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhRequisicion } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Requisiciones', href: '/admin/rh/requisiciones' },
];

const estadoVariant = (estado: string) => {
    switch (estado) {
        case 'abierta':
            return 'default';
        case 'borrador':
            return 'secondary';
        case 'en_proceso':
            return 'warning';
        case 'cerrada':
            return 'secondary';
        case 'cancelada':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const TIPO_LABELS: Record<string, string> = {
    nueva: 'Nueva',
    reemplazo: 'Reemplazo',
    temporal: 'Temporal',
};

const columns: Column<RhRequisicion>[] = [
    { key: 'folio', label: 'Folio' },
    {
        key: 'puesto_id',
        label: 'Puesto',
        render: (req) => req.puesto?.nombre ?? '-',
    },
    { key: 'cantidad', label: 'Cantidad' },
    {
        key: 'estado',
        label: 'Estado',
        render: (req) => <Badge variant={estadoVariant(req.estado)}>{req.estado}</Badge>,
    },
    {
        key: 'tipo_requisicion',
        label: 'Tipo',
        render: (req) => TIPO_LABELS[req.tipo_requisicion] ?? req.tipo_requisicion,
    },
    { key: 'fecha_creacion', label: 'Fecha Creacion' },
];

type Props = {
    requisiciones: PaginatedData<RhRequisicion>;
    filters: { search?: string };
};

export default function RequisicionesIndex({ requisiciones, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Requisiciones" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={requisiciones}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar requisiciones..."
                    createHref="/admin/rh/requisiciones/create"
                    createLabel="Nueva Requisicion"
                    emptyMessage="No hay requisiciones registradas"
                    getRowHref={(req) => `/admin/rh/requisiciones/${req.id}`}
                />
            </div>
        </AppLayout>
    );
}
