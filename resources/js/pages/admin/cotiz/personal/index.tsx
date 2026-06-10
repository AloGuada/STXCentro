import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizPersonalCategoria } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/personal' },
    { title: 'Categorías de personal', href: '/admin/cotiz/personal' },
];

type Props = {
    personal: CotizPersonalCategoria[];
};

function guardarFila(row: CotizPersonalCategoria): void {
    router.put(`/admin/cotiz/personal/${row.id}`, {
        codigo: row.codigo,
        nombre: row.nombre,
        sueldo_semanal: row.sueldo_semanal,
        orden: row.orden,
    }, { preserveScroll: true, preserveState: true, only: ['personal'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizPersonalCategoria>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar categoría"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.codigo}"?`)) {
                    router.delete(`/admin/cotiz/personal/${data.id}`, { preserveScroll: true, only: ['personal'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function PersonalIndex({ personal }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizPersonalCategoria>[]>(() => [
        { field: 'codigo', headerName: 'Código', editable: true, minWidth: 150 },
        { field: 'nombre', headerName: 'Nombre', editable: true, minWidth: 240, flex: 3 },
        {
            field: 'sueldo_semanal',
            headerName: 'Sueldo semanal',
            editable: true,
            minWidth: 160,
            cellEditor: 'agNumberCellEditor',
            cellEditorParams: { precision: 2, min: 0 },
            valueFormatter: (p) => (p.value == null ? '' : `$${Number(p.value).toFixed(2)}`),
        },
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
            <Head title="Categorías de personal" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Categorías de personal</h1>
                        <p className="text-sm text-base-content/60">{personal.length} categorías · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar categorías..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/personal/create">
                            Nueva categoría
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizPersonalCategoria>
                    rowData={personal}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
