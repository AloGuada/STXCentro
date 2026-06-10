import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizResumenBloque, CotizResumenFila, CotizResumenTipoFormula } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/resumen-filas' },
    { title: 'Filas de resumen', href: '/admin/cotiz/resumen-filas' },
];

type Props = {
    resumenFilas: CotizResumenFila[];
    bloques: Record<string, string>;
    tiposFormula: Record<string, string>;
};

function guardarFila(row: CotizResumenFila): void {
    router.put(`/admin/cotiz/resumen-filas/${row.id}`, {
        descripcion: row.descripcion,
        bloque: row.bloque,
        tipo_formula: row.tipo_formula,
        coef_default: row.coef_default,
        referencia_extra: row.referencia_extra,
        orden: row.orden,
        bloqueada: row.bloqueada,
    }, { preserveScroll: true, preserveState: true, only: ['resumenFilas'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizResumenFila>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar fila"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.descripcion}"?`)) {
                    router.delete(`/admin/cotiz/resumen-filas/${data.id}`, { preserveScroll: true, only: ['resumenFilas'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function ResumenFilasIndex({ resumenFilas, bloques, tiposFormula }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizResumenFila>[]>(() => {
        const bloqueLabels = Object.values(bloques);
        const tipoLabels = Object.values(tiposFormula);

        return [
            { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 240, flex: 3 },
            {
                headerName: 'Bloque',
                editable: true,
                minWidth: 200,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: bloqueLabels },
                valueGetter: (p) => (p.data ? bloques[p.data.bloque] ?? p.data.bloque : ''),
                valueSetter: (p: ValueSetterParams<CotizResumenFila>) => {
                    const value = Object.keys(bloques).find((k) => bloques[k] === p.newValue);
                    if (!value) {
                        return false;
                    }
                    p.data.bloque = value as CotizResumenBloque;
                    return true;
                },
            },
            {
                headerName: 'Tipo de fórmula',
                editable: true,
                minWidth: 220,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: tipoLabels },
                valueGetter: (p) => (p.data ? tiposFormula[p.data.tipo_formula] ?? p.data.tipo_formula : ''),
                valueSetter: (p: ValueSetterParams<CotizResumenFila>) => {
                    const value = Object.keys(tiposFormula).find((k) => tiposFormula[k] === p.newValue);
                    if (!value) {
                        return false;
                    }
                    p.data.tipo_formula = value as CotizResumenTipoFormula;
                    return true;
                },
            },
            {
                field: 'coef_default',
                headerName: 'Coef. default',
                editable: true,
                minWidth: 140,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6 },
            },
            { field: 'referencia_extra', headerName: 'Referencia extra', editable: true, minWidth: 180, flex: 2 },
            {
                field: 'orden',
                headerName: 'Orden',
                editable: true,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 0, min: 0 },
            },
            {
                field: 'bloqueada',
                headerName: 'Bloqueada',
                editable: true,
                minWidth: 120,
                cellRenderer: 'agCheckboxCellRenderer',
                cellEditor: 'agCheckboxCellEditor',
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
    }, [bloques, tiposFormula]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Filas de resumen" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Filas de resumen</h1>
                        <p className="text-sm text-base-content/60">{resumenFilas.length} filas · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar filas..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/resumen-filas/create">
                            Nueva fila
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizResumenFila>
                    rowData={resumenFilas}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
