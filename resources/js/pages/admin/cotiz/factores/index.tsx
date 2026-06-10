import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCategoriaTarjeta, CotizFactor, CotizInsumo } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/factores' },
    { title: 'Factores', href: '/admin/cotiz/factores' },
];

type Props = {
    factores: CotizFactor[];
    insumos: CotizInsumo[];
    categoriasTarjeta: CotizCategoriaTarjeta[];
};

function guardarFila(row: CotizFactor): void {
    router.put(`/admin/cotiz/factores/${row.id}`, {
        codigo: row.codigo,
        nombre: row.nombre,
        insumo_id: row.insumo_id,
        formula: row.formula,
        descripcion: row.descripcion,
        categoria_tarjeta_id: row.categoria_tarjeta_id,
    }, { preserveScroll: true, preserveState: true, only: ['factores'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizFactor>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar factor"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.codigo}"?`)) {
                    router.delete(`/admin/cotiz/factores/${data.id}`, { preserveScroll: true, only: ['factores'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function FactoresIndex({ factores, insumos, categoriasTarjeta }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizFactor>[]>(() => {
        const insumoLabels = insumos.map((i) => i.descripcion);
        const categoriaLabels = ['(sin clasificar)', ...categoriasTarjeta.map((c) => c.descripcion)];

        return [
            { field: 'codigo', headerName: 'Código', editable: true, minWidth: 160 },
            { field: 'nombre', headerName: 'Nombre', editable: true, minWidth: 220, flex: 2 },
            {
                headerName: 'Insumo',
                editable: true,
                minWidth: 220,
                flex: 2,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: insumoLabels },
                valueGetter: (p) => p.data?.insumo?.descripcion ?? '',
                valueSetter: (p: ValueSetterParams<CotizFactor>) => {
                    const match = insumos.find((i) => i.descripcion === p.newValue);
                    if (!match) {
                        return false;
                    }
                    p.data.insumo_id = match.id;
                    p.data.insumo = match;
                    return true;
                },
            },
            { field: 'formula', headerName: 'Fórmula', editable: true, minWidth: 200, flex: 2 },
            { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 200, flex: 2 },
            {
                headerName: 'Categoría tarjeta',
                editable: true,
                minWidth: 160,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: categoriaLabels },
                valueGetter: (p) => p.data?.categoria_tarjeta?.descripcion ?? '(sin clasificar)',
                valueSetter: (p: ValueSetterParams<CotizFactor>) => {
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
    }, [insumos, categoriasTarjeta]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Factores" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Factores</h1>
                        <p className="text-sm text-base-content/60">{factores.length} factores · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar factores..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/factores/create">
                            Nuevo factor
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizFactor>
                    rowData={factores}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
