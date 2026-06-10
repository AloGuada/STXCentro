import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizFleteViaticoCatalogo, CotizGrupoFlete } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/fletes-viaticos' },
    { title: 'Fletes y viáticos', href: '/admin/cotiz/fletes-viaticos' },
];

type Props = {
    fletesViaticos: CotizFleteViaticoCatalogo[];
    grupos: Record<string, string>;
};

function guardarFila(row: CotizFleteViaticoCatalogo): void {
    router.put(`/admin/cotiz/fletes-viaticos/${row.id}`, {
        grupo: row.grupo,
        orden: row.orden,
        concepto: row.concepto,
        unidad: row.unidad,
        p_unit_default: row.p_unit_default,
        notas: row.notas,
        clave: row.clave,
        formula_cantidad: row.formula_cantidad,
        formula_p_unit: row.formula_p_unit,
    }, { preserveScroll: true, preserveState: true, only: ['fletesViaticos'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizFleteViaticoCatalogo>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar concepto"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.concepto}"?`)) {
                    router.delete(`/admin/cotiz/fletes-viaticos/${data.id}`, { preserveScroll: true, only: ['fletesViaticos'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function FletesViaticosIndex({ fletesViaticos, grupos }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizFleteViaticoCatalogo>[]>(() => {
        const grupoLabels = Object.values(grupos);

        return [
            {
                headerName: 'Grupo',
                editable: true,
                minWidth: 180,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: grupoLabels },
                valueGetter: (p) => (p.data ? grupos[p.data.grupo] ?? p.data.grupo : ''),
                valueSetter: (p: ValueSetterParams<CotizFleteViaticoCatalogo>) => {
                    const value = Object.keys(grupos).find((k) => grupos[k] === p.newValue);
                    if (!value) {
                        return false;
                    }
                    p.data.grupo = value as CotizGrupoFlete;
                    return true;
                },
            },
            { field: 'concepto', headerName: 'Concepto', editable: true, minWidth: 220, flex: 2 },
            { field: 'clave', headerName: 'Clave', editable: true, minWidth: 130 },
            { field: 'unidad', headerName: 'Unidad', editable: true, minWidth: 110 },
            {
                field: 'p_unit_default',
                headerName: 'P. Unit. default',
                editable: true,
                minWidth: 150,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                valueFormatter: (p) => (p.value == null ? '' : `$${Number(p.value).toFixed(2)}`),
            },
            { field: 'formula_cantidad', headerName: 'Fórmula cantidad', editable: true, minWidth: 180, flex: 2 },
            { field: 'formula_p_unit', headerName: 'Fórmula P. Unit.', editable: true, minWidth: 180, flex: 2 },
            { field: 'notas', headerName: 'Notas', editable: true, minWidth: 180, flex: 2 },
            {
                field: 'orden',
                headerName: 'Orden',
                editable: true,
                minWidth: 110,
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
    }, [grupos]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fletes y viáticos" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Fletes y viáticos</h1>
                        <p className="text-sm text-base-content/60">{fletesViaticos.length} conceptos · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar conceptos..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/fletes-viaticos/create">
                            Nuevo concepto
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizFleteViaticoCatalogo>
                    rowData={fletesViaticos}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
