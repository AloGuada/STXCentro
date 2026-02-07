import { DataTable, type Column } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, StiMantenimiento, StiPlan } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarIcon, CalendarRangeIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
];

const columns: Column<StiMantenimiento>[] = [
    {
        key: 'equipo_id',
        label: 'Equipo',
        render: (m) => m.equipo?.descripcion ?? '-',
    },
    {
        key: 'plan_id',
        label: 'Plan',
        render: (m) => m.plan?.descripcion ?? '-',
    },
    {
        key: 'fecha_programada',
        label: 'Fecha Programada',
        render: (m) => new Date(m.fecha_programada + 'T00:00:00').toLocaleDateString('es-MX'),
    },
    {
        key: 'fecha_realizado',
        label: 'Fecha Realizado',
        render: (m) => (m.fecha_realizado ? new Date(m.fecha_realizado + 'T00:00:00').toLocaleDateString('es-MX') : '-'),
    },
    {
        key: 'status',
        label: 'Estado',
        render: (m) => (
            <Badge variant={m.status === 'realizado' ? 'success' : 'warning'}>{m.status === 'realizado' ? 'Realizado' : 'Pendiente'}</Badge>
        ),
    },
    {
        key: 'tecnico_id',
        label: 'Tecnico',
        render: (m) => m.tecnico?.descripcion ?? '-',
    },
];

type Props = {
    mantenimientos: PaginatedData<StiMantenimiento>;
    planes: (StiPlan & { equipo?: { descripcion: string } })[];
    filters: { search?: string; plan_id?: string };
};

export default function MantenimientosIndex({ mantenimientos, planes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mantenimientos" />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-4">
                    <Select
                        value={filters.plan_id ?? ''}
                        onValueChange={(value) =>
                            router.get('/admin/sti/mantenimientos', { ...filters, plan_id: value || undefined }, { preserveState: true })
                        }
                        className="w-64"
                    >
                        <option value="">Todos los planes</option>
                        {planes.map((plan) => (
                            <option key={plan.id} value={plan.id}>
                                {plan.descripcion} ({plan.equipo?.descripcion})
                            </option>
                        ))}
                    </Select>

                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/sti/mantenimientos-gantt">
                                <CalendarIcon className="mr-1 size-4" />
                                Vista Mensual
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/sti/mantenimientos-gantt-anual">
                                <CalendarRangeIcon className="mr-1 size-4" />
                                Vista Anual
                            </Link>
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={mantenimientos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar mantenimientos..."
                    createHref="/admin/sti/mantenimientos/create"
                    createLabel="Nuevo Mantenimiento"
                    emptyMessage="No hay mantenimientos registrados"
                    getRowHref={(m) => `/admin/sti/mantenimientos/${m.id}/edit`}
                />
            </div>
        </AppLayout>
    );
}
