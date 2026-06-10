import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCategoriaTarjeta } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/categorias-tarjeta' },
    { title: 'Categorías de tarjeta', href: '/admin/cotiz/categorias-tarjeta' },
];

type Props = {
    categoriasTarjeta: CotizCategoriaTarjeta[];
};

function guardarFila(row: CotizCategoriaTarjeta): void {
    router.put(`/admin/cotiz/categorias-tarjeta/${row.id}`, {
        descripcion: row.descripcion,
        orden: row.orden,
    }, { preserveScroll: true, preserveState: true, only: ['categoriasTarjeta'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizCategoriaTarjeta>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar categoría"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.descripcion}"?`)) {
                    router.delete(`/admin/cotiz/categorias-tarjeta/${data.id}`, { preserveScroll: true, only: ['categoriasTarjeta'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function CategoriasTarjetaIndex({ categoriasTarjeta }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizCategoriaTarjeta>[]>(() => [
        { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 280, flex: 3 },
        {
            field: 'orden',
            headerName: 'Orden',
            editable: true,
            minWidth: 120,
            cellEditor: 'agNumberCellEditor',
            cellEditorParams: { precision: 0, min: 0 },
        },
        {
            headerName: '',
            editable: false,
            sortable: false,
            filter: false,
            width: 64,
            cellRenderer: DeleteCell,
        },
    ], []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías de tarjeta" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Categorías de tarjeta</h1>
                        <p className="text-sm text-base-content/60">{categoriasTarjeta.length} categorías · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar categorías..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/categorias-tarjeta/create">
                            Nueva categoría
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizCategoriaTarjeta>
                    rowData={categoriasTarjeta}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
