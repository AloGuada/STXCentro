import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCentroCosto, CotizFaseMontaje } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams, ValueSetterParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/fases-montaje' },
    { title: 'Fases de montaje', href: '/admin/cotiz/fases-montaje' },
];

type Props = {
    fasesMontaje: CotizFaseMontaje[];
    centrosCosto: CotizCentroCosto[];
};

function guardarFila(row: CotizFaseMontaje): void {
    router.put(`/admin/cotiz/fases-montaje/${row.id}`, {
        codigo: row.codigo,
        nombre: row.nombre,
        unidad: row.unidad,
        centro_costo_id: row.centro_costo_id,
        orden: row.orden,
    }, { preserveScroll: true, preserveState: true, only: ['fasesMontaje'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizFaseMontaje>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar fase"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.codigo}"?`)) {
                    router.delete(`/admin/cotiz/fases-montaje/${data.id}`, { preserveScroll: true, only: ['fasesMontaje'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function FasesMontajeIndex({ fasesMontaje, centrosCosto }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizFaseMontaje>[]>(() => {
        const centroLabels = ['(sin centro)', ...centrosCosto.map((c) => c.concepto)];

        return [
            { field: 'codigo', headerName: 'Código', editable: true, minWidth: 150 },
            { field: 'nombre', headerName: 'Nombre', editable: true, minWidth: 240, flex: 3 },
            { field: 'unidad', headerName: 'Unidad', editable: true, minWidth: 110 },
            {
                headerName: 'Centro de costo',
                editable: true,
                minWidth: 180,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: centroLabels },
                valueGetter: (p) => p.data?.centro_costo?.concepto ?? '(sin centro)',
                valueSetter: (p: ValueSetterParams<CotizFaseMontaje>) => {
                    const match = centrosCosto.find((c) => c.concepto === p.newValue);
                    p.data.centro_costo_id = match?.id ?? null;
                    p.data.centro_costo = match;
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
    }, [centrosCosto]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fases de montaje" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Fases de montaje</h1>
                        <p className="text-sm text-base-content/60">{fasesMontaje.length} fases · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar fases..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/fases-montaje/create">
                            Nueva fase
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizFaseMontaje>
                    rowData={fasesMontaje}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
