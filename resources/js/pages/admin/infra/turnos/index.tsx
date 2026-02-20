import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraTurno, PaginatedData } from '@/types/models';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Turnos', href: '/admin/infra/turnos' },
];

const DIA_LABELS: Record<number, string> = {
    1: 'L',
    2: 'M',
    3: 'Mi',
    4: 'J',
    5: 'V',
    6: 'S',
    7: 'D',
};

const columns: Column<InfraTurno>[] = [
    { key: 'nombre', label: 'Nombre' },
    { key: 'hora_inicio', label: 'Inicio' },
    { key: 'hora_fin', label: 'Fin' },
    { key: 'orden', label: 'Orden' },
    {
        key: 'dias_semana',
        label: 'Días',
        render: (turno) => (
            <div className="flex gap-1">
                {[1, 2, 3, 4, 5, 6, 7].map((dia) => {
                    const activo = turno.dias_semana?.some((d) => d.dia_semana === dia);
                    return (
                        <Badge key={dia} variant={activo ? 'default' : 'secondary'}>
                            {DIA_LABELS[dia]}
                        </Badge>
                    );
                })}
            </div>
        ),
    },
    {
        key: 'activo',
        label: 'Estado',
        render: (turno) => <Badge variant={turno.activo ? 'default' : 'secondary'}>{turno.activo ? 'Activo' : 'Inactivo'}</Badge>,
    },
];

type Props = {
    turnos: PaginatedData<InfraTurno>;
    filters: { search?: string };
};

export default function TurnosIndex({ turnos, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Turnos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={turnos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar turnos..."
                    createHref="/admin/infra/turnos/create"
                    createLabel="Nuevo Turno"
                    emptyMessage="No hay turnos registrados"
                    getRowHref={(turno) => `/admin/infra/turnos/${turno.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
