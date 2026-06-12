import { Head, router } from '@inertiajs/react';
import type {
    ColDef,
    EditableCallbackParams,
    ICellRendererParams,
    ValueSetterParams,
} from 'ag-grid-community';
import { LockIcon, Loader2Icon, Trash2Icon } from 'lucide-react';
import { useMemo } from 'react';
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

/**
 * El insumo de origen viene con los pesos EFECTIVOS (override por obra > global) más los
 * valores globales y banderas de override (para estilo). Editar peso_lineal/peso_default
 * en la grilla escribe un override por obra (no el catálogo global).
 */
type MaterialOrigen = {
    id: number;
    descripcion: string;
    peso_lineal: number | null;
    peso_default: number | null;
    peso_lineal_global?: number | null;
    peso_default_global?: number | null;
    peso_lineal_overridden?: boolean;
    peso_default_overridden?: boolean;
};

type RegistroRow = Omit<CotizGeneradoraRegistro, 'material_origen'> & {
    material_origen?: MaterialOrigen | null;
    // Efectivos calculados en el backend (null cuando no aplican, p. ej. registro sin insumo):
    // peso % por agregados del mismo insumo, y T. kilos = kg reales × (1 + peso %).
    peso_porcentual_ef: number | null;
    kilos_totales_ef: number | null;
};

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

function fmtKg(value: number | string | null): string {
    return value == null || value === '' ? '' : Number(value).toFixed(2);
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
            peso_porcentual: row.peso_porcentual,
            kilos_totales: row.kilos_totales,
            merma_id: row.merma_id,
            validado: row.validado,
        },
        { preserveScroll: true, preserveState: true, only: ['registros'] },
    );
}

/**
 * Peso ml/m² y Peso pres. NO tocan el registro ni el catálogo global: guardan un override por
 * obra (obra_insumo_override). El backend limpia el override si el valor iguala al global.
 */
function savePesoOverride(
    row: RegistroRow,
    field: 'peso_lineal' | 'peso_default',
): void {
    if (row.material_origen_id == null) {
        return;
    }
    router.put(
        `/admin/cotiz/registros/${row.id}/insumo-override`,
        { field, value: row.material_origen?.[field] ?? null },
        { preserveScroll: true, preserveState: true, only: ['registros'] },
    );
}

function onRegistroEdited(row: RegistroRow, field?: string): void {
    if (row.id < 0) {
        return; // fila de totales (footer), no se guarda
    }
    if (field === 'peso_lineal' || field === 'peso_default') {
        savePesoOverride(row, field);
    } else {
        guardarFila(row);
    }
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
        // No editable en la fila de totales (footer pinned).
        const editableCell = (p: EditableCallbackParams<RegistroRow>) =>
            !readOnly && !p.node?.rowPinned;

        return [
            {
                headerName: 'Material (insumo)',
                editable: editableCell,
                minWidth: 200,
                flex: 2,
                cellEditor: AutocompleteCellEditor,
                cellEditorParams: { opciones: insumoLabels },
                cellClass: (p) => (p.node?.rowPinned ? 'font-bold' : ''),
                valueGetter: (p) =>
                    p.node?.rowPinned
                        ? 'TOTALES'
                        : (p.data?.material_origen?.descripcion ?? NINGUNO),
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
                    // Optimista; el servidor recarga los pesos efectivos al guardar.
                    p.data.material_origen = {
                        id: match.id,
                        descripcion: match.descripcion,
                        peso_lineal:
                            match.peso_lineal == null
                                ? null
                                : Number(match.peso_lineal),
                        peso_default: null,
                    };
                    return true;
                },
            },
            {
                field: 'material',
                headerName: 'Material (texto)',
                editable: editableCell,
                minWidth: 160,
            },
            {
                field: 'marca',
                headerName: 'Marca',
                editable: editableCell,
                minWidth: 120,
            },
            {
                field: 'validado',
                headerName: 'Validado',
                editable: editableCell,
                minWidth: 110,
                cellEditor: 'agCheckboxCellEditor',
                cellRenderer: 'agCheckboxCellRenderer',
            },
            {
                field: 'ancho',
                headerName: 'Ancho',
                editable: editableCell,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'largo',
                headerName: 'Largo',
                editable: editableCell,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                editable: editableCell,
                minWidth: 110,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 'cant_pzas',
                headerName: 'Cant. piezas',
                editable: editableCell,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
            },
            {
                field: 't_ml_m2',
                headerName: 'T ML/M²',
                editable: false,
                minWidth: 120,
                valueFormatter: (p) => fmtKg(p.value),
            },
            {
                colId: 'peso_lineal',
                headerName: 'Peso ml/m²',
                editable: (p) =>
                    !readOnly && p.data?.material_origen_id != null,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
                headerTooltip:
                    'Peso por ml/m² efectivo. Editar guarda un override SOLO para esta obra (visible en el catálogo de obra); vaciarlo o igualarlo al global lo quita.',
                valueGetter: (p) =>
                    p.data?.material_origen?.peso_lineal ?? null,
                valueSetter: (p: ValueSetterParams<RegistroRow>) => {
                    if (!p.data.material_origen) {
                        return false;
                    }
                    p.data.material_origen.peso_lineal =
                        p.newValue === '' || p.newValue == null
                            ? null
                            : Number(p.newValue);
                    return true;
                },
                cellClass: (p) =>
                    p.data?.material_origen?.peso_lineal_overridden
                        ? 'font-semibold text-info'
                        : 'italic opacity-70',
                valueFormatter: (p) => fmtKg(p.value),
            },
            {
                colId: 'peso_default',
                headerName: 'Peso pres.',
                editable: (p) =>
                    !readOnly && p.data?.material_origen_id != null,
                minWidth: 120,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
                headerTooltip:
                    'Peso por presentación (placa/barra/polín) efectivo. Editar guarda un override SOLO para esta obra; vaciarlo o igualarlo al global lo quita.',
                valueGetter: (p) =>
                    p.data?.material_origen?.peso_default ?? null,
                valueSetter: (p: ValueSetterParams<RegistroRow>) => {
                    if (!p.data.material_origen) {
                        return false;
                    }
                    p.data.material_origen.peso_default =
                        p.newValue === '' || p.newValue == null
                            ? null
                            : Number(p.newValue);
                    return true;
                },
                cellClass: (p) =>
                    p.data?.material_origen?.peso_default_overridden
                        ? 'font-semibold text-info'
                        : 'italic opacity-70',
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
                headerName: 'Merma',
                editable: editableCell,
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
                colId: 'peso_porcentual',
                headerName: 'Peso %',
                editable: false,
                minWidth: 120,
                headerTooltip:
                    'Peso porcentual calculado por agregados del mismo insumo: (Σ kg c/merma − Σ kg reales) / Σ kg reales. En el footer: Δ = T. kilos − kg reales (kg).',
                valueGetter: (p) => p.data?.peso_porcentual_ef ?? null,
                cellClass: (p) =>
                    p.node?.rowPinned
                        ? 'font-bold'
                        : p.data?.peso_porcentual == null
                          ? 'italic opacity-70'
                          : '',
                valueFormatter: (p) => {
                    if (p.node?.rowPinned) {
                        return p.value == null ? '' : `Δ ${fmtKg(p.value)} kg`;
                    }
                    return p.value == null
                        ? ''
                        : `${(Number(p.value) * 100).toFixed(2)} %`;
                },
            },
            {
                colId: 'kilos_totales',
                headerName: 'T. kilos (kg c/merma)',
                editable: (p) =>
                    !readOnly &&
                    !p.node?.rowPinned &&
                    p.data?.kilos_totales_ef != null,
                minWidth: 160,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
                headerTooltip:
                    'Kg con merma efectivos = kg reales × (1 + peso %). En blanco si el registro no tiene insumo. Editar fija un override; vaciar vuelve al valor calculado.',
                valueGetter: (p) => p.data?.kilos_totales_ef ?? null,
                valueSetter: (p: ValueSetterParams<RegistroRow>) => {
                    const v = p.newValue;
                    p.data.kilos_totales =
                        v === '' || v == null ? null : String(Number(v));
                    return true;
                },
                cellClass: (p) =>
                    p.data?.kilos_totales == null
                        ? 'font-semibold opacity-90'
                        : 'font-semibold',
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

    // Fila de totales (footer fijo del grid): Σ kg reales, Σ T. kilos (merma total) y el Δ.
    const sumReales = registros.reduce((s, r) => s + (r.kilos_reales ?? 0), 0);
    const sumTKilos = registros.reduce(
        (s, r) => s + (r.kilos_totales_ef ?? 0),
        0,
    );
    const footerRow = useMemo(
        () =>
            ({
                id: -1,
                kilos_reales: sumReales,
                kilos_totales_ef: sumTKilos,
                peso_porcentual_ef: sumTKilos - sumReales, // Δ entre kg reales y merma total
            }) as unknown as RegistroRow,
        [sumReales, sumTKilos],
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
                    onCellEdited={readOnly ? undefined : onRegistroEdited}
                    pinnedBottomRowData={[footerRow]}
                    getRowStyle={(p) =>
                        p.node.rowPinned ? { fontWeight: 700 } : undefined
                    }
                    paginated={false}
                />
            </div>
        </AppLayout>
    );
}

function DeleteCell(readOnly: boolean) {
    return function Cell({ data, node }: ICellRendererParams<RegistroRow>) {
        if (!data || node.rowPinned) {
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
