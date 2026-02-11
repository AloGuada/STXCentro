import { DataTable, type Column } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, PaginatedData, ProdGrupoTrabajo, ProdRegistro } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Registros', href: '/admin/prod/registros' },
];

type RegistroRow = ProdRegistro & {
    concepto: Concepto & { obra: Obra };
    grupo_trabajo: ProdGrupoTrabajo;
};

const columns: Column<RegistroRow>[] = [
    {
        key: 'fecha',
        label: 'Fecha',
        render: (r) => <span className="font-mono text-sm">{r.fecha}</span>,
    },
    {
        key: 'concepto_id',
        label: 'Concepto',
        render: (r) => <span>{r.concepto?.marca} - {r.concepto?.descripcion}</span>,
    },
    {
        key: 'grupo_trabajo_id',
        label: 'Grupo',
        render: (r) => <span>{r.grupo_trabajo?.descripcion}</span>,
    },
    {
        key: 'cantidad',
        label: 'Cantidad',
        render: (r) => <span className="font-mono text-sm">{r.cantidad}</span>,
    },
];

type Props = {
    registros: PaginatedData<RegistroRow>;
    gruposTrabajo: ProdGrupoTrabajo[];
    filters: { grupo_trabajo_id?: string; fecha_inicio?: string; fecha_fin?: string };
};

export default function RegistrosIndex({ registros, gruposTrabajo, filters }: Props) {
    const applyFilters = (newFilters: Record<string, string | undefined>) => {
        router.get('/admin/prod/registros', { ...filters, ...newFilters }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registros" />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-center gap-4">
                    <div className="w-48">
                        <Select
                            value={filters.grupo_trabajo_id ?? ''}
                            onValueChange={(v) => applyFilters({ grupo_trabajo_id: v || undefined })}
                            placeholder="Filtrar por grupo"
                        >
                            <option value="">Todos los grupos</option>
                            {gruposTrabajo.map((g) => (
                                <option key={g.id} value={g.id}>{g.descripcion}</option>
                            ))}
                        </Select>
                    </div>
                    <div className="w-40">
                        <Input
                            type="date"
                            value={filters.fecha_inicio ?? ''}
                            onChange={(e) => applyFilters({ fecha_inicio: e.target.value || undefined })}
                            placeholder="Desde"
                        />
                    </div>
                    <div className="w-40">
                        <Input
                            type="date"
                            value={filters.fecha_fin ?? ''}
                            onChange={(e) => applyFilters({ fecha_fin: e.target.value || undefined })}
                            placeholder="Hasta"
                        />
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={registros}
                    createHref="/admin/prod/registros/create"
                    createLabel="Nuevo Registro"
                    emptyMessage="No hay registros de produccion"
                />
            </div>
        </AppLayout>
    );
}
