import { DataTable, type Column } from '@/components/data-table';
import { NuevoCatalogoDialog } from '@/components/prod/nuevo-catalogo-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, PaginatedData, ProdCatalogo, Proyecto } from '@/types/models';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Catalogos', href: '/admin/prod/catalogos' },
];

type CatalogoRow = ProdCatalogo & {
    conceptos_count: number;
    conceptos_activos_count: number;
};

const columns: Column<CatalogoRow>[] = [
    {
        key: 'nombre',
        label: 'Catalogo',
        render: (c) => (
            <div>
                <div className="font-medium">{c.nombre}</div>
                <div className="text-base-content/50 text-xs">
                    {c.obra ? `${c.obra.no} — ${c.obra.descripcion}` : 'Sin obra'}
                </div>
            </div>
        ),
    },
    {
        key: 'version',
        label: 'Version',
        className: 'text-center',
        render: (c) => <span className="badge badge-ghost badge-sm font-mono">v{c.version}</span>,
    },
    {
        key: 'vigente',
        label: 'Estado',
        className: 'text-center',
        render: (c) => (
            <span className={`badge badge-sm ${c.vigente ? 'badge-success' : 'badge-ghost'}`}>
                {c.vigente ? 'Vigente' : 'Historico'}
            </span>
        ),
    },
    {
        key: 'conceptos_activos_count',
        label: 'Piezas activas',
        className: 'text-right',
        render: (c) => <span className="font-mono text-sm">{c.conceptos_activos_count}</span>,
    },
    {
        key: 'conceptos_count',
        label: 'Total',
        className: 'text-right',
        render: (c) => <span className="font-mono text-sm">{c.conceptos_count}</span>,
    },
];

type Props = {
    catalogos: PaginatedData<CatalogoRow>;
    obrasDisponibles: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    proyectos: Pick<Proyecto, 'id' | 'no' | 'descripcion'>[];
    filters: { search?: string; historicos?: boolean | string };
};

export default function CatalogosIndex({ catalogos, obrasDisponibles, proyectos, filters }: Props) {
    const verHistoricos = filters.historicos === true || filters.historicos === '1';

    const toggleHistoricos = () => {
        router.get(
            '/admin/prod/catalogos',
            { search: filters.search, historicos: verHistoricos ? undefined : 1 },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catalogos de conceptos" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Catálogos de conceptos</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Un catálogo vigente por obra. Las versiones anteriores quedan congeladas como historia.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={catalogos}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar catalogo u obra..."
                    emptyMessage="No hay catalogos registrados"
                    getRowHref={(c) => `/admin/prod/catalogos/${c.id}`}
                >
                    <label className="label cursor-pointer gap-2">
                        <input
                            type="checkbox"
                            className="checkbox checkbox-sm"
                            checked={verHistoricos}
                            onChange={toggleHistoricos}
                        />
                        <span className="label-text text-sm">Ver históricos</span>
                    </label>
                    <NuevoCatalogoDialog obrasDisponibles={obrasDisponibles} proyectos={proyectos} />
                </DataTable>
            </div>
        </AppLayout>
    );
}
