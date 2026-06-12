import { Head, router } from '@inertiajs/react';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { Trash2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EditableGrid } from '@/components/cotiz/editable-grid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CotizFaseMontaje,
    CotizFleteViaticoCatalogo,
    CotizGrupoFlete,
    CotizMetodoFleteEstandar,
} from '@/types/models';

type SeccionRow = {
    id: number;
    nombre: string;
    area_m2: number | null;
    orden: number;
    importe_directo: number;
    importe_total: number;
    semanas: number;
};

type CuadrillaCategoria = {
    categoria_id: number;
    codigo: string;
    nombre: string;
    sueldo_semanal: number;
    orden: number;
    cantidad_por_grupo: number;
};

type CuadrillaTotales = {
    num_grupos: number;
    personas_por_grupo: number;
    personas_totales: number;
    nomina_por_grupo: number;
    nomina_total: number;
    nomina_con_contratista: number;
    personas_viaticos: number;
};

type FvItem = {
    id: number;
    grupo: CotizGrupoFlete;
    orden: number;
    concepto: string;
    unidad: string | null;
    cantidad: number;
    p_unit: number;
    importe: number;
    clave: string | null;
    formula_cantidad: string | null;
    formula_p_unit: string | null;
};

type FleteEstandarRow = {
    id: number;
    tarjeta_id: number;
    tarjeta_nombre: string | null;
    metodo: CotizMetodoFleteEstandar;
    grupo: string | null;
    volumen_override: number | null;
    volumen: number;
    pzas: number;
    camiones: number;
    kg_por_camion: number;
    pzas_por_camion: number;
    ml_por_pza: number;
    orden: number;
};

type Props = {
    obra: {
        id: number;
        nombre: string;
        op: string | null;
        factor_contratista: string;
        num_grupos: number;
    };
    secciones: SeccionRow[];
    cuadrilla: { categorias: CuadrillaCategoria[]; totales: CuadrillaTotales };
    fletesViaticos: {
        items: FvItem[];
        subtotales: Record<string, number>;
        total: number;
        variables: Record<string, number>;
    };
    fletesEstandar: FleteEstandarRow[];
    catalogos: {
        fasesMontaje: Pick<
            CotizFaseMontaje,
            'id' | 'codigo' | 'nombre' | 'unidad'
        >[];
        fletesCatalogo: CotizFleteViaticoCatalogo[];
        tarjetas: { id: number; descripcion: string }[];
        metodos: Record<string, string>;
        grupos: Record<string, string>;
    };
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

type TabId = 'zonas' | 'global' | 'fletes' | 'fletes-estandar';

const TABS: { id: TabId; label: string }[] = [
    { id: 'zonas', label: 'Zonas de proyecto' },
    { id: 'global', label: 'Montaje Global' },
    { id: 'fletes', label: 'Fletes y Viáticos' },
    { id: 'fletes-estandar', label: 'Fletes Estándar' },
];

export default function AnalisisMoIndex(props: Props) {
    const { obra } = props;
    const [tab, setTab] = useState<TabId>('zonas');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        {
            title: 'Análisis MO',
            href: `/admin/cotiz/obras/${obra.id}/analisis-mo`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Análisis MO — ${obra.nombre}`} />

            <div className="space-y-4 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Análisis de MO / Montaje
                    </h1>
                    <p className="text-sm text-base-content/60">
                        Obra: {obra.nombre}
                    </p>
                </div>

                <div role="tablist" className="tabs-boxed tabs self-start">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            role="tab"
                            className={`tab ${tab === t.id ? 'tab-active' : ''}`}
                            onClick={() => setTab(t.id)}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {tab === 'zonas' && (
                    <ZonasPanel obraId={obra.id} secciones={props.secciones} />
                )}
                {tab === 'global' && (
                    <GlobalPanel
                        obraId={obra.id}
                        cuadrilla={props.cuadrilla}
                        secciones={props.secciones}
                        factor={Number(obra.factor_contratista)}
                    />
                )}
                {tab === 'fletes' && (
                    <FletesViaticosPanel
                        obraId={obra.id}
                        data={props.fletesViaticos}
                        grupos={props.catalogos.grupos}
                        catalogo={props.catalogos.fletesCatalogo}
                    />
                )}
                {tab === 'fletes-estandar' && (
                    <FletesEstandarPanel
                        obraId={obra.id}
                        rows={props.fletesEstandar}
                        tarjetas={props.catalogos.tarjetas}
                        metodos={props.catalogos.metodos}
                    />
                )}
            </div>
        </AppLayout>
    );
}

/* ===================== Zonas ===================== */

function ZonasPanel({
    obraId,
    secciones,
}: {
    obraId: number;
    secciones: SeccionRow[];
}) {
    const columnDefs = useMemo<ColDef<SeccionRow>[]>(
        () => [
            { field: 'orden', headerName: '#', width: 70, maxWidth: 90 },
            {
                field: 'nombre',
                headerName: 'Zona',
                flex: 2,
                minWidth: 220,
                cellRenderer: ({ data }: ICellRendererParams<SeccionRow>) =>
                    data ? (
                        <button
                            type="button"
                            className="link text-left link-primary"
                            onClick={() =>
                                router.visit(
                                    `/admin/cotiz/secciones/${data.id}/edit`,
                                )
                            }
                        >
                            {data.nombre}
                        </button>
                    ) : null,
            },
            {
                field: 'area_m2',
                headerName: 'Área (m²)',
                valueFormatter: (p) => (p.value == null ? '' : fmtNum(p.value)),
            },
            {
                field: 'semanas',
                headerName: 'Semanas',
                valueFormatter: (p) => fmtNum(p.value, 2),
            },
            {
                field: 'importe_directo',
                headerName: 'Importe directo',
                valueFormatter: (p) => fmtMoney(p.value),
            },
            {
                field: 'importe_total',
                headerName: 'Total + contratista',
                valueFormatter: (p) => fmtMoney(p.value),
            },
            {
                headerName: '',
                width: 64,
                sortable: false,
                filter: false,
                cellRenderer: ({ data }: ICellRendererParams<SeccionRow>) =>
                    data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            onClick={() => {
                                if (
                                    confirm(
                                        `¿Eliminar zona "${data.nombre}"? Se borran su matriz de personal y rendimientos.`,
                                    )
                                ) {
                                    router.delete(
                                        `/admin/cotiz/secciones/${data.id}`,
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

    const totales = useMemo(() => {
        const m2 = secciones.reduce((s, x) => s + (x.area_m2 ?? 0), 0);
        const semanas = secciones.reduce((s, x) => s + x.semanas, 0);
        const importe = secciones.reduce((s, x) => s + x.importe_total, 0);
        return { m2, semanas, importe };
    }, [secciones]);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="badge badge-ghost">
                    {secciones.length} zonas
                </span>
                <span className="badge badge-lg badge-info">
                    {fmtNum(totales.m2)} m²
                </span>
                <span className="badge badge-lg badge-info">
                    {fmtNum(totales.semanas, 2)} sem
                </span>
                <span className="ml-auto badge badge-lg badge-primary">
                    Total: {fmtMoney(totales.importe)}
                </span>
                <Button
                    type="button"
                    variant="primary"
                    onClick={() =>
                        router.post(
                            `/admin/cotiz/obras/${obraId}/secciones`,
                            {},
                        )
                    }
                >
                    Nueva zona
                </Button>
            </div>
            <EditableGrid<SeccionRow>
                rowData={secciones}
                columnDefs={columnDefs}
                getRowId={(r) => String(r.id)}
                height="60vh"
                paginated={false}
            />
        </div>
    );
}

/* ===================== Montaje Global ===================== */

function GlobalPanel({
    obraId,
    cuadrilla,
    secciones,
    factor,
}: {
    obraId: number;
    cuadrilla: Props['cuadrilla'];
    secciones: SeccionRow[];
    factor: number;
}) {
    const { categorias, totales } = cuadrilla;

    const setCantidad = (categoriaId: number, value: number) => {
        if (!Number.isFinite(value) || value < 0) return;
        router.put(
            `/admin/cotiz/obras/${obraId}/cuadrilla-global`,
            { categoria_id: categoriaId, cantidad_por_grupo: value },
            { preserveScroll: true, preserveState: true },
        );
    };

    const setGrupos = (value: number) => {
        if (!Number.isFinite(value) || value < 1) return;
        router.put(
            `/admin/cotiz/obras/${obraId}/num-grupos`,
            { num_grupos: value },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-4">
            <div className="card border border-base-300 bg-base-100 p-4">
                <div className="mb-3 flex flex-wrap items-center gap-3">
                    <h3 className="font-semibold">
                        Cuadrilla Global para Montaje
                    </h3>
                    <label className="ml-auto flex items-center gap-2 text-sm">
                        Grupos:
                        <input
                            type="number"
                            min={1}
                            defaultValue={totales.num_grupos}
                            onBlur={(e) => setGrupos(Number(e.target.value))}
                            className="input-bordered input input-sm w-20"
                        />
                    </label>
                </div>
                <div className="overflow-x-auto">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th className="text-right">$/sem</th>
                                <th className="text-right">× grupo</th>
                                <th className="text-right">Personas</th>
                                <th className="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            {categorias.map((c) => {
                                const personas =
                                    c.cantidad_por_grupo * totales.num_grupos;
                                return (
                                    <tr key={c.categoria_id}>
                                        <td>{c.nombre}</td>
                                        <td className="text-right">
                                            {fmtMoney(c.sueldo_semanal)}
                                        </td>
                                        <td className="text-right">
                                            <input
                                                type="number"
                                                min={0}
                                                defaultValue={
                                                    c.cantidad_por_grupo
                                                }
                                                onBlur={(e) =>
                                                    setCantidad(
                                                        c.categoria_id,
                                                        Number(e.target.value),
                                                    )
                                                }
                                                className="input-bordered input input-xs w-20 text-right"
                                            />
                                        </td>
                                        <td className="text-right font-semibold">
                                            {personas}
                                        </td>
                                        <td className="text-right">
                                            {fmtMoney(
                                                personas * c.sueldo_semanal,
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr className="border-t-2 font-semibold">
                                <td colSpan={3}>NÓMINA SEMANAL</td>
                                <td className="text-right">
                                    {totales.personas_totales}
                                </td>
                                <td className="text-right">
                                    {fmtMoney(totales.nomina_total)}
                                </td>
                            </tr>
                            <tr className="font-semibold opacity-80">
                                <td colSpan={3}>+15% CONTRATISTA</td>
                                <td></td>
                                <td className="text-right">
                                    {fmtMoney(totales.nomina_con_contratista)}
                                </td>
                            </tr>
                            <tr className="opacity-70">
                                <td colSpan={3}>
                                    Personas para viáticos (sin cabo)
                                </td>
                                <td className="text-right">
                                    {totales.personas_viaticos}
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="card border border-base-300 bg-base-100 p-4">
                <h3 className="mb-2 font-semibold">
                    Importes consolidados por sección
                </h3>
                {secciones.length === 0 ? (
                    <p className="text-sm opacity-60">
                        Sin zonas de montaje. Crea una en la pestaña «Zonas de
                        proyecto».
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    {secciones.map((s) => (
                                        <th key={s.id} className="text-right">
                                            {s.nombre}
                                        </th>
                                    ))}
                                    <th className="text-right">TOTALES</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(
                                    [
                                        [
                                            'IMP. RENDIMIENTOS',
                                            (s: SeccionRow) =>
                                                s.importe_directo,
                                        ],
                                        [
                                            'IND. CONTRATISTA',
                                            (s: SeccionRow) =>
                                                s.importe_directo *
                                                (factor - 1),
                                        ],
                                        [
                                            'IMPORTE TOTAL',
                                            (s: SeccionRow) => s.importe_total,
                                        ],
                                    ] as const
                                ).map(([label, fn], i) => {
                                    const total = secciones.reduce(
                                        (acc, s) => acc + fn(s),
                                        0,
                                    );
                                    return (
                                        <tr
                                            key={label}
                                            className={
                                                i === 2 ? 'font-bold' : ''
                                            }
                                        >
                                            <td className="font-semibold">
                                                {label}
                                            </td>
                                            {secciones.map((s) => (
                                                <td
                                                    key={s.id}
                                                    className="text-right"
                                                >
                                                    {fmtMoney(fn(s))}
                                                </td>
                                            ))}
                                            <td className="text-right font-semibold">
                                                {fmtMoney(total)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ===================== Fletes y Viáticos ===================== */

function FletesViaticosPanel({
    obraId,
    data,
    grupos,
    catalogo,
}: {
    obraId: number;
    data: Props['fletesViaticos'];
    grupos: Record<string, string>;
    catalogo: CotizFleteViaticoCatalogo[];
}) {
    const [grupoAgregar, setGrupoAgregar] = useState<string>('VIATICOS');

    const guardar = (row: FvItem, field?: string) => {
        const payload: Record<string, unknown> = {};
        if (field === 'concepto') payload.concepto = row.concepto;
        else if (field === 'unidad') payload.unidad = row.unidad ?? '';
        else if (field === 'orden') payload.orden = row.orden;
        else if (field === 'cantidad') payload.cantidad = row.cantidad;
        else if (field === 'p_unit') payload.p_unit = row.p_unit;
        else if (field === 'clave') payload.clave = row.clave ?? '';
        else if (field === 'formula_cantidad')
            payload.formula_cantidad = row.formula_cantidad ?? '';
        else if (field === 'formula_p_unit')
            payload.formula_p_unit = row.formula_p_unit ?? '';
        else return;
        router.put(`/admin/cotiz/obra-fletes-viaticos/${row.id}`, payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const columnDefs = useMemo<ColDef<FvItem>[]>(
        () => [
            {
                field: 'grupo',
                headerName: 'Grupo',
                width: 150,
                cellClass: 'font-mono text-xs',
            },
            {
                field: 'orden',
                headerName: '#',
                width: 70,
                editable: true,
                cellEditor: 'agNumberCellEditor',
            },
            {
                field: 'concepto',
                headerName: 'Concepto',
                flex: 2,
                minWidth: 220,
                editable: true,
            },
            {
                field: 'unidad',
                headerName: 'Unidad',
                width: 110,
                editable: true,
                valueFormatter: (p) => p.value ?? '',
            },
            {
                field: 'clave',
                headerName: 'Clave',
                width: 110,
                editable: true,
                valueFormatter: (p) => p.value ?? '',
            },
            {
                field: 'formula_cantidad',
                headerName: 'ƒ Cantidad',
                minWidth: 150,
                editable: true,
                cellClass: 'font-mono text-xs',
                valueFormatter: (p) => p.value ?? '',
            },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                width: 120,
                editable: (p) => !p.data?.formula_cantidad,
                cellEditor: 'agNumberCellEditor',
                cellClass: (p) =>
                    p.data?.formula_cantidad ? 'italic text-info' : '',
                valueFormatter: (p) => fmtNum(p.value, 4),
            },
            {
                field: 'formula_p_unit',
                headerName: 'ƒ P.Unit',
                minWidth: 150,
                editable: true,
                cellClass: 'font-mono text-xs',
                valueFormatter: (p) => p.value ?? '',
            },
            {
                field: 'p_unit',
                headerName: 'P. Unit.',
                width: 130,
                editable: (p) => !p.data?.formula_p_unit,
                cellEditor: 'agNumberCellEditor',
                cellClass: (p) =>
                    p.data?.formula_p_unit ? 'italic text-info' : '',
                valueFormatter: (p) => fmtMoney(p.value),
            },
            {
                colId: 'importe',
                headerName: 'Importe',
                width: 140,
                valueGetter: (p) =>
                    Number(p.data?.cantidad ?? 0) * Number(p.data?.p_unit ?? 0),
                valueFormatter: (p) => fmtMoney(p.value),
                cellClass: 'font-semibold',
            },
            {
                headerName: '',
                width: 64,
                sortable: false,
                filter: false,
                cellRenderer: ({ data }: ICellRendererParams<FvItem>) =>
                    data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            onClick={() => {
                                if (confirm(`¿Eliminar "${data.concepto}"?`)) {
                                    router.delete(
                                        `/admin/cotiz/obra-fletes-viaticos/${data.id}`,
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

    const variables = useMemo(
        () =>
            Object.entries(data.variables).sort(([a], [b]) =>
                a.localeCompare(b),
            ),
        [data.variables],
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="badge badge-ghost">
                    {data.items.length} items
                </span>
                <span className="badge badge-lg badge-primary">
                    Total: {fmtMoney(data.total)}
                </span>
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    <select
                        className="select-bordered select select-sm"
                        value={grupoAgregar}
                        onChange={(e) => setGrupoAgregar(e.target.value)}
                    >
                        {Object.entries(grupos).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <Button
                        type="button"
                        variant="primary"
                        onClick={() =>
                            router.post(
                                `/admin/cotiz/obras/${obraId}/fletes-viaticos`,
                                { grupo: grupoAgregar },
                                { preserveScroll: true },
                            )
                        }
                    >
                        + Item
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={catalogo.length === 0}
                        onClick={() =>
                            router.post(
                                `/admin/cotiz/obras/${obraId}/fletes-viaticos/importar`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        + Plantilla
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            router.post(
                                `/admin/cotiz/obras/${obraId}/fletes-viaticos/aplicar-formulas`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        ƒ del catálogo
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            router.post(
                                `/admin/cotiz/obras/${obraId}/fletes-viaticos/recalcular`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        ↻ Recalcular
                    </Button>
                </div>
            </div>

            <details className="collapse-arrow collapse rounded-box border border-base-300 bg-base-100">
                <summary className="collapse-title min-h-0 py-2 text-xs font-semibold opacity-70">
                    ƒ Variables disponibles para fórmulas ({variables.length})
                </summary>
                <div className="collapse-content">
                    <div className="grid grid-cols-2 gap-x-4 gap-y-0.5 font-mono text-[11px] md:grid-cols-4">
                        {variables.map(([k, v]) => (
                            <div key={k} className="flex justify-between gap-2">
                                <span className="opacity-70">{k}</span>
                                <span className="font-semibold">
                                    {fmtNum(v, 2)}
                                </span>
                            </div>
                        ))}
                    </div>
                    <p className="mt-2 text-[11px] opacity-60">
                        Además: <code>cantidad_&lt;clave&gt;</code>,{' '}
                        <code>p_unit_&lt;clave&gt;</code>,{' '}
                        <code>importe_&lt;clave&gt;</code> de items con clave, y{' '}
                        <code>roundup(x, decimales)</code>.
                    </p>
                </div>
            </details>

            <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                {Object.entries(grupos).map(([value, label]) => {
                    const v = data.subtotales[value] ?? 0;
                    return (
                        <div
                            key={value}
                            className="card flex flex-row items-center justify-between bg-base-200 p-2 text-xs"
                        >
                            <span className="font-semibold">{label}</span>
                            <span
                                className={`font-bold ${v > 0 ? '' : 'opacity-30'}`}
                            >
                                {fmtMoney(v)}
                            </span>
                        </div>
                    );
                })}
            </div>

            <EditableGrid<FvItem>
                rowData={data.items}
                columnDefs={columnDefs}
                getRowId={(r) => String(r.id)}
                onCellEdited={guardar}
                height="55vh"
                paginated={false}
            />
        </div>
    );
}

/* ===================== Fletes Estándar ===================== */

function FletesEstandarPanel({
    obraId,
    rows,
    tarjetas,
    metodos,
}: {
    obraId: number;
    rows: FleteEstandarRow[];
    tarjetas: { id: number; descripcion: string }[];
    metodos: Record<string, string>;
}) {
    const [tarjetaSel, setTarjetaSel] = useState<number | ''>('');
    const [grupoNuevo, setGrupoNuevo] = useState<string>('');

    const guardar = (row: FleteEstandarRow, field?: string) => {
        const payload: Record<string, unknown> = {};
        if (field === 'grupo') payload.grupo = row.grupo ?? '';
        else if (field === 'metodo') payload.metodo = row.metodo;
        else if (field === 'orden') payload.orden = row.orden;
        else if (field === 'volumen_override')
            payload.volumen_override = row.volumen_override;
        else if (field === 'kg_por_camion')
            payload.kg_por_camion = row.kg_por_camion;
        else if (field === 'pzas_por_camion')
            payload.pzas_por_camion = row.pzas_por_camion;
        else if (field === 'ml_por_pza') payload.ml_por_pza = row.ml_por_pza;
        else return;
        router.put(`/admin/cotiz/obra-fletes-estandar/${row.id}`, payload, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const columnDefs = useMemo<ColDef<FleteEstandarRow>[]>(
        () => [
            { field: 'orden', headerName: '#', width: 70 },
            {
                field: 'grupo',
                headerName: 'Grupo',
                width: 150,
                editable: true,
                valueFormatter: (p) => p.value ?? '(sin grupo)',
            },
            {
                field: 'tarjeta_nombre',
                headerName: 'Tarjeta',
                flex: 2,
                minWidth: 200,
            },
            {
                field: 'metodo',
                headerName: 'Método',
                width: 140,
                editable: true,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: Object.keys(metodos) },
                valueFormatter: (p) => metodos[p.value] ?? p.value,
            },
            {
                field: 'volumen_override',
                headerName: 'Volumen',
                width: 150,
                editable: true,
                cellEditor: 'agNumberCellEditor',
                valueGetter: (p) => p.data?.volumen ?? 0,
                valueSetter: (p) => {
                    p.data.volumen_override =
                        p.newValue === '' || p.newValue == null
                            ? null
                            : Number(p.newValue);
                    return true;
                },
                cellClass: (p) =>
                    p.data?.volumen_override == null
                        ? 'italic opacity-70'
                        : 'font-semibold',
                valueFormatter: (p) => {
                    const u = p.data?.metodo === 'por_piezas' ? 'ml' : 'kg';
                    return `${fmtNum(Number(p.value), 2)} ${u}`;
                },
            },
            {
                field: 'kg_por_camion',
                headerName: 'kg/camión',
                width: 120,
                editable: (p) => p.data?.metodo === 'por_kg',
                cellEditor: 'agNumberCellEditor',
                valueFormatter: (p) =>
                    p.data?.metodo === 'por_kg' ? fmtNum(p.value, 0) : '—',
            },
            {
                field: 'ml_por_pza',
                headerName: 'ml/pza',
                width: 100,
                editable: (p) => p.data?.metodo === 'por_piezas',
                cellEditor: 'agNumberCellEditor',
                valueFormatter: (p) =>
                    p.data?.metodo === 'por_piezas' ? fmtNum(p.value, 2) : '—',
            },
            {
                field: 'pzas_por_camion',
                headerName: 'pzas/camión',
                width: 120,
                editable: (p) => p.data?.metodo === 'por_piezas',
                cellEditor: 'agNumberCellEditor',
                valueFormatter: (p) =>
                    p.data?.metodo === 'por_piezas' ? fmtNum(p.value, 0) : '—',
            },
            {
                colId: 'camiones',
                headerName: 'Camiones',
                width: 120,
                valueGetter: (p) => p.data?.camiones ?? 0,
                valueFormatter: (p) => fmtNum(p.value, 2),
                cellClass: 'font-bold',
            },
            {
                headerName: '',
                width: 64,
                sortable: false,
                filter: false,
                cellRenderer: ({
                    data,
                }: ICellRendererParams<FleteEstandarRow>) =>
                    data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            onClick={() => {
                                if (
                                    confirm(
                                        `¿Quitar tarjeta "${data.tarjeta_nombre}" del análisis?`,
                                    )
                                ) {
                                    router.delete(
                                        `/admin/cotiz/obra-fletes-estandar/${data.id}`,
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
        [metodos],
    );

    const camionesTotal = rows.reduce((s, r) => s + r.camiones, 0);

    const onAdd = () => {
        if (tarjetaSel === '') return;
        router.post(
            `/admin/cotiz/obras/${obraId}/fletes-estandar`,
            { tarjeta_id: tarjetaSel, grupo: grupoNuevo.trim() || null },
            { preserveScroll: true, onSuccess: () => setTarjetaSel('') },
        );
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <span className="badge badge-ghost">{rows.length} fletes</span>
                <span className="ml-auto badge badge-lg badge-primary">
                    {fmtNum(camionesTotal, 2)} camiones
                </span>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <select
                    className="select-bordered select max-w-xs flex-1 select-sm"
                    value={tarjetaSel}
                    onChange={(e) =>
                        setTarjetaSel(
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                >
                    <option value="">
                        {tarjetas.length === 0
                            ? 'Sin tarjetas disponibles'
                            : 'Selecciona tarjeta…'}
                    </option>
                    {tarjetas.map((t) => (
                        <option key={t.id} value={t.id}>
                            {t.descripcion}
                        </option>
                    ))}
                </select>
                <input
                    type="text"
                    className="input-bordered input input-sm max-w-[160px]"
                    placeholder="Grupo (opcional)"
                    value={grupoNuevo}
                    onChange={(e) => setGrupoNuevo(e.target.value)}
                />
                <Button
                    type="button"
                    variant="primary"
                    disabled={tarjetaSel === ''}
                    onClick={onAdd}
                >
                    + Tarjeta
                </Button>
            </div>
            {rows.length === 0 ? (
                <p className="py-8 text-center text-sm italic opacity-50">
                    Agrega tarjetas para calcular camiones.
                </p>
            ) : (
                <EditableGrid<FleteEstandarRow>
                    rowData={rows}
                    columnDefs={columnDefs}
                    getRowId={(r) => String(r.id)}
                    onCellEdited={guardar}
                    height="55vh"
                    paginated={false}
                />
            )}
        </div>
    );
}
