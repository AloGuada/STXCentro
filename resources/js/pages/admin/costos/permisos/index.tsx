import { DataTable, type Column } from '@/components/data-table';
import { ButtonLink } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPermiso, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';
import { UserCheckIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/permisos' },
    { title: 'Niveles Aprobacion', href: '/admin/costos/permisos' },
];

const columns: Column<CostosPermiso>[] = [
    { key: 'descripcion', label: 'Descripcion', sortable: true },
    { key: 'nivel', label: 'Nivel', sortable: true },
    {
        key: 'tipo_aprobacion',
        label: 'Tipo',
        sortable: true,
        render: (p) => (
            <span className={`badge badge-sm ${p.tipo_aprobacion === 'requisicion' ? 'badge-info' : 'badge-ghost'}`}>
                {p.tipo_aprobacion === 'requisicion' ? 'Requisición' : 'Solicitud de pago'}
            </span>
        ),
    },
];

type Props = {
    permisos: PaginatedData<CostosPermiso>;
    filters: { search?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function PermisosIndex({ permisos, filters, sortBy, sortDir }: Props) {
    const { hasRole } = useCan();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Niveles Aprobacion" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={permisos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar niveles..."
                    createHref="/admin/costos/permisos/create"
                    createLabel="Nuevo Nivel"
                    emptyMessage="No hay niveles de aprobacion configurados"
                    getRowHref={(row) => `/admin/costos/permisos/${row.id}`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                >
                    {hasRole('super-admin') && (
                        <ButtonLink href="/admin/costos/aprobaciones/bandeja" variant="ghost">
                            <UserCheckIcon className="size-4" />
                            Bandeja de aprobador
                        </ButtonLink>
                    )}
                </DataTable>
            </div>
        </AppLayout>
    );
}
