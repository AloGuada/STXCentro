import { Head, router } from '@inertiajs/react';
import {
    AllCommunityModule,
    type CellValueChangedEvent,
    type ColDef,
    type ColGroupDef,
    colorSchemeDark,
    ModuleRegistry,
    type RowClassParams,
    themeQuartz,
} from 'ag-grid-community';
import { AgGridReact } from 'ag-grid-react';
import { useMemo, useState } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CotizResumenBloque,
    CotizResumenTipoFormula,
} from '@/types/models';

ModuleRegistry.registerModules([AllCommunityModule]);

const themeLight = themeQuartz.withParams({
    accentColor: 'oklch(0.55 0.2 264)',
    borderRadius: 6,
    fontFamily: 'inherit',
    headerFontWeight: 600,
});
const themeDark = themeLight.withPart(colorSchemeDark);

type Fila = {
    id: number;
    descripcion: string;
    bloque: CotizResumenBloque;
    tipo_formula: CotizResumenTipoFormula;
    coef_default: number | null;
    referencia_extra: string | null;
    orden: number;
    bloqueada: boolean;
};

type Columna = {
    columna_id: number;
    nombre: string;
    orden: number;
    kg: number;
    m2_pintura: number;
    m2_montaje: number;
    importe_materiales: number;
    sueldo_mo_pza: number;
};

type Props = {
    obra: { id: number; nombre: string; op: string | null };
    filas: Fila[];
    columnas: Columna[];
    matriz: Record<number, Record<number, number>>;
    overrides: {
        por_fila: Record<number, number>;
        por_celda: Record<string, number>;
    };
    totalesPorFila: Record<number, number>;
    obraTotales: { kg_total: number; m2_montaje_total: number };
    importeTotalVenta: number;
    bloqueColores: Record<string, string>;
};

type MatrixRow = {
    fila_id: number;
    descripcion: string;
    bloque: CotizResumenBloque;
    tipo_formula: CotizResumenTipoFormula;
    bloqueada: boolean;
    totales: number;
    [key: string]: unknown;
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtMoney = (n: number) => money.format(Number(n ?? 0));
const fmtNum = (n: number, d = 2) =>
    Number(n ?? 0).toLocaleString('es-MX', {
        maximumFractionDigits: d,
        minimumFractionDigits: 0,
    });
const fmtCoef = (n: number | null) =>
    n == null
        ? ''
        : Number(n).toLocaleString('es-MX', { maximumFractionDigits: 4 });
const fmtKg = (n: number | null) =>
    n == null
        ? ''
        : Number(n).toLocaleString('es-MX', {
              maximumFractionDigits: 4,
              minimumFractionDigits: 2,
          });

// Subcol2 ($/kg) editable: el usuario teclea el $/kg directo del concepto.
const SUBCOL2_EDITABLE: CotizResumenTipoFormula[] = [
    'por_kg',
    'mo_fab_subgrupo',
];
// Subcol1 (tarifa) editable: $/m² (pintura) y % (margen). El $/kg se deriva.
const SUBCOL1_EDITABLE: CotizResumenTipoFormula[] = [
    'por_m2_pintura',
    'margen',
];

// Props que cambian al editar un coef/sueldo (recarga parcial sin remontar la grilla).
const RELOAD_ONLY = [
    'matriz',
    'overrides',
    'totalesPorFila',
    'obraTotales',
    'importeTotalVenta',
    'columnas',
];

// Tarifas $/kg de M.O. Fabricación por tipo de estructura (atajos del modal).
const TARIFAS_MO_FAB: { label: string; valor: number }[] = [
    { label: 'Estructura metálica', valor: 4.91 },
    { label: 'Polinería', valor: 3.28 },
    { label: 'Armadura', valor: 6.28 },
    { label: 'Joist', valor: 6.28 },
    { label: 'Anclas', valor: 9.56 },
    { label: 'Bastidores', valor: 6.26 },
];

export default function ResumenIndex({
    obra,
    filas,
    columnas,
    matriz,
    overrides,
    totalesPorFila,
    obraTotales,
    importeTotalVenta,
    bloqueColores,
}: Props) {
    const { resolvedAppearance } = useAppearance();
    const [moFabColumnaId, setMoFabColumnaId] = useState<number | null>(null);
    const moFabColumna =
        moFabColumnaId == null
            ? null
            : (columnas.find((c) => c.columna_id === moFabColumnaId) ?? null);
    const filasMoFab = useMemo(
        () => filas.filter((f) => f.tipo_formula === 'mo_fab_subgrupo'),
        [filas],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        { title: 'Resumen', href: `/admin/cotiz/obras/${obra.id}/resumen` },
    ];

    const coefEfectivo = (fila: Fila, columnaId: number): number => {
        const k = `${fila.id}-${columnaId}`;
        if (overrides.por_celda[k] != null) return overrides.por_celda[k];
        if (overrides.por_fila[fila.id] != null)
            return overrides.por_fila[fila.id];
        return fila.coef_default ?? 0;
    };

    const rowData = useMemo<MatrixRow[]>(() => {
        return filas.map((f) => {
            const row: MatrixRow = {
                fila_id: f.id,
                descripcion: f.descripcion,
                bloque: f.bloque,
                tipo_formula: f.tipo_formula,
                bloqueada: f.bloqueada,
                totales: totalesPorFila[f.id] ?? 0,
            };
            for (const c of columnas) {
                const importe = matriz[f.id]?.[c.columna_id] ?? 0;
                row[`imp_${c.columna_id}`] = importe;
                row[`kg_${c.columna_id}`] = c.kg > 0 ? importe / c.kg : null;

                let tarifa: number | null = null;
                switch (f.tipo_formula) {
                    case 'por_m2_pintura':
                    case 'margen':
                        tarifa = coefEfectivo(f, c.columna_id);
                        break;
                    case 'flete_kg_prorrateado':
                        tarifa =
                            obraTotales.kg_total > 0
                                ? c.kg / obraTotales.kg_total
                                : 0;
                        break;
                    case 'viatico_m2_prorrateado':
                        tarifa =
                            obraTotales.m2_montaje_total > 0
                                ? c.m2_montaje / obraTotales.m2_montaje_total
                                : 0;
                        break;
                }
                row[`tar_${c.columna_id}`] = tarifa;
            }
            return row;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filas, columnas, matriz, overrides, totalesPorFila, obraTotales]);

    const rowsScroll = useMemo(
        () => rowData.filter((r) => r.bloque !== 'TOTALES'),
        [rowData],
    );
    const rowsTotales = useMemo(
        () => rowData.filter((r) => r.bloque === 'TOTALES'),
        [rowData],
    );

    const cellClassFor = (tf?: CotizResumenTipoFormula) => {
        if (tf === 'total') return 'font-bold bg-primary/10';
        if (tf === 'subtotal') return 'font-bold bg-base-200';
        if (tf === 'margen') return 'font-semibold opacity-80';
        return '';
    };

    const columnDefs = useMemo<
        (ColDef<MatrixRow> | ColGroupDef<MatrixRow>)[]
    >(() => {
        const base: (ColDef<MatrixRow> | ColGroupDef<MatrixRow>)[] = [
            {
                field: 'bloque',
                headerName: 'Bloque',
                width: 110,
                pinned: 'left',
                valueFormatter: (p) => String(p.value ?? '').replace('_', ' '),
                cellClass: 'text-[10px] uppercase opacity-60',
            },
            {
                field: 'descripcion',
                headerName: 'Concepto',
                width: 240,
                pinned: 'left',
                valueFormatter: (p) =>
                    p.data?.bloqueada
                        ? `🔒 ${p.value ?? ''}`
                        : String(p.value ?? ''),
                cellClass: (p) => {
                    const tf = p.data?.tipo_formula;
                    if (tf === 'subtotal') return 'font-bold bg-base-200';
                    if (tf === 'total') return 'font-bold bg-primary/10';
                    if (tf === 'margen') return 'font-semibold opacity-80';
                    return 'font-medium';
                },
            },
        ];

        for (const c of columnas) {
            base.push({
                headerName: c.nombre,
                marryChildren: true,
                headerGroupComponent: NaveGroupHeader,
                headerGroupComponentParams: {
                    kg: c.kg,
                    m2: c.m2_pintura,
                    columnaId: c.columna_id,
                    onOpenMoFab: setMoFabColumnaId,
                },
                children: [
                    {
                        colId: `tar_${c.columna_id}`,
                        field: `tar_${c.columna_id}`,
                        headerName: 'Tarifa',
                        width: 90,
                        type: 'numericColumn',
                        editable: (p) =>
                            !p.data?.bloqueada &&
                            SUBCOL1_EDITABLE.includes(p.data!.tipo_formula),
                        valueParser: (p) =>
                            p.newValue === '' || p.newValue == null
                                ? null
                                : Number(p.newValue),
                        valueFormatter: (p) =>
                            fmtCoef(p.value as number | null),
                        cellClass: (p) => {
                            const editable =
                                !p.data?.bloqueada &&
                                SUBCOL1_EDITABLE.includes(p.data!.tipo_formula);
                            return `${cellClassFor(p.data?.tipo_formula)} ${editable ? '' : 'italic opacity-50'}`.trim();
                        },
                    },
                    {
                        colId: `kg_${c.columna_id}`,
                        field: `kg_${c.columna_id}`,
                        headerName: '$/kg',
                        width: 96,
                        type: 'numericColumn',
                        editable: (p) =>
                            !p.data?.bloqueada &&
                            SUBCOL2_EDITABLE.includes(p.data!.tipo_formula),
                        valueParser: (p) =>
                            p.newValue === '' || p.newValue == null
                                ? null
                                : Number(p.newValue),
                        valueFormatter: (p) => fmtKg(p.value as number | null),
                        cellClass: (p) => {
                            const editable =
                                !p.data?.bloqueada &&
                                SUBCOL2_EDITABLE.includes(p.data!.tipo_formula);
                            return `${cellClassFor(p.data?.tipo_formula)} ${editable ? 'font-medium' : 'opacity-70'}`.trim();
                        },
                    },
                    {
                        colId: `imp_${c.columna_id}`,
                        headerName: 'Importe',
                        width: 130,
                        type: 'numericColumn',
                        valueGetter: (p) =>
                            (p.data?.[`imp_${c.columna_id}`] as
                                | number
                                | undefined) ?? 0,
                        valueFormatter: (p) => fmtMoney(Number(p.value ?? 0)),
                        cellClass: (p) => cellClassFor(p.data?.tipo_formula),
                    },
                ],
            });
        }

        base.push({
            colId: 'totales',
            headerName: 'TOTAL',
            width: 150,
            pinned: 'right',
            type: 'numericColumn',
            valueGetter: (p) => p.data?.totales ?? 0,
            valueFormatter: (p) => fmtMoney(Number(p.value ?? 0)),
            cellClass: (p) => {
                const tf = p.data?.tipo_formula;
                if (tf === 'total') return 'font-bold bg-primary/20';
                if (tf === 'subtotal') return 'font-bold bg-base-300';
                return 'font-semibold bg-base-200';
            },
        });
        return base;
    }, [columnas]);

    const onCellChanged = (e: CellValueChangedEvent<MatrixRow>) => {
        if (!e.data || e.data.bloqueada) return;
        const colId = e.colDef.colId ?? '';
        const tf = e.data.tipo_formula;
        const esTar = colId.startsWith('tar_') && SUBCOL1_EDITABLE.includes(tf);
        const esKg = colId.startsWith('kg_') && SUBCOL2_EDITABLE.includes(tf);
        if (!esTar && !esKg) return;
        const columnaId = Number(colId.slice(colId.indexOf('_') + 1));
        if (!Number.isFinite(columnaId)) return;
        const raw = e.newValue;
        const coef = raw == null || raw === '' ? null : Number(raw);
        router.put(
            `/admin/cotiz/obras/${obra.id}/resumen/celda`,
            { fila_id: e.data.fila_id, columna_id: columnaId, coef },
            { preserveScroll: true, preserveState: true, only: RELOAD_ONLY },
        );
    };

    // Los colores de bloque son pasteles claros: solo se aplican en modo claro (en oscuro
    // harían el texto ilegible). En oscuro se usa el fondo del tema.
    const getRowStyle = (p: RowClassParams<MatrixRow>) => {
        if (resolvedAppearance === 'dark') return undefined;
        const b = p.data?.bloque;
        if (b && bloqueColores[b]) return { background: bloqueColores[b] };
        return undefined;
    };

    const theme = resolvedAppearance === 'dark' ? themeDark : themeLight;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Resumen — ${obra.nombre}`} />

            <div className="flex h-[calc(100vh-3.5rem)] flex-col gap-3 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Resumen de Proyecto
                        </h1>
                        <p className="text-xs text-base-content/60">
                            Obra: {obra.nombre} · matriz auto-derivada (1
                            columna por tarjeta)
                        </p>
                    </div>
                    <span className="ml-auto badge badge-lg badge-primary">
                        Importe total: {fmtMoney(importeTotalVenta)}
                    </span>
                </div>

                {columnas.length === 0 ? (
                    <p className="py-12 text-center text-sm italic opacity-50">
                        Sin tarjetas en la obra. Cada tarjeta genera
                        automáticamente su columna.
                    </p>
                ) : (
                    <div className="min-h-0 flex-1">
                        <AgGridReact<MatrixRow>
                            theme={theme}
                            rowData={rowsScroll}
                            pinnedBottomRowData={rowsTotales}
                            columnDefs={columnDefs}
                            getRowId={(p) => String(p.data.fila_id)}
                            onCellValueChanged={onCellChanged}
                            getRowStyle={getRowStyle}
                            groupHeaderHeight={74}
                            defaultColDef={{
                                sortable: false,
                                resizable: true,
                                suppressMovable: true,
                                cellDataType: false,
                            }}
                            singleClickEdit
                            stopEditingWhenCellsLoseFocus
                        />
                    </div>
                )}
            </div>

            {moFabColumna && (
                <MoFabModal
                    obraId={obra.id}
                    columna={moFabColumna}
                    filasMoFab={filasMoFab}
                    porCelda={overrides.por_celda}
                    onClose={() => setMoFabColumnaId(null)}
                />
            )}
        </AppLayout>
    );
}

/** Header de cada nave/columna: nombre + kg/m² + botón que abre el modal de M.O. Fabricación. */
function NaveGroupHeader(props: {
    displayName?: string;
    kg?: number;
    m2?: number;
    columnaId?: number;
    onOpenMoFab?: (id: number) => void;
}) {
    return (
        <div className="flex w-full flex-col justify-center gap-0.5 py-1 leading-tight">
            <span className="truncate font-semibold" title={props.displayName}>
                {props.displayName}
            </span>
            <span className="text-[10px] whitespace-nowrap opacity-70">
                {fmtNum(props.kg ?? 0, 0)} kg · {fmtNum(props.m2 ?? 0, 0)} m²
            </span>
            <button
                type="button"
                className="btn h-5 min-h-0 px-2 text-[10px] font-normal normal-case btn-outline btn-xs"
                title="Editar M.O. Fabricación (sueldo $/kg + subgrupos)"
                onClick={(e) => {
                    e.stopPropagation();
                    if (props.columnaId != null)
                        props.onOpenMoFab?.(props.columnaId);
                }}
            >
                M.O. FAB…
            </button>
        </div>
    );
}

/**
 * Modal de M.O. Fabricación por columna: edita el sueldo $/kg de la columna y el $/kg directo
 * (override) de cada subgrupo M.O. FAB (HABILITADO / ARMADO Y SOLDADO / SUPERVISIÓN).
 */
function MoFabModal({
    obraId,
    columna,
    filasMoFab,
    porCelda,
    onClose,
}: {
    obraId: number;
    columna: Columna;
    filasMoFab: Fila[];
    porCelda: Record<string, number>;
    onClose: () => void;
}) {
    const [sueldo, setSueldo] = useState(
        columna.sueldo_mo_pza ? String(columna.sueldo_mo_pza) : '',
    );
    // $/kg local por fila (override). '' = sin override (usa coef × sueldo).
    const [locales, setLocales] = useState<Record<number, string>>(() => {
        const init: Record<number, string> = {};
        for (const f of filasMoFab) {
            const ov = porCelda[`${f.id}-${columna.columna_id}`];
            init[f.id] = ov == null ? '' : String(ov);
        }
        return init;
    });

    const sueldoNum = Number(sueldo) || 0;

    const saveSueldo = (valor: string) =>
        router.put(
            `/admin/cotiz/resumen-columnas/${columna.columna_id}/sueldo`,
            { sueldo_mo_pza: valor.trim() === '' ? null : Number(valor) },
            { preserveScroll: true, preserveState: true, only: RELOAD_ONLY },
        );

    const saveCelda = (filaId: number, raw: string) =>
        router.put(
            `/admin/cotiz/obras/${obraId}/resumen/celda`,
            {
                fila_id: filaId,
                columna_id: columna.columna_id,
                coef: raw.trim() === '' ? null : Number(raw),
            },
            { preserveScroll: true, preserveState: true, only: RELOAD_ONLY },
        );

    return (
        <dialog className="modal-open modal">
            <div className="modal-box max-w-xl">
                <div className="mb-3 flex items-center gap-2">
                    <h3 className="text-lg font-bold">M.O. FABRICACIÓN</h3>
                    <span className="truncate text-xs opacity-60">
                        {columna.nombre} · {fmtNum(columna.kg, 0)} kg
                    </span>
                    <button
                        type="button"
                        className="btn ml-auto btn-ghost btn-sm"
                        onClick={onClose}
                    >
                        ✕
                    </button>
                </div>

                <label className="mb-4 block">
                    <span className="text-xs opacity-70">
                        Tarifa $/kg por tipo de estructura (o captura libre)
                    </span>
                    <div className="mt-1 flex items-center gap-2">
                        <select
                            className="select-bordered select w-64 select-sm"
                            value={(() => {
                                const i = TARIFAS_MO_FAB.findIndex(
                                    (t) => Math.abs(sueldoNum - t.valor) < 1e-9,
                                );
                                return i >= 0 ? String(i) : 'custom';
                            })()}
                            onChange={(e) => {
                                if (e.target.value === 'custom') return;
                                const t =
                                    TARIFAS_MO_FAB[Number(e.target.value)];
                                setSueldo(String(t.valor));
                                saveSueldo(String(t.valor));
                            }}
                        >
                            <option value="custom">Personalizado…</option>
                            {TARIFAS_MO_FAB.map((t, i) => (
                                <option key={t.label} value={i}>
                                    {t.label} — {t.valor} $/kg
                                </option>
                            ))}
                        </select>
                        <input
                            type="number"
                            step="0.01"
                            className="input-bordered input input-sm w-28"
                            value={sueldo}
                            placeholder="0"
                            onChange={(e) => setSueldo(e.target.value)}
                            onBlur={(e) => saveSueldo(e.target.value)}
                        />
                    </div>
                </label>

                <div className="mb-1 text-xs opacity-60">
                    Subgrupos — $/kg directo (vacío = coef default × sueldo)
                </div>
                <table className="table table-xs">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th className="text-right">$/kg</th>
                            <th className="text-right opacity-50">Default</th>
                            <th className="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filasMoFab.map((f) => {
                            const local = locales[f.id] ?? '';
                            const override = local.trim() !== '';
                            const def = (f.coef_default ?? 0) * sueldoNum;
                            const efectivo = override ? Number(local) : def;
                            return (
                                <tr key={f.id}>
                                    <td>
                                        {f.bloqueada ? '🔒 ' : ''}
                                        {f.descripcion}
                                    </td>
                                    <td className="text-right">
                                        <input
                                            type="number"
                                            step="0.01"
                                            disabled={f.bloqueada}
                                            className={`input-bordered input input-xs w-24 text-right ${override ? 'font-semibold text-success' : ''}`}
                                            value={local}
                                            placeholder={def.toFixed(2)}
                                            onChange={(e) =>
                                                setLocales((s) => ({
                                                    ...s,
                                                    [f.id]: e.target.value,
                                                }))
                                            }
                                            onBlur={(e) =>
                                                saveCelda(f.id, e.target.value)
                                            }
                                        />
                                    </td>
                                    <td className="text-right font-mono text-[11px] opacity-50">
                                        {def.toFixed(2)}
                                    </td>
                                    <td className="text-right font-semibold">
                                        {fmtMoney(efectivo * columna.kg)}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                <div className="modal-action">
                    <button
                        type="button"
                        className="btn btn-sm"
                        onClick={onClose}
                    >
                        Cerrar
                    </button>
                </div>
            </div>
            <button type="button" className="modal-backdrop" onClick={onClose}>
                cerrar
            </button>
        </dialog>
    );
}
