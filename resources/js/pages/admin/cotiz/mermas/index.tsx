import { EditableGrid } from '@/components/cotiz/editable-grid';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizMerma } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/mermas' },
    { title: 'Mermas', href: '/admin/cotiz/mermas' },
];

type Props = {
    mermas: CotizMerma[];
};

function guardarFila(row: CotizMerma): void {
    router.put(`/admin/cotiz/mermas/${row.id}`, {
        descripcion: row.descripcion,
        formula: row.formula,
    }, { preserveScroll: true, preserveState: true, only: ['mermas'] });
}

function DeleteCell({ data }: ICellRendererParams<CotizMerma>) {
    if (!data) {
        return null;
    }

    return (
        <button
            type="button"
            className="btn btn-ghost btn-xs text-error"
            title="Eliminar merma"
            onClick={() => {
                if (confirm(`¿Eliminar "${data.descripcion}"?`)) {
                    router.delete(`/admin/cotiz/mermas/${data.id}`, { preserveScroll: true, only: ['mermas'] });
                }
            }}
        >
            <Trash2Icon className="size-4" />
        </button>
    );
}

export default function MermasIndex({ mermas }: Props) {
    const [search, setSearch] = useState('');

    const columnDefs = useMemo<ColDef<CotizMerma>[]>(() => [
        { field: 'descripcion', headerName: 'Descripción', editable: true, minWidth: 280, flex: 2 },
        { field: 'formula', headerName: 'Fórmula', editable: true, minWidth: 280, flex: 3 },
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
            <Head title="Mermas" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Mermas</h1>
                        <p className="text-sm text-base-content/60">{mermas.length} mermas · edición en línea (clic en una celda)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar mermas..."
                            className="w-64"
                        />
                        <ButtonLink variant="primary" href="/admin/cotiz/mermas/create">
                            Nueva merma
                        </ButtonLink>
                    </div>
                </div>

                <EditableGrid<CotizMerma>
                    rowData={mermas}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={guardarFila}
                    quickFilterText={search}
                />
            </div>
        </AppLayout>
    );
}
