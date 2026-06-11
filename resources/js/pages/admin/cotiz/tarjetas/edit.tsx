import { EditableGrid } from '@/components/cotiz/editable-grid';
import { useCotizEditLock } from '@/hooks/use-cotiz-edit-lock';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    CotizGeneradora,
    CotizObra,
    CotizTarjetaCategoriaKilos,
    CotizTarjetaEstructura,
    CotizTarjetaFactorResuelto,
    CotizTarjetaKilosCelda,
    CotizTarjetaRegistroResuelto,
} from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { LockIcon, Loader2Icon } from 'lucide-react';
import { useMemo, useState } from 'react';

type TarjetaProp = {
    id: number;
    obra_id: number;
    descripcion: string;
    orden: number;
    obra: CotizObra;
    generadoras: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    registros_count: number;
};

type Totales = {
    total_importe: number;
    total_registros: number;
    total_factores: number;
    kg_fab: number;
    area_pintura: number;
    kg_reales_total: number;
};

type Catalogos = {
    insumos: { id: number; descripcion: string; precio_unitario: string }[];
    factores: {
        id: number;
        codigo: string;
        nombre: string;
        formula: string | null;
    }[];
    krCategorias: { id: number; descripcion: string; tipo_corte: string }[];
    tiposPintura: Record<string, string>;
};

type Props = {
    tarjeta: TarjetaProp;
    registros: CotizTarjetaRegistroResuelto[];
    factores: CotizTarjetaFactorResuelto[];
    estructuras: CotizTarjetaEstructura[];
    categoriasKilos: CotizTarjetaCategoriaKilos[];
    celdas: CotizTarjetaKilosCelda[];
    preciosOverride: Record<string, string>;
    totales: Totales;
    catalogos: Catalogos;
    generadorasDisponibles: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    lock: {
        is_locked: boolean;
        locked_by: { id: string; name: string } | null;
        locked_at: string | null;
    };
};

/** Fila unificada de la grilla: registro de insumo o factor; fila fantasma (alta) y footer. */
type Row = {
    rowId: string;
    tipo: 'registro' | 'factor' | 'footer' | 'ghost';
    refId: number;
    insumoId: number | null;
    esManual: boolean;
    esGeneradora: boolean;
    categoria: string | null;
    categoriaOrden: number;
    showCategoria: boolean;
    generadora: string | null;
    descripcion: string;
    unidad: string | null;
    cantidad: number | null;
    precio: number | null;
    formula: string | null;
    factorManual: boolean;
    importe: number;
    tipoPintura: string | null;
    validado: boolean;
};

const ONLY = [
    'registros',
    'factores',
    'estructuras',
    'categoriasKilos',
    'celdas',
    'preciosOverride',
    'totales',
    'tarjeta',
];

const reloadOpts = { preserveScroll: true, preserveState: true, only: ONLY };

const fmtMoney = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtNum = (n: number | null | undefined, d = 2) =>
    n == null ? '' : Number(n).toFixed(d);
const fmtPct = (n: number) => `${(n * 100).toFixed(1)}%`;

export default function TarjetaEdit(props: Props) {
    const { tarjeta, totales, catalogos, lock } = props;
    const lockState = useCotizEditLock('tarjeta', tarjeta.id);
    const readOnly = lockState.status !== 'owned';
    const [mostrarKr, setMostrarKr] = useState(false);

    const obra = tarjeta.obra;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        { title: 'Tarjetas', href: `/admin/cotiz/obras/${obra.id}/tarjetas` },
        {
            title: tarjeta.descripcion,
            href: `/admin/cotiz/tarjetas/${tarjeta.id}/edit`,
        },
    ];

    // Filas unificadas: registros (ordenados por categoría) seguidos de los factores.
    const rows = useMemo<Row[]>(() => {
        const regs = [...props.registros].sort(
            (a, b) =>
                a.categoria_orden - b.categoria_orden ||
                a.descripcion.localeCompare(b.descripcion),
        );
        let prevCat: string | null = '__none__';
        const regRows: Row[] = regs.map((r) => {
            const cat = r.categoria ?? '(sin clasificar)';
            const show = cat !== prevCat;
            prevCat = cat;
            return {
                rowId: `r-${r.id}`,
                tipo: 'registro',
                refId: r.id,
                insumoId: r.insumo_id,
                esManual: r.es_manual,
                esGeneradora: !r.es_manual,
                categoria: r.categoria,
                categoriaOrden: r.categoria_orden,
                showCategoria: show,
                generadora: r.generadora_titulo,
                descripcion: r.descripcion,
                unidad: r.unidad,
                cantidad: r.cantidad,
                precio: r.precio_unitario,
                formula: null,
                factorManual: false,
                importe: r.importe,
                tipoPintura: r.tipo_pintura,
                validado: r.validado,
            };
        });
        const facRows: Row[] = props.factores.map((f, i) => ({
            rowId: `f-${f.id}`,
            tipo: 'factor',
            refId: f.id,
            insumoId: null,
            esManual: false,
            esGeneradora: false,
            categoria: f.categoria,
            categoriaOrden: f.categoria_orden,
            showCategoria: i === 0,
            generadora: null,
            descripcion: f.nombre || f.codigo,
            unidad: null,
            cantidad: f.cantidad,
            precio: f.precio_unitario,
            formula: f.formula,
            factorManual: f.formula == null,
            importe: f.importe,
            tipoPintura: null,
            validado: f.validado,
        }));
        return [...regRows, ...facRows];
    }, [props.registros, props.factores]);

    const footerRow = useMemo<Row[]>(
        () => [
            {
                rowId: 'footer',
                tipo: 'footer',
                refId: 0,
                insumoId: null,
                esManual: false,
                esGeneradora: false,
                categoria: null,
                categoriaOrden: 99999,
                showCategoria: false,
                generadora: null,
                descripcion: 'TOTAL',
                unidad: null,
                cantidad: totales.kg_fab,
                precio: null,
                formula: null,
                factorManual: false,
                importe: totales.total_importe,
                tipoPintura: null,
                validado: false,
            },
        ],
        [totales],
    );

    // Fila fantasma de alta (inline, al pie de la tabla): elegir insumo o factor lo agrega.
    const vinculados = new Set(props.factores.map((f) => f.factor_id));
    const factoresLibres = catalogos.factores.filter(
        (f) => !vinculados.has(f.id),
    );
    const ghostRow = useMemo<Row>(
        () => ({
            rowId: 'ghost',
            tipo: 'ghost',
            refId: 0,
            insumoId: null,
            esManual: false,
            esGeneradora: false,
            categoria: null,
            categoriaOrden: 99999,
            showCategoria: false,
            generadora: null,
            descripcion: '',
            unidad: null,
            cantidad: null,
            precio: null,
            formula: null,
            factorManual: false,
            importe: 0,
            tipoPintura: null,
            validado: false,
        }),
        [],
    );

    const agregarInsumo = (insumoId: number) =>
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/registros-manual`,
            { insumo_id: insumoId, cantidad: null },
            reloadOpts,
        );
    const vincularFactor = (factorId: number) =>
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/factores`,
            { factor_id: factorId },
            reloadOpts,
        );

    const numValidados = rows.filter((r) => r.validado).length;

    const guardar = async (row: Row, field?: string) => {
        if (row.tipo === 'footer') {
            return;
        }
        if (field === 'precio') {
            if (row.insumoId == null) {
                return;
            }
            router.put(
                `/admin/cotiz/tarjetas/${tarjeta.id}/precios/${row.insumoId}`,
                { precio_unitario: row.precio },
                reloadOpts,
            );
            return;
        }
        if (field === 'cantidad') {
            if (row.tipo === 'factor') {
                router.put(
                    `/admin/cotiz/tarjeta-factores/${row.refId}`,
                    { cantidad_manual: row.cantidad },
                    reloadOpts,
                );
            } else {
                router.put(
                    `/admin/cotiz/tarjeta-registros/${row.refId}`,
                    { cantidad: row.cantidad },
                    reloadOpts,
                );
            }
            return;
        }
        if (field === 'formula') {
            const formula = row.formula ?? '';
            if (formula.trim() !== '') {
                const { data } = await axios.post(
                    '/admin/cotiz/tarjetas/validar-formula',
                    { formula },
                );
                if (data.error) {
                    alert(`Fórmula inválida: ${data.error}`);
                    return;
                }
            }
            router.put(
                `/admin/cotiz/tarjeta-factores/${row.refId}`,
                { formula_override: row.formula },
                reloadOpts,
            );
        }
    };

    const setValidado = (row: Row, valor: boolean) => {
        if (row.tipo === 'factor') {
            router.put(
                `/admin/cotiz/tarjeta-factores/${row.refId}`,
                { validado: valor },
                reloadOpts,
            );
        } else {
            router.put(
                `/admin/cotiz/tarjeta-registros/${row.refId}`,
                { validado: valor },
                reloadOpts,
            );
        }
    };

    const setPintura = (row: Row, clave: string) => {
        router.put(
            `/admin/cotiz/tarjeta-registros/${row.refId}`,
            { tipo_pintura: clave },
            reloadOpts,
        );
    };

    const eliminar = (row: Row) => {
        const url =
            row.tipo === 'factor'
                ? `/admin/cotiz/tarjeta-factores/${row.refId}`
                : `/admin/cotiz/tarjeta-registros/${row.refId}`;
        const msg =
            row.tipo === 'factor'
                ? '¿Quitar este factor?'
                : '¿Eliminar este registro?';
        if (confirm(msg)) {
            router.delete(url, reloadOpts);
        }
    };

    const columnDefs = useMemo<ColDef<Row>[]>(
        () => [
            {
                headerName: 'Cat.',
                width: 130,
                sortable: false,
                cellRenderer: (p: ICellRendererParams<Row>) =>
                    p.data && p.data.tipo !== 'footer' && p.data.showCategoria ? (
                        <strong className="text-xs uppercase opacity-70">
                            {p.data.categoria ?? '(sin clasificar)'}
                        </strong>
                    ) : null,
            },
            {
                headerName: 'Generadora',
                width: 150,
                cellClass: 'text-xs',
                valueGetter: (p) =>
                    p.data?.tipo === 'footer' || p.data?.tipo === 'ghost'
                        ? ''
                        : p.data?.tipo === 'factor'
                          ? '(factor)'
                          : (p.data?.generadora ?? '(manual)'),
                cellStyle: (p) =>
                    p.data &&
                    p.data.tipo === 'registro' &&
                    p.data.generadora == null
                        ? { fontStyle: 'italic', opacity: 0.5 }
                        : null,
            },
            {
                headerName: 'Insumo / Factor',
                field: 'descripcion',
                flex: 2,
                minWidth: 240,
                sortable: false,
                cellStyle: (p) =>
                    p.data?.tipo === 'footer' ? { fontWeight: 'bold' } : null,
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (p.data?.tipo !== 'ghost') {
                        return p.data?.descripcion ?? '';
                    }
                    return (
                        <select
                            className="select select-xs select-bordered h-6 min-h-0 w-full text-xs"
                            value=""
                            onChange={(e) => {
                                const v = e.target.value;
                                if (!v) {
                                    return;
                                }
                                const id = Number(v.slice(2));
                                if (v.startsWith('f:')) {
                                    vincularFactor(id);
                                } else {
                                    agregarInsumo(id);
                                }
                            }}
                        >
                            <option value="">
                                + Añadir insumo o factor…
                            </option>
                            {factoresLibres.length > 0 && (
                                <optgroup label="Factores">
                                    {factoresLibres.map((f) => (
                                        <option key={`f-${f.id}`} value={`f:${f.id}`}>
                                            [factor] {f.codigo} — {f.nombre}
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                            <optgroup label="Insumos">
                                {catalogos.insumos.map((i) => (
                                    <option key={`i-${i.id}`} value={`i:${i.id}`}>
                                        {i.descripcion}
                                    </option>
                                ))}
                            </optgroup>
                        </select>
                    );
                },
            },
            {
                headerName: '✓',
                width: 70,
                sortable: false,
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (
                        !p.data ||
                        p.data.tipo === 'footer' ||
                        p.data.tipo === 'ghost'
                    ) {
                        return null;
                    }
                    if (p.data.esGeneradora) {
                        return p.data.validado ? (
                            <span
                                className="font-semibold text-success"
                                title="Validado en la generadora"
                            >
                                ✔
                            </span>
                        ) : (
                            <span
                                className="opacity-30"
                                title="Pendiente: palomear en la generadora"
                            >
                                ○
                            </span>
                        );
                    }
                    return (
                        <input
                            type="checkbox"
                            className="checkbox checkbox-xs checkbox-primary"
                            checked={p.data.validado}
                            disabled={readOnly}
                            onChange={(e) => setValidado(p.data!, e.target.checked)}
                        />
                    );
                },
            },
            {
                headerName: 'Unidad',
                field: 'unidad',
                width: 90,
                valueFormatter: (p) =>
                    p.data?.tipo === 'footer' ? '' : (p.value ?? ''),
            },
            {
                headerName: 'Cantidad',
                field: 'cantidad',
                width: 120,
                type: 'numericColumn',
                editable: (p) =>
                    !readOnly &&
                    ((p.data?.tipo === 'registro' && p.data.esManual) ||
                        (p.data?.tipo === 'factor' && p.data.factorManual)),
                cellEditor: 'agNumberCellEditor',
                cellClass: (p) =>
                    p.data?.tipo === 'factor' && !p.data.factorManual
                        ? 'italic opacity-70'
                        : '',
                valueFormatter: (p) => fmtNum(p.value, 4),
            },
            {
                headerName: 'P.U.',
                field: 'precio',
                width: 140,
                type: 'numericColumn',
                editable: (p) =>
                    !readOnly &&
                    p.data?.tipo === 'registro' &&
                    p.data.insumoId != null,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                valueFormatter: (p) =>
                    p.data?.tipo === 'footer' || p.value == null
                        ? ''
                        : fmtMoney.format(Number(p.value)),
                cellClass: (p) =>
                    p.data?.insumoId != null &&
                    props.preciosOverride[String(p.data.insumoId)] != null
                        ? 'font-semibold text-warning'
                        : '',
            },
            {
                headerName: 'Fórmula',
                field: 'formula',
                width: 240,
                editable: (p) => !readOnly && p.data?.tipo === 'factor',
                cellClass: 'font-mono text-xs',
                valueFormatter: (p) =>
                    p.data?.tipo === 'factor'
                        ? p.value || '(manual)'
                        : '',
            },
            {
                headerName: 'Importe',
                field: 'importe',
                width: 170,
                type: 'numericColumn',
                cellClass: 'font-semibold',
                valueFormatter: (p) => {
                    if (p.value == null || p.data?.tipo === 'ghost') {
                        return '';
                    }
                    if (p.data?.tipo === 'footer') {
                        const costoKg =
                            totales.kg_reales_total > 0
                                ? Number(p.value) / totales.kg_reales_total
                                : 0;
                        return `${fmtMoney.format(Number(p.value))}  (${fmtMoney.format(costoKg)}/kg)`;
                    }
                    return fmtMoney.format(Number(p.value));
                },
            },
            {
                headerName: '%',
                width: 80,
                type: 'numericColumn',
                sortable: false,
                cellClass: 'opacity-70',
                valueGetter: (p) =>
                    p.data &&
                    (p.data.tipo === 'registro' || p.data.tipo === 'factor') &&
                    totales.total_importe > 0
                        ? p.data.importe / totales.total_importe
                        : null,
                valueFormatter: (p) =>
                    p.value == null ? '' : fmtPct(Number(p.value)),
            },
            {
                headerName: 'Fórmula pintura',
                width: 160,
                sortable: false,
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (!p.data || p.data.tipo !== 'registro') {
                        return null;
                    }
                    return (
                        <select
                            className="select select-xs select-bordered h-6 min-h-0 w-full text-xs"
                            value={p.data.tipoPintura ?? 'auto'}
                            disabled={readOnly}
                            onChange={(e) => setPintura(p.data!, e.target.value)}
                        >
                            {Object.entries(catalogos.tiposPintura).map(
                                ([clave, label]) => (
                                    <option key={clave} value={clave}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                    );
                },
            },
            {
                headerName: '',
                width: 56,
                sortable: false,
                filter: false,
                cellRenderer: (p: ICellRendererParams<Row>) =>
                    p.data &&
                    (p.data.tipo === 'registro' || p.data.tipo === 'factor') ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            disabled={readOnly}
                            title="Eliminar"
                            onClick={() => eliminar(p.data!)}
                        >
                            ✕
                        </button>
                    ) : null,
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [
            readOnly,
            props.preciosOverride,
            totales,
            catalogos.tiposPintura,
            catalogos.insumos,
            factoresLibres,
        ],
    );

    const pinnedBottom = readOnly ? footerRow : [ghostRow, ...footerRow];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${tarjeta.descripcion}`} />

            <div className="flex h-[calc(100vh-3.5rem)] flex-col gap-3 p-4">
                {/* Barra de título + badges */}
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={`/admin/cotiz/obras/${obra.id}/tarjetas`}
                        className="text-sm link link-primary"
                    >
                        ← Tarjetas
                    </Link>
                    <input
                        defaultValue={tarjeta.descripcion}
                        disabled={readOnly}
                        onBlur={(e) => {
                            const v = e.target.value.trim();
                            if (v && v !== tarjeta.descripcion) {
                                router.put(
                                    `/admin/cotiz/tarjetas/${tarjeta.id}`,
                                    { descripcion: v, orden: tarjeta.orden },
                                    { preserveScroll: true },
                                );
                            }
                        }}
                        className="input input-sm input-bordered w-72 text-lg font-semibold"
                    />
                    <span
                        className="badge badge-lg badge-info"
                        title="Suma del Análisis de kilos reales (TIRAS + RAZ + KG + CNX)."
                    >
                        Kg reales: {fmtNum(totales.kg_reales_total)} kg
                    </span>
                    <span
                        className="badge badge-lg badge-accent"
                        title="Área pintable total — alimenta area_pintura."
                    >
                        Área pintura: {fmtNum(totales.area_pintura)} m²
                    </span>
                    <span className="badge badge-ghost">
                        {props.registros.length} registros
                    </span>
                    <span className="text-xs opacity-60">
                        Validados: {numValidados}/{rows.length}
                    </span>
                    <div className="flex-1" />
                    <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => setMostrarKr(true)}
                    >
                        📊 Análisis kg reales
                    </button>
                </div>

                <LockBanner state={lockState} fallback={lock} />

                {/* Generadoras vinculadas */}
                <GeneradorasRow
                    tarjeta={tarjeta}
                    generadorasDisponibles={props.generadorasDisponibles}
                    readOnly={readOnly}
                />

                {/* Grilla unificada */}
                <div className="min-h-0 flex-1">
                    <EditableGrid<Row>
                        rowData={rows}
                        columnDefs={columnDefs}
                        getRowId={(row) => row.rowId}
                        onCellEdited={readOnly ? undefined : guardar}
                        pinnedBottomRowData={pinnedBottom}
                        paginated={false}
                        height="100%"
                        getRowStyle={(p) => {
                            if (p.data?.tipo === 'footer') {
                                return { fontWeight: 'bold' };
                            }
                            if (p.data?.tipo === 'ghost') {
                                return { fontStyle: 'italic', opacity: 0.85 };
                            }
                            return undefined;
                        }}
                    />
                </div>
            </div>

            {mostrarKr && (
                <KilosRealesModal
                    tarjeta={tarjeta}
                    estructuras={props.estructuras}
                    categoriasKilos={props.categoriasKilos}
                    celdas={props.celdas}
                    catalogos={catalogos}
                    readOnly={readOnly}
                    onClose={() => setMostrarKr(false)}
                />
            )}
        </AppLayout>
    );
}

// ===== Generadoras vinculadas =====

function GeneradorasRow({
    tarjeta,
    generadorasDisponibles,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    generadorasDisponibles: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    readOnly: boolean;
}) {
    const vincular = (id: number) => {
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras`,
            { generadora_id: id },
            { preserveScroll: true },
        );
    };
    const desvincular = (g: Pick<CotizGeneradora, 'id' | 'titulo'>) => {
        if (
            confirm(
                `¿Desvincular "${g.titulo}"? Se quitarán sus registros importados.`,
            )
        ) {
            router.delete(
                `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras/${g.id}`,
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-2 text-xs">
            <span className="opacity-60">Generadoras vinculadas:</span>
            {tarjeta.generadoras.length === 0 && (
                <span className="italic opacity-50">
                    ninguna — solo registros manuales
                </span>
            )}
            {tarjeta.generadoras.map((g) => (
                <span key={g.id} className="badge gap-1 badge-primary">
                    {g.titulo}
                    {!readOnly && (
                        <button
                            type="button"
                            className="ml-1 hover:text-error"
                            title="Desvincular"
                            onClick={() => desvincular(g)}
                        >
                            ✕
                        </button>
                    )}
                </span>
            ))}
            {!readOnly && generadorasDisponibles.length > 0 && (
                <details className="dropdown">
                    <summary className="btn btn-outline btn-xs">
                        + Vincular generadora
                    </summary>
                    <ul className="dropdown-content menu z-10 w-72 rounded-box bg-base-200 shadow">
                        {generadorasDisponibles.map((g) => (
                            <li key={g.id}>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        (
                                            e.currentTarget.closest(
                                                'details',
                                            ) as HTMLDetailsElement
                                        ).open = false;
                                        vincular(g.id);
                                    }}
                                >
                                    {g.titulo}
                                </button>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </div>
    );
}

// ===== Modal Análisis de kilos reales =====

function KilosRealesModal({
    tarjeta,
    estructuras,
    categoriasKilos,
    celdas,
    catalogos,
    readOnly,
    onClose,
}: {
    tarjeta: TarjetaProp;
    estructuras: CotizTarjetaEstructura[];
    categoriasKilos: CotizTarjetaCategoriaKilos[];
    celdas: CotizTarjetaKilosCelda[];
    catalogos: Catalogos;
    readOnly: boolean;
    onClose: () => void;
}) {
    const [estructura, setEstructura] = useState('');
    const [categoriaId, setCategoriaId] = useState<number | ''>('');
    const [porcentual, setPorcentual] = useState('');

    const celdaPorClave = useMemo(() => {
        const m = new Map<string, CotizTarjetaKilosCelda>();
        for (const c of celdas) {
            m.set(`${c.categoria_id}:${c.estructura_id}`, c);
        }
        return m;
    }, [celdas]);

    const setCelda = (categoria_id: number, estructura_id: number, kilos: string) =>
        router.put(
            `/admin/cotiz/tarjetas/${tarjeta.id}/kr-celdas`,
            { categoria_id, estructura_id, kilos: kilos === '' ? 0 : kilos },
            reloadOpts,
        );

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-5xl">
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold">
                        Análisis de kilos reales
                    </h3>
                    <button
                        type="button"
                        className="btn btn-ghost btn-sm"
                        onClick={onClose}
                    >
                        ✕
                    </button>
                </div>

                {categoriasKilos.length === 0 || estructuras.length === 0 ? (
                    <p className="text-sm text-base-content/60">
                        Agrega al menos una estructura (columna) y una categoría
                        (fila) para capturar kilos.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-box border border-base-300">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Corte</th>
                                    {estructuras.map((est) => (
                                        <th key={est.id} className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {est.nombre}
                                                {!readOnly && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Quitar estructura"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/admin/cotiz/tarjeta-estructuras/${est.id}`,
                                                                reloadOpts,
                                                            )
                                                        }
                                                    >
                                                        ✕
                                                    </button>
                                                )}
                                            </div>
                                        </th>
                                    ))}
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {categoriasKilos.map((cat) => {
                                    const esPct = cat.porcentual != null;
                                    return (
                                        <tr key={cat.id}>
                                            <td>{cat.descripcion}</td>
                                            <td>
                                                <span className="badge badge-ghost badge-sm">
                                                    {cat.tipo_corte}
                                                </span>
                                            </td>
                                            {estructuras.map((est) => (
                                                <td
                                                    key={est.id}
                                                    className="text-right"
                                                >
                                                    {esPct ? (
                                                        <span className="text-xs opacity-40">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            className="input input-xs input-bordered w-24 text-right"
                                                            defaultValue={
                                                                celdaPorClave.get(
                                                                    `${cat.categoria_id}:${est.id}`,
                                                                )?.kilos ?? ''
                                                            }
                                                            disabled={readOnly}
                                                            onBlur={(e) =>
                                                                setCelda(
                                                                    cat.categoria_id,
                                                                    est.id,
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            ))}
                                            <td className="whitespace-nowrap">
                                                {esPct && (
                                                    <span className="badge mr-1 badge-info badge-sm">
                                                        {(
                                                            Number(
                                                                cat.porcentual,
                                                            ) * 100
                                                        ).toFixed(1)}
                                                        %
                                                    </span>
                                                )}
                                                {!readOnly && (
                                                    <button
                                                        type="button"
                                                        className="btn text-error btn-ghost btn-xs"
                                                        title="Quitar categoría"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/admin/cotiz/tarjeta-kr-categorias/${cat.id}`,
                                                                reloadOpts,
                                                            )
                                                        }
                                                    >
                                                        ✕
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {!readOnly && (
                    <div className="mt-3 flex flex-wrap gap-3">
                        <div className="flex items-end gap-2">
                            <input
                                className="input input-sm input-bordered w-40"
                                placeholder="Nueva estructura"
                                value={estructura}
                                onChange={(e) => setEstructura(e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn btn-sm btn-secondary"
                                disabled={estructura.trim() === ''}
                                onClick={() =>
                                    router.post(
                                        `/admin/cotiz/tarjetas/${tarjeta.id}/estructuras`,
                                        {
                                            nombre: estructura,
                                            orden: estructuras.length,
                                        },
                                        {
                                            ...reloadOpts,
                                            onSuccess: () => setEstructura(''),
                                        },
                                    )
                                }
                            >
                                + Columna
                            </button>
                        </div>
                        <div className="flex items-end gap-2">
                            <select
                                className="select select-sm select-bordered w-52"
                                value={categoriaId}
                                onChange={(e) =>
                                    setCategoriaId(
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            >
                                <option value="">Categoría…</option>
                                {catalogos.krCategorias.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.descripcion} ({c.tipo_corte})
                                    </option>
                                ))}
                            </select>
                            <input
                                type="number"
                                step="any"
                                className="input input-sm input-bordered w-24"
                                placeholder="% (opc.)"
                                value={porcentual}
                                onChange={(e) => setPorcentual(e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn btn-sm btn-secondary"
                                disabled={categoriaId === ''}
                                onClick={() =>
                                    router.post(
                                        `/admin/cotiz/tarjetas/${tarjeta.id}/kr-categorias`,
                                        {
                                            categoria_id: categoriaId,
                                            porcentual:
                                                porcentual === ''
                                                    ? null
                                                    : porcentual,
                                            orden: categoriasKilos.length,
                                        },
                                        {
                                            ...reloadOpts,
                                            onSuccess: () => {
                                                setCategoriaId('');
                                                setPorcentual('');
                                            },
                                        },
                                    )
                                }
                            >
                                + Fila
                            </button>
                        </div>
                    </div>
                )}
            </div>
            <button
                type="button"
                className="modal-backdrop"
                onClick={onClose}
            >
                cerrar
            </button>
        </dialog>
    );
}

// ===== Lock =====

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

function LockBanner({
    state,
    fallback,
}: {
    state: ReturnType<typeof useCotizEditLock>;
    fallback: Props['lock'];
}) {
    if (state.status === 'taking') {
        return (
            <div className="alert py-2">
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
            <div className="alert alert-error py-2">
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
        <div className="alert alert-warning py-2">
            <LockIcon className="size-5" />
            <span className="text-sm">
                La está editando {nombre}.{' '}
                {desde ? `Inició ${desde}. ` : ''}
                Solo lectura hasta que termine o expire su sesión.
            </span>
        </div>
    );
}
