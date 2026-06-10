import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCentroCosto } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/centros-costo' },
    { title: 'Centros de costo', href: '/admin/cotiz/centros-costo' },
];

type Props = {
    centrosCosto: CotizCentroCosto[];
};

function guardarFila(row: CotizCentroCosto): void {
    router.put(`/admin/cotiz/centros-costo/${row.id}`, {
        cod_coste: row.cod_coste,
        concepto: row.concepto,
    }, { preserveScroll: true, preserveState: true, only: ['centrosCosto'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizCentroCosto>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar centro de costo"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.cod_coste}"?`)) {
                    router.delete(`/admin/cotiz/centros-costo/${data.id}`, { preserveScroll: true, only: ['centrosCosto'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function CentrosCostoIndex({ centrosCosto }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizCentroCosto>[]>(() => [
        { field: 'cod_coste', headerName: 'Código de coste', editable: true, minWidth: 180 },
        { field: 'concepto', headerName: 'Concepto', editable: true, minWidth: 280, flex: 3 },
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
            <Head title="Centros de costo" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Centros de costo</h1>
                        <p className="text-sm text-base-content/60">{centrosCosto.length} centros · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar centros..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/centros-costo/create">
                            Nuevo centro
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizCentroCosto>
                    rowData={centrosCosto}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
