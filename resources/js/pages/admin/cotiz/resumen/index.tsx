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
import { useMemo } from 'react';
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
                headerName: `${c.nombre} · ${fmtNum(c.kg, 0)} kg`,
                marryChildren: true,
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
            { preserveScroll: true, preserveState: false },
        );
    };

    const getRowStyle = (p: RowClassParams<MatrixRow>) => {
        const b = p.data?.bloque;
        if (b && bloqueColores[b]) return { background: bloqueColores[b] };
        return undefined;
    };

    const theme = resolvedAppearance === 'dark' ? themeDark : themeLight;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Resumen — ${obra.nombre}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Resumen de Proyecto
                        </h1>
                        <p className="text-sm text-base-content/60">
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
                    <>
                        <div style={{ height: '62vh' }}>
                            <AgGridReact<MatrixRow>
                                theme={theme}
                                rowData={rowsScroll}
                                pinnedBottomRowData={rowsTotales}
                                columnDefs={columnDefs}
                                getRowId={(p) => String(p.data.fila_id)}
                                onCellValueChanged={onCellChanged}
                                getRowStyle={getRowStyle}
                                groupHeaderHeight={40}
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

                        <div className="card border border-base-300 bg-base-100 p-4">
                            <h3 className="mb-2 text-sm font-semibold">
                                M.O. Fabricación — sueldo $/kg por columna
                            </h3>
                            <p className="mb-2 text-xs opacity-60">
                                Drivea el default de los subgrupos M.O. FAB
                                (coef × sueldo). El $/kg directo de cada
                                subgrupo se edita en la columna «$/kg» de la
                                grilla.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                {columnas.map((c) => (
                                    <label
                                        key={c.columna_id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <span className="max-w-[160px] truncate opacity-70">
                                            {c.nombre}
                                        </span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            className="input-bordered input input-sm w-28"
                                            defaultValue={c.sueldo_mo_pza || ''}
                                            placeholder="0"
                                            onBlur={(e) =>
                                                router.put(
                                                    `/admin/cotiz/resumen-columnas/${c.columna_id}/sueldo`,
                                                    {
                                                        sueldo_mo_pza:
                                                            e.target.value ===
                                                            ''
                                                                ? null
                                                                : Number(
                                                                      e.target
                                                                          .value,
                                                                  ),
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        />
                                    </label>
                                ))}
                            </div>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
