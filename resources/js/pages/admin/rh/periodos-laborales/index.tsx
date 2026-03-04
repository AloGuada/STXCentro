import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPeriodoLaboral } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales' },
];

const estadoVariant = (estado: string) => {
    switch (estado) {
        case 'activo':
            return 'default';
        case 'terminado':
            return 'secondary';
        case 'baja':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const columns: Column<RhPeriodoLaboral>[] = [
    {
        key: 'persona_id',
        label: 'Persona',
        render: (periodo) => periodo.persona ? `${periodo.persona.nombre} ${periodo.persona.apellido}` : '-',
    },
    {
        key: 'puesto_id',
        label: 'Puesto',
        render: (periodo) => periodo.puesto?.nombre ?? '-',
    },
    {
        key: 'requisicion_id',
        label: 'Requisición',
        render: (periodo) => periodo.requisicion?.folio ?? '-',
    },
    { key: 'fecha_inicio', label: 'Fecha Inicio' },
    {
        key: 'estado',
        label: 'Estado',
        render: (periodo) => <Badge variant={estadoVariant(periodo.estado)}>{periodo.estado}</Badge>,
    },
];

type Props = {
    periodos: PaginatedData<RhPeriodoLaboral>;
    filters: { search?: string };
};

export default function PeriodosLaboralesIndex({ periodos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Periodos Laborales" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={periodos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar periodos laborales..."
                    createHref="/admin/rh/periodos-laborales/create"
                    createLabel="Nuevo Periodo Laboral"
                    emptyMessage="No hay periodos laborales registrados"
                    getRowHref={(periodo) => `/admin/rh/periodos-laborales/${periodo.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
