import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizKilosRealesCategoria, CotizTipoCorte } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/kilos-reales-categorias' },
    { title: 'Categorías de kilos reales', href: '/admin/cotiz/kilos-reales-categorias' },
];

type Props = {
    kilosRealesCategorias: CotizKilosRealesCategoria[];
    tiposCorte: Record<string, string>;
};

function guardarFila(row: CotizKilosRealesCategoria): void {
    router.put(`/admin/cotiz/kilos-reales-categorias/${row.id}`, {
        descripcion: row.descripcion,
        tipo_corte: row.tipo_corte,
        orden: row.orden,
    }, { preserveScroll: true, preserveState: true, only: ['kilosRealesCategorias'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizKilosRealesCategoria>) {
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
                    router.delete(`/admin/cotiz/kilos-reales-categorias/${data.id}`, { preserveScroll: true, only: ['kilosRealesCategorias'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function KilosRealesCategoriasIndex({ kilosRealesCategorias, tiposCorte }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizKilosRealesCategoria>[]>(() => {
        const tipoLabels = Object.values(tiposCorte);

        return [
            { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 280, flex: 3 },
            {
                headerName: 'Tipo de corte',
                editable: true,
                minWidth: 180,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: tipoLabels },
                valueGetter: (p) => (p.data ? tiposCorte[p.data.tipo_corte] ?? p.data.tipo_corte : ''),
                valueSetter: (p: ValueSetterParams<CotizKilosRealesCategoria>) => {
                    const value = Object.keys(tiposCorte).find((k) => tiposCorte[k] === p.newValue);
                    if (!value) {
                        return false;
                    }
                    p.data.tipo_corte = value as CotizTipoCorte;
                    return true;
                },
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
        ];
    }, [tiposCorte]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorías de kilos reales" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Categorías de kilos reales</h1>
                        <p className="text-sm text-base-content/60">{kilosRealesCategorias.length} categorías · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar categorías..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/kilos-reales-categorias/create">
                            Nueva categoría
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizKilosRealesCategoria>
                    rowData={kilosRealesCategorias}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
