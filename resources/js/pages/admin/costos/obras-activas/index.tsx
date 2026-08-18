import { Head, router } from '@inertiajs/react';
import { FileTextIcon, PrinterIcon } from 'lucide-react';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPresupuestoEstatus, PaginatedData, PresupuestableTipo, PresupuestoRow } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/obras-activas' },
    { title: 'Obras activas', href: '/admin/costos/obras-activas' },
];

const TIPO_LABELS: Record<PresupuestableTipo, string> = {
    proyecto: 'Proyecto',
    obra: 'Obra',
    partida: 'Partida',
};

const TIPO_COLORS: Record<PresupuestableTipo, string> = {
    proyecto: 'badge-primary',
    obra: 'badge-neutral',
    partida: 'badge-accent',
};

const columns: Column<PresupuestoRow>[] = [
    {
        key: 'tipo',
        label: 'Tipo',
        render: (p) => (
            <span className={`badge badge-sm ${p.es_planta ? 'badge-info' : TIPO_COLORS[p.tipo]}`}>
                {p.es_planta ? 'Planta' : TIPO_LABELS[p.tipo]}
            </span>
        ),
    },
    {
        key: 'nombre_interno',
        label: 'Nombre',
        sortable: true,
        render: (p) => <div className="font-medium">{p.nombre}</div>,
    },
    {
        key: 'op',
        label: 'OP',
        render: (p) => <span className="font-mono text-sm">{p.op ?? '—'}</span>,
    },
    {
        key: 'descripcion',
        label: 'Descripción',
        sortable: true,
        render: (p) => (
            <span className="text-sm text-base-content/70">{p.descripcion ?? '—'}</span>
        ),
    },
    {
        // El PDF autorizado del presupuesto. Se sube en la pantalla de edición
        // y se abre desde aquí, que es donde se consulta la obra.
        key: 'documento',
        label: 'Presupuesto',
        className: 'text-center',
        render: (p) =>
            p.documento ? (
                <a
                    href={`/storage/${p.documento.path}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-ghost btn-xs"
                    title={p.documento.nombre ?? 'Abrir el PDF del presupuesto'}
                    onClick={(e) => e.stopPropagation()}
                >
                    <FileTextIcon className="size-4" />
                    PDF
                </a>
            ) : (
                <span className="text-base-content/30" title="Todavía no se ha cargado el documento">
                    —
                </span>
            ),
    },
];

type Props = {
    presupuestos: PaginatedData<PresupuestoRow>;
    estatus: CostosPresupuestoEstatus;
    conteos: { activo: number; cerrado: number };
    filters: { search?: string; estatus?: string };
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
};

export default function ObrasActivasIndex({ presupuestos, estatus, conteos, filters, sortBy, sortDir }: Props) {
    const cambiarTab = (nuevo: CostosPresupuestoEstatus) => {
        if (nuevo === estatus) return;
        router.get('/admin/costos/obras-activas', { search: filters.search || undefined, estatus: nuevo }, { preserveState: true, preserveScroll: true });
    };

    const tabClass = (activo: boolean) => `tab ${activo ? 'tab-active font-medium' : ''}`;

    const parametros = new URLSearchParams({
        estatus,
        ...(filters.search ? { search: filters.search } : {}),
        ...(sortBy ? { sort_by: sortBy, sort_dir: sortDir ?? 'asc' } : {}),
    });
    const urlImpresion = `/admin/costos/obras-activas/pdf?${parametros.toString()}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras activas" />

            <div className="p-6">
                <h1 className="mb-4 text-2xl font-semibold">Obras activas</h1>

                <div role="tablist" className="tabs tabs-bordered mb-4">
                    <button type="button" role="tab" className={tabClass(estatus === 'activo')} onClick={() => cambiarTab('activo')}>
                        Activas ({conteos.activo})
                    </button>
                    <button type="button" role="tab" className={tabClass(estatus === 'cerrado')} onClick={() => cambiarTab('cerrado')}>
                        Cerradas ({conteos.cerrado})
                    </button>
                </div>

                <DataTable
                    columns={columns}
                    data={presupuestos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por nombre, numero o descripcion..."
                    emptyMessage={estatus === 'activo' ? 'No hay obras activas' : 'No hay obras cerradas'}
                    getRowHref={(p) => `/admin/costos/presupuestos/${p.id}/edit`}
                    sortBy={sortBy}
                    sortDir={sortDir}
                >
                    {/* Imprime lo que se está viendo: se lleva la pestaña, la
                        búsqueda y el orden, pero sin paginar. */}
                    <a href={urlImpresion} target="_blank" rel="noopener noreferrer" className="btn btn-outline btn-sm">
                        <PrinterIcon className="size-4" />
                        Imprimir PDF
                    </a>
                </DataTable>
            </div>
        </AppLayout>
    );
}
