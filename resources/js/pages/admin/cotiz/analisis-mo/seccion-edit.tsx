import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EditableGrid } from '@/components/cotiz/editable-grid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizFaseMontaje, CotizPersonalCategoria } from '@/types/models';

type Seccion = {
    id: number;
    obra_id: number;
    nombre: string;
    area_m2: number | null;
    importe_directo: number;
    importe_total: number;
};

type Categoria = Pick<
    CotizPersonalCategoria,
    'id' | 'codigo' | 'nombre' | 'orden'
> & { sueldo_semanal: number };
type Fase = Pick<
    CotizFaseMontaje,
    'id' | 'codigo' | 'nombre' | 'unidad' | 'orden'
>;
type Celda = { fase_id: number; categoria_id: number; cantidad: number };
type FaseResumen = {
    nomina_semanal: number;
    dias: number;
    semanas: number;
    importe: number;
};

type Rendimiento = {
    id: number;
    fase_id: number;
    fase_nombre: string | null;
    fase_unidad: string | null;
    concepto: string;
    largo_pza: string | null;
    cantidad: number;
    rendimiento: number;
    total_dias: number;
};

type Props = {
    seccion: Seccion;
    categorias: Categoria[];
    fases: Fase[];
    celdas: Celda[];
    resumenPorFase: Record<number, FaseResumen>;
    rendimientos: Rendimiento[];
};

type MatrixRow = {
    id: string;
    kind: 'cat' | 'nomina' | 'dias' | 'semanas' | 'importe';
    nombre: string;
    sueldo: number | null;
    categoria_id?: number;
    [key: string]: unknown;
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtMoney = (n: number) => money.format(Number(n ?? 0));
const fmtNum = (n: number, d = 2) => Number(n ?? 0).toFixed(d);

export default function SeccionEdit({
    seccion,
    categorias,
    fases,
    celdas,
    resumenPorFase,
    rendimientos,
}: Props) {
    const [tab, setTab] = useState<'rendimientos' | 'personal'>('rendimientos');
    const [faseAgregar, setFaseAgregar] = useState<number | ''>('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        {
            title: 'Análisis MO',
            href: `/admin/cotiz/obras/${seccion.obra_id}/analisis-mo`,
        },
        {
            title: seccion.nombre,
            href: `/admin/cotiz/secciones/${seccion.id}/edit`,
        },
    ];

    /* ---- Matriz personal × fase ---- */
    const celdaMap = useMemo(() => {
        const m = new Map<string, number>();
        for (const c of celdas)
            m.set(`${c.fase_id}:${c.categoria_id}`, c.cantidad);
        return m;
    }, [celdas]);

    const matrixRows = useMemo<MatrixRow[]>(() => {
        return categorias.map((cat) => {
            const row: MatrixRow = {
                id: `cat_${cat.id}`,
                kind: 'cat',
                categoria_id: cat.id,
                nombre: cat.nombre,
                sueldo: cat.sueldo_semanal,
            };
            for (const f of fases)
                row[`f_${f.id}`] = celdaMap.get(`${f.id}:${cat.id}`) ?? 0;
            return row;
        });
    }, [categorias, fases, celdaMap]);

    const pinnedBottom = useMemo<MatrixRow[]>(() => {
        const make = (
            kind: MatrixRow['kind'],
            nombre: string,
            pick: (r: FaseResumen) => number,
        ): MatrixRow => {
            const row: MatrixRow = {
                id: `foot_${kind}`,
                kind,
                nombre,
                sueldo: null,
            };
            for (const f of fases)
                row[`f_${f.id}`] = pick(
                    resumenPorFase[f.id] ?? {
                        nomina_semanal: 0,
                        dias: 0,
                        semanas: 0,
                        importe: 0,
                    },
                );
            return row;
        };
        return [
            make('nomina', 'NÓMINA SEMANAL', (r) => r.nomina_semanal),
            make('dias', 'DÍAS', (r) => r.dias),
            make('semanas', 'SEMANAS', (r) => r.semanas),
            make('importe', 'IMPORTE', (r) => r.importe),
        ];
    }, [fases, resumenPorFase]);

    const matrixCols = useMemo<ColDef<MatrixRow>[]>(() => {
        const cols: ColDef<MatrixRow>[] = [
            {
                field: 'nombre',
                headerName: 'Categoría',
                width: 190,
                pinned: 'left',
                cellClass: (p) =>
                    p.data?.kind === 'cat' ? '' : 'font-semibold bg-base-200',
            },
            {
                field: 'sueldo',
                headerName: '$/sem',
                width: 110,
                pinned: 'left',
                valueFormatter: (p) =>
                    p.value == null ? '' : fmtMoney(Number(p.value)),
            },
        ];
        for (const f of fases) {
            cols.push({
                colId: `f_${f.id}`,
                headerName: f.nombre,
                headerTooltip: `${f.codigo} (${f.unidad})`,
                width: 130,
                editable: (p) => p.data?.kind === 'cat',
                cellEditor: 'agNumberCellEditor',
                valueGetter: (p) =>
                    (p.data?.[`f_${f.id}`] as number | undefined) ?? 0,
                valueSetter: (p) => {
                    if (p.data?.kind !== 'cat') return false;
                    const v = Number(p.newValue);
                    if (!Number.isFinite(v)) return false;
                    p.data[`f_${f.id}`] = v;
                    return true;
                },
                valueFormatter: (p) => {
                    const v = Number(p.value ?? 0);
                    const kind = p.data?.kind;
                    if (kind === 'nomina' || kind === 'importe')
                        return fmtMoney(v);
                    if (kind === 'dias' || kind === 'semanas')
                        return v === 0 ? '' : fmtNum(v, 2);
                    return v === 0 ? '' : String(v);
                },
                cellClass: (p) => {
                    const kind = p.data?.kind;
                    if (kind === 'importe') return 'font-bold bg-base-200';
                    if (kind && kind !== 'cat') return 'bg-base-200 opacity-80';
                    return '';
                },
            });
        }
        return cols;
    }, [fases]);

    const guardarCelda = (row: MatrixRow, field?: string) => {
        if (row.kind !== 'cat' || !field?.startsWith('f_')) return;
        const faseId = Number(field.slice(2));
        router.put(
            `/admin/cotiz/secciones/${seccion.id}/personal`,
            {
                fase_id: faseId,
                categoria_id: row.categoria_id,
                cantidad: Number(row[field] ?? 0),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    /* ---- Rendimientos ---- */
    const guardarRend = (row: Rendimiento, field?: string) => {
        const payload: Record<string, unknown> = {};
        if (field === 'concepto') payload.concepto = row.concepto;
        else if (field === 'largo_pza') payload.largo_pza = row.largo_pza ?? '';
        else if (field === 'cantidad') payload.cantidad = row.cantidad;
        else if (field === 'rendimiento') payload.rendimiento = row.rendimiento;
        else return;
        router.put(`/admin/cotiz/seccion-rendimientos/${row.id}`, payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const rendCols = useMemo<ColDef<Rendimiento>[]>(
        () => [
            {
                field: 'fase_nombre',
                headerName: 'Fase',
                width: 170,
                cellClass: 'font-semibold',
            },
            {
                field: 'concepto',
                headerName: 'Concepto',
                flex: 2,
                minWidth: 220,
                editable: true,
            },
            {
                field: 'largo_pza',
                headerName: 'Largo × pza',
                width: 150,
                editable: true,
                valueFormatter: (p) => p.value ?? '',
            },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                width: 120,
                editable: true,
                cellEditor: 'agNumberCellEditor',
            },
            {
                field: 'rendimiento',
                headerName: 'Rend. (qty/día)',
                width: 150,
                editable: true,
                cellEditor: 'agNumberCellEditor',
            },
            {
                field: 'total_dias',
                headerName: 'Total días',
                width: 130,
                valueFormatter: (p) => fmtNum(p.value, 2),
                cellClass: 'font-semibold',
            },
            {
                headerName: '',
                width: 64,
                sortable: false,
                filter: false,
                cellRenderer: ({ data }: ICellRendererParams<Rendimiento>) =>
                    data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            onClick={() => {
                                if (confirm(`¿Eliminar "${data.concepto}"?`)) {
                                    router.delete(
                                        `/admin/cotiz/seccion-rendimientos/${data.id}`,
                                        { preserveScroll: true },
                                    );
                                }
                            }}
                        >
                            <Trash2Icon className="size-4" />
                        </button>
                    ) : null,
            },
        ],
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Zona — ${seccion.nombre}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            router.visit(
                                `/admin/cotiz/obras/${seccion.obra_id}/analisis-mo`,
                            )
                        }
                    >
                        ← Análisis MO
                    </Button>
                    <input
                        className="input-bordered input input-sm max-w-md flex-1 text-lg font-semibold"
                        defaultValue={seccion.nombre}
                        onBlur={(e) => {
                            const v = e.target.value.trim();
                            if (v && v !== seccion.nombre) {
                                router.put(
                                    `/admin/cotiz/secciones/${seccion.id}`,
                                    { nombre: v },
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    />
                    <label className="flex items-center gap-2 text-sm">
                        Área m²:
                        <input
                            type="number"
                            className="input-bordered input input-sm w-28"
                            defaultValue={seccion.area_m2 ?? ''}
                            onBlur={(e) =>
                                router.put(
                                    `/admin/cotiz/secciones/${seccion.id}`,
                                    {
                                        area_m2:
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </label>
                    <span className="badge badge-lg badge-primary">
                        Total: {fmtMoney(seccion.importe_total)}
                    </span>
                    <span className="badge badge-ghost">
                        Directo: {fmtMoney(seccion.importe_directo)}
                    </span>
                </div>

                <div role="tablist" className="tabs-boxed tabs self-start">
                    <button
                        role="tab"
                        className={`tab ${tab === 'rendimientos' ? 'tab-active' : ''}`}
                        onClick={() => setTab('rendimientos')}
                    >
                        Rendimientos por fase
                        <span className="ml-2 badge badge-ghost badge-sm">
                            {rendimientos.length}
                        </span>
                    </button>
                    <button
                        role="tab"
                        className={`tab ${tab === 'personal' ? 'tab-active' : ''}`}
                        onClick={() => setTab('personal')}
                    >
                        Personal por fase
                    </button>
                </div>

                {tab === 'personal' && (
                    <EditableGrid<MatrixRow>
                        rowData={matrixRows}
                        columnDefs={matrixCols}
                        getRowId={(r) => r.id}
                        onCellEdited={guardarCelda}
                        pinnedBottomRowData={pinnedBottom}
                        height="60vh"
                        paginated={false}
                    />
                )}

                {tab === 'rendimientos' && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <span className="text-sm opacity-60">
                                Agregar renglón:
                            </span>
                            <select
                                className="select-bordered select select-sm"
                                value={faseAgregar}
                                onChange={(e) =>
                                    setFaseAgregar(
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Fase…</option>
                                {fases.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.nombre}
                                    </option>
                                ))}
                            </select>
                            <Button
                                type="button"
                                variant="primary"
                                disabled={faseAgregar === ''}
                                onClick={() =>
                                    router.post(
                                        `/admin/cotiz/secciones/${seccion.id}/rendimientos`,
                                        { fase_id: faseAgregar },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                + Renglón
                            </Button>
                        </div>
                        <EditableGrid<Rendimiento>
                            rowData={rendimientos}
                            columnDefs={rendCols}
                            getRowId={(r) => String(r.id)}
                            onCellEdited={guardarRend}
                            height="55vh"
                            paginated={false}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
