import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCategoriaTarjeta, CotizCentroCosto, CotizInsumo, CotizUnidad } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/insumos' },
    { title: 'Insumos', href: '/admin/cotiz/insumos' },
];

type Props = {
    insumos: CotizInsumo[];
    unidades: CotizUnidad[];
    centrosCosto: CotizCentroCosto[];
    categoriasTarjeta: CotizCategoriaTarjeta[];
};

function guardarFila(row: CotizInsumo): void {
    router.put(`/admin/cotiz/insumos/${row.id}`, {
        descripcion: row.descripcion,
        codigo_stumis: row.codigo_stumis,
        unidad_id: row.unidad_id,
        precio_unitario: row.precio_unitario,
        peso_lineal: row.peso_lineal,
        peso_default: row.peso_default,
        centro_costo_id: row.centro_costo_id,
        categoria_tarjeta_id: row.categoria_tarjeta_id,
    }, { preserveScroll: true, preserveState: true, only: ['insumos'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizInsumo>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar insumo"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.descripcion}"?`)) {
                    router.delete(`/admin/cotiz/insumos/${data.id}`, { preserveScroll: true, only: ['insumos'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function InsumosIndex({ insumos, unidades, centrosCosto, categoriasTarjeta }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizInsumo>[]>(() => {
        const unidadLabels = unidades.map((u) => u.descripcion);
        const centroLabels = centrosCosto.map((c) => c.concepto);
        const categoriaLabels = ['(sin clasificar)', ...categoriasTarjeta.map((c) => c.descripcion)];

        return [
            { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 280, flex: 2 },
            { field: 'codigo_stumis', headerName: 'Código STUMIS', editable: true, minWidth: 140 },
            {
                headerName: 'Unidad',
                editable: true,
                minWidth: 110,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: unidadLabels },
                valueGetter: (p) => p.data?.unidad?.descripcion ?? '',
                valueSetter: (p: ValueSetterParams<CotizInsumo>) => {
                    const match = unidades.find((u) => u.descripcion === p.newValue);
                    if (!match) {
                        return false;
                    }
                    p.data.unidad_id = match.id;
                    p.data.unidad = match;
                    return true;
                },
            },
            {
                field: 'precio_unitario',
                headerName: 'P. Unitario',
                editable: true,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                valueFormatter: (p) => (p.value == null ? '' : `$${Number(p.value).toFixed(2)}`),
            },
            {
                field: 'peso_lineal',
                headerName: 'Peso ML/M²',
                editable: true,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'peso_default',
                headerName: 'Peso default',
                editable: true,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                headerName: 'Centro de costo',
                editable: true,
                minWidth: 160,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: centroLabels },
                valueGetter: (p) => p.data?.centro_costo?.concepto ?? '',
                valueSetter: (p: ValueSetterParams<CotizInsumo>) => {
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
                headerName: 'Categoría tarjeta',
                editable: true,
                minWidth: 160,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: categoriaLabels },
                valueGetter: (p) => p.data?.categoria_tarjeta?.descripcion ?? '(sin clasificar)',
                valueSetter: (p: ValueSetterParams<CotizInsumo>) => {
                    const match = categoriasTarjeta.find((c) => c.descripcion === p.newValue);
                    p.data.categoria_tarjeta_id = match?.id ?? null;
                    p.data.categoria_tarjeta = match;
                    return true;
                },
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
    }, [unidades, centrosCosto, categoriasTarjeta]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Insumos" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Insumos</h1>
                        <p className="text-sm text-base-content/60">{insumos.length} insumos · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar insumos..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/insumos/create">
                            Nuevo insumo
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizInsumo>
                    rowData={insumos}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
