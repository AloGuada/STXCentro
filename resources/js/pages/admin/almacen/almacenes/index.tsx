import { Head } from '@inertiajs/react';
import { BotonReporteExistencias } from '@/components/alm/reporte-existencias';
import { DataTable, type Column } from '@/components/data-table';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeTipo } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacen, PaginatedData } from '@/types/models';

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/almacenes' },
    { title: 'Almacenes', href: '/admin/almacen/almacenes' },
];

type AlmacenFila = AlmAlmacen & {
    /** Sumado por el servidor desde el kardex. */
    valor_inventario: number | null;
    articulos_con_saldo: number;
};

const columns: Column<AlmacenFila>[] = [
    {
        key: 'clave',
        label: 'Clave',
        render: (a) => <span className="badge badge-ghost font-mono font-medium">{a.clave}</span>,
    },
    { key: 'nombre', label: 'Nombre', render: (a) => <span className="font-medium">{a.nombre}</span> },
    {
        key: 'obra_id',
        label: 'Obra',
        render: (a) =>
            a.obra ? (
                <span>
                    <span className="font-mono">{a.obra.no}</span>
                    <span className="text-base-content/60"> · {a.obra.descripcion}</span>
                </span>
            ) : (
                <span className="badge badge-sm badge-info badge-outline">Central</span>
            ),
    },
    {
        key: 'tipo',
        label: 'Tipo',
        render: (a) => <span className="badge badge-sm badge-ghost">{etiquetaDeTipo(a.tipo)}</span>,
    },
    {
        key: 'responsable_id',
        label: 'Responsable',
        render: (a) =>
            a.responsable ? a.responsable.name : <span className="text-base-content/40">Sin asignar</span>,
    },
    {
        key: 'activo',
        label: 'Estado',
        className: 'text-center',
        render: (a) => (
            <span className={`badge badge-sm ${a.activo ? 'badge-success' : 'badge-ghost'}`}>
                {a.activo ? 'Activo' : 'Inactivo'}
            </span>
        ),
    },
    {
        // Lo que vale lo que está a cargo de esa bodega, a costo promedio. Lo
        // suma el servidor desde el kardex: contarlo aquí obligaría a traer
        // todas las existencias de todos los almacenes al navegador.
        key: 'valor_inventario',
        label: 'Valor',
        className: 'text-right',
        render: (a) => {
            if (!a.articulos_con_saldo) {
                return (
                    <span className="text-base-content/40" title="Todavía no hay existencias registradas">
                        —
                    </span>
                );
            }

            return (
                <span title={`${a.articulos_con_saldo} artículo(s) con saldo`}>
                    <span className="font-mono">{moneda(a.valor_inventario ?? 0)}</span>
                    <span className="text-base-content/50 block text-xs">
                        {a.articulos_con_saldo} artículo(s)
                    </span>
                </span>
            );
        },
    },
    {
        key: 'reporte',
        label: 'Existencias',
        className: 'text-center',
        render: (a) => <BotonReporteExistencias almacen={a} compacto />,
    },
];

type Props = {
    almacenes: PaginatedData<AlmacenFila>;
    filters: { search?: string };
};

export default function AlmacenesIndex({ almacenes, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Almacenes" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Almacenes</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Dónde vive el material. Un almacén con obra es de esa obra; uno sin obra es central y surte a
                        todas.
                    </p>
                </div>

                <DataTable
                    columns={columns}
                    data={almacenes}
                    searchable
                    searchValue={filters.search}
                    searchPlaceholder="Buscar por clave, nombre u obra..."
                    createHref="/admin/almacen/almacenes/create"
                    createLabel="Nuevo almacén"
                    emptyMessage="No hay almacenes registrados"
                    getRowHref={(a) => `/admin/almacen/almacenes/${a.id}/edit`}
                >
                    {/* El consolidado de la empresa: todas las existencias
                        valuadas a costo promedio, sin importar la bodega. */}
                    <BotonReporteExistencias etiqueta="Reporte de existencias" />
                </DataTable>
            </div>
        </AppLayout>
    );
}
