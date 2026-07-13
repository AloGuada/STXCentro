import { Head, router } from '@inertiajs/react';
import { DataTable, type Column } from '@/components/data-table';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, PaginatedData, RhPuesto } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/puestos' },
    { title: 'Puestos', href: '/admin/rh/puestos' },
];

const columns: Column<RhPuesto>[] = [
    { key: 'nombre', label: 'Nombre' },
    {
        key: 'departamento_id',
        label: 'Departamento',
        render: (puesto) => puesto.departamento?.descripcion ?? '-',
    },
    { key: 'codigo', label: 'Codigo' },
    { key: 'ubicacion', label: 'Ubicacion' },
];

const TODOS = '__todos__';

type Props = {
    puestos: PaginatedData<RhPuesto>;
    filters: { search?: string; departamento_id?: string | number };
    departamentos: Departamento[];
};

export default function PuestosIndex({ puestos, filters, departamentos }: Props) {
    const handleDepartamentoChange = (value: string) => {
        const currentParams = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(
            window.location.pathname,
            {
                ...currentParams,
                departamento_id: value === TODOS ? undefined : value,
                page: undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Puestos" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={puestos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar puestos..."
                    createHref="/admin/rh/puestos/create"
                    createLabel="Nuevo Puesto"
                    emptyMessage="No hay puestos registrados"
                    getRowHref={(puesto) => `/admin/rh/puestos/${puesto.id}/edit`}
                >
                    <Select
                        value={filters.departamento_id ? String(filters.departamento_id) : TODOS}
                        onValueChange={handleDepartamentoChange}
                        placeholder="Todos los departamentos"
                    >
                        <SelectItem value={TODOS}>Todos los departamentos</SelectItem>
                        {departamentos.map((d) => (
                            <SelectItem key={d.id} value={String(d.id)}>
                                {d.descripcion}
                            </SelectItem>
                        ))}
                    </Select>
                </DataTable>
            </div>
        </AppLayout>
    );
}
