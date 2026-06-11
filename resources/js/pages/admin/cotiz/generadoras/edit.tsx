import { AutocompleteCellEditor } from '@/components/cotiz/autocomplete-cell-editor';
import { EditableGrid } from '@/components/cotiz/editable-grid';
import { Button } from '@/components/ui/button';
import { useCotizEditLock } from '@/hooks/use-cotiz-edit-lock';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CotizGeneradora,
    CotizGeneradoraRegistro,
    CotizInsumo,
    CotizMerma,
} from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type {
    ColDef,
    ICellRendererParams,
    ValueSetterParams,
} from 'ag-grid-community';
import { LockIcon, Loader2Icon, Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

type RegistroRow = CotizGeneradoraRegistro;

type Props = {
    generadora: CotizGeneradora;
    registros: RegistroRow[];
    insumos: Pick<CotizInsumo, 'id' | 'descripcion' | 'peso_lineal'>[];
    mermas: Pick<CotizMerma, 'id' | 'descripcion' | 'formula'>[];
    lock: {
        is_locked: boolean;
        locked_by: { id: string; name: string } | null;
        locked_at: string | null;
    };
};

const NINGUNO = '(ninguno)';

function formatDesde(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const diff = (Date.now() - new Date(iso).getTime()) / 60000;
    if (diff < 1) {
        return 'hace un momento';
    }
    if (diff < 60) {
        return `hace ${Math.round(diff)} min`;
    }
    return `hace ${Math.round(diff / 60)} h`;
}

function fmtKg(value: number | null): string {
    return value == null ? '' : Number(value).toFixed(2);
}

function guardarFila(row: RegistroRow): void {
    router.put(
        `/admin/cotiz/registros/${row.id}`,
        {
            material_origen_id: row.material_origen_id,
            material: row.material,
            marca: row.marca,
            ancho: row.ancho,
            largo: row.largo,
            cantidad: row.cantidad,
            cant_pzas: row.cant_pzas,
            merma_id: row.merma_id,
            validado: row.validado,
        },
        { preserveScroll: true, preserveState: true, only: ['registros'] },
    );
}

export default function GeneradorasEdit({
    generadora,
    registros,
    insumos,
    mermas,
    lock,
}: Props) {
    const lockState = useCotizEditLock('generadora', generadora.id);
    const readOnly = lockState.status !== 'owned';

    const obra = generadora.obra;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        ...(obra
            ? [
                  {
                      title: obra.nombre,
                      href: `/admin/cotiz/obras/${obra.id}/generadoras`,
                  },
              ]
            : []),
        {
            title: generadora.titulo,
            href: `/admin/cotiz/generadoras/${generadora.id}/edit`,
        },
    ];

    const columnDefs = useMemo<ColDef<RegistroRow>[]>(() => {
        const insumoLabels = [NINGUNO, ...insumos.map((i) => i.descripcion)];
        const mermaLabels = mermas.map((m) => m.descripcion);

        return [
            {
                headerName: 'Material (insumo)',
                editable: !readOnly,
                minWidth: 200,
                flex: 2,
                cellEditor: AutocompleteCellEditor,
                cellEditorParams: { opciones: insumoLabels },
                valueGetter: (p) =>
                    p.data?.material_origen?.descripcion ?? NINGUNO,
                valueSetter: (p: ValueSetterParams<RegistroRow>) => {
                    if (p.newValue === NINGUNO) {
                        p.data.material_origen_id = null;
                        p.data.material_origen = null;
                        return true;
                    }
                    const match = insumos.find(
                        (i) => i.descripcion === p.newValue,
                    );
                    if (!match) {
                        return false;
                    }
                    p.data.material_origen_id = match.id;
                    p.data.material_origen = match as CotizInsumo;
                    return true;
                },
            },
            {
                field: 'material',
                headerName: 'Material (texto)',
                editable: !readOnly,
                minWidth: 160,
            },
            {
                field: 'marca',
                headerName: 'Marca',
                editable: !readOnly,
                minWidth: 120,
            },
            {
                field: 'ancho',
                headerName: 'Ancho',
                editable: !readOnly,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'largo',
                headerName: 'Largo',
                editable: !readOnly,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                editable: !readOnly,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'cant_pzas',
                headerName: 'Cant. piezas',
                editable: !readOnly,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                headerName: 'Merma',
                editable: !readOnly,
                minWidth: 150,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: mermaLabels },
                valueGetter: (p) => p.data?.merma?.descripcion ?? '',
                valueSetter: (p: ValueSetterParams<RegistroRow>) => {
                    const match = mermas.find(
                        (m) => m.descripcion === p.newValue,
                    );
                    if (!match) {
                        return false;
                    }
                    p.data.merma_id = match.id;
                    p.data.merma = match as CotizMerma;
                    return true;
                },
            },
            {
                field: 'validado',
                headerName: 'Validado',
                editable: !readOnly,
                minWidth: 110,
                cellEditor: 'agCheckboxCellEditor',
                cellRenderer: 'agCheckboxCellRenderer',
            },
            {
                field: 't_ml_m2',
                headerName: 'T ML/M²',
                editable: false,
                minWidth: 120,
                valueFormatter: (p) => fmtKg(p.value),
            },
            {
                field: 'kilos_reales',
                headerName: 'Kg reales',
                editable: false,
                minWidth: 120,
                valueFormatter: (p) => fmtKg(p.value),
            },
            {
                field: 'kilos_con_merma',
                headerName: 'Kg c/merma',
                editable: false,
                minWidth: 120,
                valueFormatter: (p) => fmtKg(p.value),
            },
            {
                headerName: '',
                editable: false,
                sortable: false,
                filter: false,
                width: 64,
                cellRenderer: DeleteCell(readOnly),
            },
        ];
    }, [insumos, mermas, readOnly]);

    const totalReales = registros.reduce(
        (sum, r) => sum + (r.kilos_reales ?? 0),
        0,
    );
    const totalConMerma = registros.reduce(
        (sum, r) => sum + (r.kilos_con_merma ?? 0),
        0,
    );

    const handleNuevo = () => {
        const mermaId = mermas[0]?.id;
        if (!mermaId) {
            alert(
                'No hay mermas configuradas. Crea una merma antes de agregar registros.',
            );
            return;
        }
        router.post(
            `/admin/cotiz/generadoras/${generadora.id}/registros`,
            { merma_id: mermaId, validado: false },
            { preserveScroll: true, preserveState: true, only: ['registros'] },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${generadora.titulo}`} />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {generadora.titulo}
                        </h1>
                        <p className="text-sm text-base-content/60">
                            {obra ? `Obra: ${obra.nombre} · ` : ''}
                            {registros.length} registros · edición en línea
                            (clic en una celda)
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="primary"
                        disabled={readOnly}
                        onClick={handleNuevo}
                    >
                        Nuevo registro
                    </Button>
                </div>

                <LockBanner state={lockState} fallback={lock} />

                <EditableGrid<RegistroRow>
                    rowData={registros}
                    columnDefs={columnDefs}
                    getRowId={(row) => String(row.id)}
                    onCellEdited={readOnly ? undefined : guardarFila}
                />

                <div className="flex justify-end gap-8 rounded-box border border-base-300 px-4 py-3 text-sm">
                    <div>
                        <span className="text-base-content/60">
                            Σ Kg reales:{' '}
                        </span>
                        <span className="font-semibold">
                            {totalReales.toFixed(2)}
                        </span>
                    </div>
                    <div>
                        <span className="text-base-content/60">
                            Σ Kg c/merma:{' '}
                        </span>
                        <span className="font-semibold">
                            {totalConMerma.toFixed(2)}
                        </span>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function DeleteCell(readOnly: boolean) {
    return function Cell({ data }: ICellRendererParams<RegistroRow>) {
        if (!data) {
            return null;
        }
        return (
            <button
                type="button"
                className="btn text-error btn-ghost btn-xs"
                title="Eliminar registro"
                disabled={readOnly}
                onClick={() => {
                    if (readOnly) {
                        return;
                    }
                    if (confirm('¿Eliminar este registro?')) {
                        router.delete(`/admin/cotiz/registros/${data.id}`, {
                            preserveScroll: true,
                            preserveState: true,
                            only: ['registros'],
                        });
                    }
                }}
            >
                <Trash2Icon className="size-4" />
            </button>
        );
    };
}

type LockBannerProps = {
    state: ReturnType<typeof useCotizEditLock>;
    fallback: Props['lock'];
};

function LockBanner({ state, fallback }: LockBannerProps) {
    if (state.status === 'taking') {
        return (
            <div className="alert">
                <Loader2Icon className="size-5 animate-spin" />
                <span>Iniciando sesión de edición…</span>
            </div>
        );
    }

    if (state.status === 'owned') {
        return null;
    }

    if (state.status === 'error') {
        return (
            <div className="alert alert-error">
                <LockIcon className="size-5" />
                <span>
                    {state.message} Puede ver los datos pero no guardar cambios.
                </span>
            </div>
        );
    }

    const nombre =
        state.lockedBy?.name ?? fallback.locked_by?.name ?? 'Otro usuario';
    const desde = formatDesde(state.lockedAt ?? fallback.locked_at);

    return (
        <div className="alert alert-warning">
            <LockIcon className="size-5" />
            <div>
                <p className="font-medium">La está editando {nombre}.</p>
                <p className="text-xs opacity-80">
                    {desde ? `Inició ${desde}. ` : ''}
                    Puede ver los registros pero no guardar cambios hasta que
                    termine o su sesión expire.
                </p>
            </div>
        </div>
    );
}
