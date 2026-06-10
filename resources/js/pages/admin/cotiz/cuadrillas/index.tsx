import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCentroCosto, CotizCuadrilla, CotizInsumo } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/cuadrillas' },
    { title: 'Cuadrillas', href: '/admin/cotiz/cuadrillas' },
];

type Props = {
    cuadrillas: CotizCuadrilla[];
    centrosCosto: CotizCentroCosto[];
    insumos: CotizInsumo[];
};

function guardarFila(row: CotizCuadrilla): void {
    router.put(`/admin/cotiz/cuadrillas/${row.id}`, {
        codigo: row.codigo,
        nombre: row.nombre,
        centro_costo_id: row.centro_costo_id,
        insumo_id: row.insumo_id,
        rendimiento: row.rendimiento,
        formula_costo: row.formula_costo,
        descripcion: row.descripcion,
    }, { preserveScroll: true, preserveState: true, only: ['cuadrillas'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizCuadrilla>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar cuadrilla"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.codigo}"?`)) {
                    router.delete(`/admin/cotiz/cuadrillas/${data.id}`, { preserveScroll: true, only: ['cuadrillas'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function CuadrillasIndex({ cuadrillas, centrosCosto, insumos }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizCuadrilla>[]>(() => {
        const centroLabels = centrosCosto.map((c) => c.concepto);
        const insumoLabels = ['(sin insumo)', ...insumos.map((i) => i.descripcion)];

        return [
            { field: 'codigo', headerName: 'Código', editable: true, minWidth: 150 },
            { field: 'nombre', headerName: 'Nombre', editable: true, minWidth: 220, flex: 2 },
            {
                headerName: 'Centro de costo',
                editable: true,
                minWidth: 180,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: centroLabels },
                valueGetter: (p) => p.data?.centro_costo?.concepto ?? '',
                valueSetter: (p: ValueSetterParams<CotizCuadrilla>) => {
                    const match = centrosCosto.find((c) => c.concepto === p.newValue);
                    if (!match) {
                        return false;
                    }
                    p.data.centro_costo_id = match.id;
                    p.data.centro_costo = match;
                    return true;
                },
            },
            {
                headerName: 'Insumo',
                editable: true,
                minWidth: 200,
                flex: 2,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: insumoLabels },
                valueGetter: (p) => p.data?.insumo?.descripcion ?? '(sin insumo)',
                valueSetter: (p: ValueSetterParams<CotizCuadrilla>) => {
                    const match = insumos.find((i) => i.descripcion === p.newValue);
                    p.data.insumo_id = match?.id ?? null;
                    p.data.insumo = match;
                    return true;
                },
            },
            {
                field: 'rendimiento',
                headerName: 'Rendimiento',
                editable: true,
                minWidth: 140,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            { field: 'formula_costo', headerName: 'Fórmula de costo', editable: true, minWidth: 200, flex: 2 },
            { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 200, flex: 2 },
            {
                headerName: '',
                editable: false,
                sortable: false,
                filter: false,
                width: 64,
                cellRenderer: DeleteCell,
            },
        ];
    }, [centrosCosto, insumos]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cuadrillas" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Cuadrillas</h1>
                        <p className="text-sm text-base-content/60">{cuadrillas.length} cuadrillas · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar cuadrillas..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/cuadrillas/create">
                            Nueva cuadrilla
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizCuadrilla>
                    rowData={cuadrillas}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
