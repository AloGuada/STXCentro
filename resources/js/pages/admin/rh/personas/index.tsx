import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData, RhPersona } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Personas', href: '/admin/rh/personas' },
];

const calcularEdad = (fechaNacimiento: string | null): string => {
    if (!fechaNacimiento) return '-';
    const hoy = new Date();
    const nacimiento = new Date(fechaNacimiento);
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const m = hoy.getMonth() - nacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
    return `${edad} años`;
};

const columns: Column<RhPersona>[] = [
    { key: 'nombre', label: 'Nombre', sortable: true },
    { key: 'apellido', label: 'Apellido', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'telefono', label: 'Telefono', sortable: true },
    {
        key: 'localidad',
        label: 'Localidad',
        render: (persona) => persona.datos_extra?.localidad ?? '-',
    },
    {
        key: 'edad',
        label: 'Edad',
        sortable: true,
        sortKey: 'fecha_nacimiento',
        render: (persona) => calcularEdad(persona.fecha_nacimiento),
    },
    {
        key: 'estado',
        label: 'Estado',
        render: (persona) => {
            const activo = persona.periodos_laborales?.some((p) => p.estado === 'activo');
            return (
                <span className={`badge badge-sm ${activo ? 'badge-success' : 'badge-ghost'}`}>
                    {activo ? 'Activo' : 'Inactivo'}
                </span>
            );
        },
    },
    {
        key: 'created_at',
        label: 'Fecha Registro',
        sortable: true,
        render: (persona) => new Date(persona.created_at).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }),
    },
];

type Props = {
    personas: PaginatedData<RhPersona>;
    filters: { search?: string; sort_by?: string; sort_dir?: 'asc' | 'desc'; estado?: string };
};

export default function PersonasIndex({ personas, filters }: Props) {
    const handleEstadoChange = (value: string) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, estado: value || undefined, page: undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Personas" />

            <div className="p-6">
                <DataTable
                    columns={columns}
                    data={personas}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar personas..."
                    createHref="/admin/rh/personas/create"
                    createLabel="Nueva Persona"
                    emptyMessage="No hay personas registradas"
                    getRowHref={(persona) => `/admin/rh/personas/${persona.id}`}
                    sortBy={filters.sort_by}
                    sortDir={filters.sort_dir}
                >
                    <select
                        className="select select-bordered select-sm"
                        value={filters.estado ?? ''}
                        onChange={(e) => handleEstadoChange(e.target.value)}
                    >
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>
                </DataTable>
            </div>
        </AppLayout>
    );
}
