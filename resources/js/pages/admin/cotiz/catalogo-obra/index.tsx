import { EditableGrid } from '@/components/cotiz/editable-grid';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CotizCatalogoFactorRow,
    CotizCatalogoInsumoRow,
    CotizObra,
} from '@/types/models';
import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { RotateCcwIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

type Props = {
    obra: CotizObra;
    insumos: CotizCatalogoInsumoRow[];
    factores: CotizCatalogoFactorRow[];
};

/** Fila plana de insumo: valores globales (g_*) + override por obra (o_*, NULL = sin override). */
type InsumoRow = {
    id: number;
    g_descripcion: string;
    g_codigo_stumis: string | null;
    g_precio_unitario: number;
    g_peso_lineal: number | null;
    g_peso_default: number | null;
    o_descripcion: string | null;
    o_codigo_stumis: string | null;
    o_precio_unitario: number | null;
    o_peso_lineal: number | null;
    o_peso_default: number | null;
    comentario: string | null;
};

type FactorRow = {
    id: number;
    codigo: string;
    g_nombre: string;
    g_formula: string | null;
    g_descripcion: string | null;
    o_nombre: string | null;
    o_formula: string | null;
    o_descripcion: string | null;
    comentario: string | null;
};

const num = (v: string | null | undefined): number | null =>
    v == null ? null : Number(v);

function toInsumoRow(r: CotizCatalogoInsumoRow): InsumoRow {
    return {
        id: r.insumo.id,
        g_descripcion: r.insumo.descripcion,
        g_codigo_stumis: r.insumo.codigo_stumis,
        g_precio_unitario: Number(r.insumo.precio_unitario),
        g_peso_lineal: num(r.insumo.peso_lineal),
        g_peso_default: num(r.insumo.peso_default),
        o_descripcion: r.override?.descripcion ?? null,
        o_codigo_stumis: r.override?.codigo_stumis ?? null,
        o_precio_unitario: num(r.override?.precio_unitario ?? null),
        o_peso_lineal: num(r.override?.peso_lineal ?? null),
        o_peso_default: num(r.override?.peso_default ?? null),
        comentario: r.override?.comentario ?? null,
    };
}

function toFactorRow(r: CotizCatalogoFactorRow): FactorRow {
    return {
        id: r.factor.id,
        codigo: r.factor.codigo,
        g_nombre: r.factor.nombre,
        g_formula: r.factor.formula,
        g_descripcion: r.factor.descripcion,
        o_nombre: r.override?.nombre ?? null,
        o_formula: r.override?.formula ?? null,
        o_descripcion: r.override?.descripcion ?? null,
        comentario: r.override?.comentario ?? null,
    };
}

function insumoDiffs(r: InsumoRow): number {
    let n = 0;
    if (r.o_descripcion != null && r.o_descripcion !== r.g_descripcion) n++;
    if (r.o_codigo_stumis != null && r.o_codigo_stumis !== r.g_codigo_stumis)
        n++;
    if (r.o_precio_unitario != null && r.o_precio_unitario !== r.g_precio_unitario)
        n++;
    if (r.o_peso_lineal != null && r.o_peso_lineal !== r.g_peso_lineal) n++;
    if (r.o_peso_default != null && r.o_peso_default !== r.g_peso_default) n++;
    return n;
}

function factorDiffs(r: FactorRow): number {
    let n = 0;
    if (r.o_nombre != null && r.o_nombre !== r.g_nombre) n++;
    if (r.o_formula != null && r.o_formula !== r.g_formula) n++;
    if (r.o_descripcion != null && r.o_descripcion !== r.g_descripcion) n++;
    return n;
}

const fmtMoney = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtNum = (v: number | null | undefined, d = 2): string =>
    v == null ? '' : Number(v).toFixed(d);

const globalCellClass = 'opacity-60 italic';

function diffClass(o: unknown, g: unknown): string {
    if (o == null || o === '') return 'opacity-40 italic';
    if (o !== g) return 'font-semibold text-warning';
    return '';
}

export default function CatalogoObraIndex({ obra, insumos, factores }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        {
            title: 'Catálogo de obra',
            href: `/admin/cotiz/obras/${obra.id}/catalogo`,
        },
    ];

    const [tab, setTab] = useState<'insumos' | 'factores'>('insumos');
    const [soloCambios, setSoloCambios] = useState(true);

    const insumoRows = useMemo(() => insumos.map(toInsumoRow), [insumos]);
    const factorRows = useMemo(() => factores.map(toFactorRow), [factores]);

    const insumoFiltered = useMemo(
        () =>
            soloCambios
                ? insumoRows.filter(
                      (r) => insumoDiffs(r) > 0 || !!r.comentario,
                  )
                : insumoRows,
        [insumoRows, soloCambios],
    );
    const factorFiltered = useMemo(
        () =>
            soloCambios
                ? factorRows.filter(
                      (r) => factorDiffs(r) > 0 || !!r.comentario,
                  )
                : factorRows,
        [factorRows, soloCambios],
    );

    const insumosConCambios = insumoRows.filter(
        (r) => insumoDiffs(r) > 0 || !!r.comentario,
    ).length;
    const factoresConCambios = factorRows.filter(
        (r) => factorDiffs(r) > 0 || !!r.comentario,
    ).length;

    const guardarInsumo = (row: InsumoRow) => {
        router.put(
            `/admin/cotiz/obras/${obra.id}/catalogo/insumos/${row.id}`,
            {
                descripcion: row.o_descripcion,
                codigo_stumis: row.o_codigo_stumis,
                precio_unitario: row.o_precio_unitario,
                peso_lineal: row.o_peso_lineal,
                peso_default: row.o_peso_default,
                comentario: row.comentario,
            },
            { preserveScroll: true, preserveState: true, only: ['insumos'] },
        );
    };

    const guardarFactor = (row: FactorRow) => {
        router.put(
            `/admin/cotiz/obras/${obra.id}/catalogo/factores/${row.id}`,
            {
                nombre: row.o_nombre,
                formula: row.o_formula,
                descripcion: row.o_descripcion,
                comentario: row.comentario,
            },
            { preserveScroll: true, preserveState: true, only: ['factores'] },
        );
    };

    const resetInsumo = (row: InsumoRow) => {
        if (!confirm(`¿Resetear los cambios de "${row.g_descripcion}"?`)) return;
        router.delete(
            `/admin/cotiz/obras/${obra.id}/catalogo/insumos/${row.id}`,
            { preserveScroll: true, preserveState: true, only: ['insumos'] },
        );
    };

    const resetFactor = (row: FactorRow) => {
        if (!confirm(`¿Resetear los cambios del factor "${row.codigo}"?`)) return;
        router.delete(
            `/admin/cotiz/obras/${obra.id}/catalogo/factores/${row.id}`,
            { preserveScroll: true, preserveState: true, only: ['factores'] },
        );
    };

    const insumoCols = useMemo<ColDef<InsumoRow>[]>(
        () => [
            {
                field: 'g_descripcion',
                headerName: 'Insumo',
                pinned: 'left',
                minWidth: 220,
                editable: false,
                cellRenderer: (p: ICellRendererParams<InsumoRow>) => {
                    if (!p.data) return null;
                    const n = insumoDiffs(p.data);
                    return (
                        <div className="flex items-center gap-2">
                            <span className="truncate">{p.data.g_descripcion}</span>
                            {n > 0 && (
                                <span className="badge badge-warning badge-xs">
                                    {n}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                field: 'g_precio_unitario',
                headerName: 'P.U. Global',
                minWidth: 130,
                editable: false,
                type: 'numericColumn',
                valueFormatter: (p) => fmtMoney.format(Number(p.value ?? 0)),
                cellClass: globalCellClass,
            },
            {
                field: 'o_precio_unitario',
                headerName: 'P.U. Proyecto',
                minWidth: 140,
                editable: true,
                type: 'numericColumn',
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                valueFormatter: (p) =>
                    p.value == null ? '' : fmtMoney.format(Number(p.value)),
                cellClass: (p) =>
                    diffClass(p.data?.o_precio_unitario, p.data?.g_precio_unitario),
            },
            {
                field: 'o_descripcion',
                headerName: 'Desc. Proyecto',
                minWidth: 200,
                editable: true,
                cellClass: (p) =>
                    diffClass(p.data?.o_descripcion, p.data?.g_descripcion),
            },
            {
                field: 'o_codigo_stumis',
                headerName: 'Cód. Strumis (P)',
                minWidth: 150,
                editable: true,
                cellClass: (p) =>
                    diffClass(p.data?.o_codigo_stumis, p.data?.g_codigo_stumis) +
                    ' font-mono text-xs',
            },
            {
                field: 'g_peso_lineal',
                headerName: 'Peso ml/m² (G)',
                minWidth: 130,
                editable: false,
                type: 'numericColumn',
                valueFormatter: (p) => fmtNum(p.value),
                cellClass: globalCellClass,
            },
            {
                field: 'o_peso_lineal',
                headerName: 'Peso ml/m² (P)',
                minWidth: 130,
                editable: true,
                type: 'numericColumn',
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
                valueFormatter: (p) => fmtNum(p.value),
                cellClass: (p) =>
                    diffClass(p.data?.o_peso_lineal, p.data?.g_peso_lineal),
            },
            {
                field: 'o_peso_default',
                headerName: 'Peso pres. (P)',
                minWidth: 130,
                editable: true,
                type: 'numericColumn',
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 6, min: 0 },
                valueFormatter: (p) => fmtNum(p.value),
                cellClass: (p) =>
                    diffClass(p.data?.o_peso_default, p.data?.g_peso_default),
            },
            {
                field: 'comentario',
                headerName: 'Comentario',
                minWidth: 200,
                flex: 1,
                editable: true,
                cellClass: 'italic text-xs',
            },
            {
                headerName: '',
                pinned: 'right',
                width: 56,
                editable: false,
                sortable: false,
                filter: false,
                cellRenderer: (p: ICellRendererParams<InsumoRow>) => {
                    if (
                        !p.data ||
                        (insumoDiffs(p.data) === 0 && !p.data.comentario)
                    )
                        return null;
                    return (
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs"
                            title="Resetear esta fila al global"
                            onClick={() => resetInsumo(p.data!)}
                        >
                            <RotateCcwIcon className="size-4" />
                        </button>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [obra.id],
    );

    const factorCols = useMemo<ColDef<FactorRow>[]>(
        () => [
            {
                field: 'codigo',
                headerName: 'Código',
                pinned: 'left',
                minWidth: 150,
                editable: false,
                cellClass: 'font-mono text-xs',
                cellRenderer: (p: ICellRendererParams<FactorRow>) => {
                    if (!p.data) return null;
                    const n = factorDiffs(p.data);
                    return (
                        <div className="flex items-center gap-2">
                            <span className="truncate">{p.data.codigo}</span>
                            {n > 0 && (
                                <span className="badge badge-warning badge-xs">
                                    {n}
                                </span>
                            )}
                        </div>
                    );
                },
            },
            {
                field: 'g_nombre',
                headerName: 'Nombre (G)',
                minWidth: 170,
                editable: false,
                cellClass: globalCellClass,
            },
            {
                field: 'o_nombre',
                headerName: 'Nombre (P)',
                minWidth: 170,
                editable: true,
                cellClass: (p) => diffClass(p.data?.o_nombre, p.data?.g_nombre),
            },
            {
                field: 'g_formula',
                headerName: 'Fórmula (G)',
                minWidth: 220,
                editable: false,
                valueFormatter: (p) => p.value || '(manual)',
                cellClass: globalCellClass + ' font-mono text-xs',
            },
            {
                field: 'o_formula',
                headerName: 'Fórmula (P)',
                minWidth: 220,
                editable: true,
                cellClass: (p) =>
                    diffClass(p.data?.o_formula, p.data?.g_formula) +
                    ' font-mono text-xs',
            },
            {
                field: 'o_descripcion',
                headerName: 'Descripción (P)',
                minWidth: 200,
                editable: true,
                cellClass: (p) =>
                    diffClass(p.data?.o_descripcion, p.data?.g_descripcion),
            },
            {
                field: 'comentario',
                headerName: 'Comentario',
                minWidth: 200,
                flex: 1,
                editable: true,
                cellClass: 'italic text-xs',
            },
            {
                headerName: '',
                pinned: 'right',
                width: 56,
                editable: false,
                sortable: false,
                filter: false,
                cellRenderer: (p: ICellRendererParams<FactorRow>) => {
                    if (
                        !p.data ||
                        (factorDiffs(p.data) === 0 && !p.data.comentario)
                    )
                        return null;
                    return (
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs"
                            title="Resetear este factor al global"
                            onClick={() => resetFactor(p.data!)}
                        >
                            <RotateCcwIcon className="size-4" />
                        </button>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [obra.id],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Catálogo de obra — ${obra.nombre}`} />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Catálogo de obra
                        </h1>
                        <p className="text-sm text-base-content/60">
                            {obra.nombre} · ajusta precios, fórmulas y datos solo
                            para esta obra. Vacío = usa el catálogo global.
                        </p>
                    </div>
                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <span>Solo cambios</span>
                        <input
                            type="checkbox"
                            className="toggle toggle-sm toggle-warning"
                            checked={soloCambios}
                            onChange={(e) => setSoloCambios(e.target.checked)}
                        />
                    </label>
                </div>

                <div role="tablist" className="tabs tabs-box w-fit">
                    <button
                        role="tab"
                        className={`tab ${tab === 'insumos' ? 'tab-active' : ''}`}
                        onClick={() => setTab('insumos')}
                    >
                        Insumos
                        <span className="badge badge-warning badge-xs ml-2">
                            {insumosConCambios}
                        </span>
                    </button>
                    <button
                        role="tab"
                        className={`tab ${tab === 'factores' ? 'tab-active' : ''}`}
                        onClick={() => setTab('factores')}
                    >
                        Factores
                        <span className="badge badge-warning badge-xs ml-2">
                            {factoresConCambios}
                        </span>
                    </button>
                </div>

                {tab === 'insumos' ? (
                    <EditableGrid<InsumoRow>
                        rowData={insumoFiltered}
                        columnDefs={insumoCols}
                        getRowId={(row) => String(row.id)}
                        onCellEdited={guardarInsumo}
                    />
                ) : (
                    <EditableGrid<FactorRow>
                        rowData={factorFiltered}
                        columnDefs={factorCols}
                        getRowId={(row) => String(row.id)}
                        onCellEdited={guardarFactor}
                    />
                )}
            </div>
        </AppLayout>
    );
}
