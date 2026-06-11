import { EditableGrid } from '@/components/cotiz/editable-grid';
import { FormulaCellEditor } from '@/components/cotiz/formula-cell-editor';
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

/** Fila unificada de la grilla: grupo de registros (mismo insumo), factor, fantasma o footer. */
type Row = {
    rowId: string;
    tipo: 'registro' | 'factor' | 'footer' | 'ghost';
    ids: number[]; // tarjeta_registros del grupo
    refId: number; // factor: tarjeta_factor id
    insumoId: number | null;
    todosManuales: boolean;
    esGeneradora: boolean;
    categoria: string | null;
    categoriaOrden: number;
    showCategoria: boolean;
    generadora: string | null;
    marca: string | null;
    descripcion: string;
    unidad: string | null;
    cantidad: number | null;
    precio: number | null;
    precioObra: number | null;
    formula: string | null;
    formulaGlobal: string | null;
    factorManual: boolean;
    importe: number;
    importeSugerido: number;
    area: number;
    tipoPintura: string | null;
    validado: boolean;
    numRegistros: number;
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

    // Agrupa registros por insumo (suma cantidad/kilos/importe/área, concatena marcas/gen).
    const rows = useMemo<Row[]>(() => {
        const grupos = new Map<string, CotizTarjetaRegistroResuelto[]>();
        for (const r of props.registros) {
            const key = r.insumo_id != null ? `ins-${r.insumo_id}` : `solo-${r.id}`;
            (grupos.get(key) ?? grupos.set(key, []).get(key)!).push(r);
        }
        const grupoRows: Row[] = [...grupos.values()].map((grp) => {
            const first = grp[0];
            const marcas = [...new Set(grp.map((r) => r.marca).filter(Boolean))];
            const gens = [
                ...new Set(grp.map((r) => r.generadora_titulo).filter(Boolean)),
            ];
            const tipos = new Set(grp.map((r) => r.tipo_pintura));
            return {
                rowId: `r-${grp.map((r) => r.id).join('-')}`,
                tipo: 'registro' as const,
                ids: grp.map((r) => r.id),
                refId: first.id,
                insumoId: first.insumo_id,
                todosManuales: grp.every((r) => r.es_manual),
                esGeneradora: grp.some((r) => !r.es_manual),
                categoria: first.categoria,
                categoriaOrden: first.categoria_orden,
                showCategoria: false,
                generadora: gens.length > 0 ? gens.join(', ') : null,
                marca: marcas.length > 0 ? marcas.join(', ') : null,
                descripcion: first.descripcion,
                unidad: first.unidad,
                cantidad: grp.reduce((s, r) => s + (r.cantidad ?? 0), 0),
                precio: Number(first.precio_unitario),
                precioObra: Number(first.precio_obra),
                formula: null,
                formulaGlobal: null,
                factorManual: false,
                importe: grp.reduce((s, r) => s + r.importe, 0),
                importeSugerido: grp.reduce((s, r) => s + r.importe_sugerido, 0),
                area: grp.reduce((s, r) => s + r.area_pintura, 0),
                tipoPintura: tipos.size === 1 ? first.tipo_pintura : 'auto',
                validado: grp.every((r) => r.validado),
                numRegistros: grp.length,
            };
        });
        grupoRows.sort(
            (a, b) =>
                a.categoriaOrden - b.categoriaOrden ||
                (a.categoria ?? '').localeCompare(b.categoria ?? '') ||
                a.descripcion.localeCompare(b.descripcion),
        );
        let prevCat: string | null = '__none__';
        for (const r of grupoRows) {
            const cat = r.categoria ?? '(sin clasificar)';
            r.showCategoria = cat !== prevCat;
            prevCat = cat;
        }
        const facRows: Row[] = props.factores.map((f, i) => ({
            rowId: `f-${f.id}`,
            tipo: 'factor',
            ids: [],
            refId: f.id,
            insumoId: null,
            todosManuales: false,
            esGeneradora: false,
            categoria: f.categoria,
            categoriaOrden: f.categoria_orden,
            showCategoria: i === 0,
            generadora: null,
            marca: null,
            descripcion: f.nombre || f.codigo,
            unidad: null,
            cantidad: f.cantidad,
            precio: Number(f.precio_unitario),
            precioObra: Number(f.precio_obra),
            formula: f.formula,
            formulaGlobal: f.formula_global,
            factorManual: f.formula == null,
            importe: f.importe,
            importeSugerido: f.importe_sugerido,
            area: 0,
            tipoPintura: null,
            validado: f.validado,
            numRegistros: 1,
        }));
        return [...grupoRows, ...facRows];
    }, [props.registros, props.factores]);

    const blank = (tipo: Row['tipo']): Row => ({
        rowId: tipo,
        tipo,
        ids: [],
        refId: 0,
        insumoId: null,
        todosManuales: false,
        esGeneradora: false,
        categoria: null,
        categoriaOrden: 99999,
        showCategoria: false,
        generadora: null,
        marca: null,
        descripcion: tipo === 'footer' ? 'TOTAL' : '',
        unidad: null,
        cantidad: tipo === 'footer' ? totales.kg_fab : null,
        precio: null,
        precioObra: null,
        formula: null,
        formulaGlobal: null,
        factorManual: false,
        importe: tipo === 'footer' ? totales.total_importe : 0,
        importeSugerido: 0,
        area: tipo === 'footer' ? totales.area_pintura : 0,
        tipoPintura: null,
        validado: false,
        numRegistros: 0,
    });

    const vinculados = new Set(props.factores.map((f) => f.factor_id));
    const factoresLibres = catalogos.factores.filter((f) => !vinculados.has(f.id));

    const numValidados = rows.filter((r) => r.validado).length;
    const desperdicio =
        totales.kg_reales_total > 0
            ? (totales.kg_fab - totales.kg_reales_total) / totales.kg_reales_total
            : 0;

    // ===== Handlers =====
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

    const guardar = async (row: Row, field?: string) => {
        if (row.tipo === 'footer' || row.tipo === 'ghost') {
            return;
        }
        if (field === 'precio') {
            if (row.insumoId == null) {
                return;
            }
            // Si el P.U. vuelve a igualar el de obra, limpiamos el override de tarjeta.
            const v =
                row.precio != null && row.precio === row.precioObra
                    ? null
                    : row.precio;
            router.put(
                `/admin/cotiz/tarjetas/${tarjeta.id}/precios/${row.insumoId}`,
                { precio_unitario: v },
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
                    `/admin/cotiz/tarjetas/${tarjeta.id}/registros-grupo`,
                    { ids: row.ids, cantidad: row.cantidad },
                    reloadOpts,
                );
            }
            return;
        }
        if (field === 'importe' && row.tipo === 'factor') {
            router.put(
                `/admin/cotiz/tarjeta-factores/${row.refId}`,
                { importe: row.importe },
                reloadOpts,
            );
            return;
        }
        if (field === 'formula' && row.tipo === 'factor') {
            const formula = row.formula ?? '';
            if (formula.trim() !== '') {
                const { data } = await axios.post(
                    '/admin/cotiz/tarjetas/validar-formula',
                    { formula },
                );
                if (data.error) {
                    alert(`Fórmula inválida: ${data.error}`);
                    router.reload({ only: ONLY });
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
                `/admin/cotiz/tarjetas/${tarjeta.id}/registros-grupo`,
                { ids: row.ids, validado: valor },
                reloadOpts,
            );
        }
    };

    const setPintura = (row: Row, clave: string) =>
        router.put(
            `/admin/cotiz/tarjetas/${tarjeta.id}/registros-grupo`,
            { ids: row.ids, tipo_pintura: clave },
            reloadOpts,
        );

    const eliminar = (row: Row) => {
        if (row.tipo === 'factor') {
            if (confirm('¿Quitar este factor?')) {
                router.delete(
                    `/admin/cotiz/tarjeta-factores/${row.refId}`,
                    reloadOpts,
                );
            }
            return;
        }
        if (confirm('¿Eliminar este registro?')) {
            router.delete(`/admin/cotiz/tarjetas/${tarjeta.id}/registros-grupo`, {
                ...reloadOpts,
                data: { ids: row.ids },
            });
        }
    };

    const columnDefs = useMemo<ColDef<Row>[]>(
        () => [
            {
                headerName: 'Cat.',
                width: 120,
                sortable: false,
                cellRenderer: (p: ICellRendererParams<Row>) =>
                    p.data &&
                    p.data.tipo !== 'footer' &&
                    p.data.tipo !== 'ghost' &&
                    p.data.showCategoria ? (
                        <strong className="text-xs uppercase opacity-70">
                            {p.data.categoria ?? '(sin clasificar)'}
                        </strong>
                    ) : null,
            },
            {
                headerName: 'Generadora',
                width: 140,
                cellClass: 'text-xs',
                valueGetter: (p) =>
                    !p.data || p.data.tipo === 'footer' || p.data.tipo === 'ghost'
                        ? ''
                        : p.data.tipo === 'factor'
                          ? '(factor)'
                          : (p.data.generadora ?? '(manual)'),
                cellStyle: (p) =>
                    p.data?.tipo === 'registro' && p.data.generadora == null
                        ? { fontStyle: 'italic', opacity: 0.5 }
                        : null,
            },
            {
                headerName: 'Insumo / Factor',
                field: 'descripcion',
                flex: 2,
                minWidth: 230,
                sortable: false,
                cellStyle: (p) =>
                    p.data?.tipo === 'footer' ? { fontWeight: 'bold' } : null,
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (p.data?.tipo !== 'ghost') {
                        const marca =
                            p.data?.tipo === 'registro' && p.data.marca
                                ? ` · ${p.data.marca}`
                                : '';
                        return (
                            <span>
                                {p.data?.descripcion}
                                {marca && (
                                    <span className="opacity-50">{marca}</span>
                                )}
                            </span>
                        );
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
                            <option value="">+ Añadir insumo o factor…</option>
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
                width: 64,
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
                width: 80,
                valueFormatter: (p) =>
                    p.data?.tipo === 'footer' || p.data?.tipo === 'ghost'
                        ? ''
                        : (p.value ?? ''),
            },
            {
                headerName: 'Cantidad',
                field: 'cantidad',
                width: 120,
                type: 'numericColumn',
                editable: (p) =>
                    !readOnly &&
                    ((p.data?.tipo === 'registro' && p.data.todosManuales) ||
                        (p.data?.tipo === 'factor' && p.data.factorManual)),
                cellEditor: 'agNumberCellEditor',
                cellClass: (p) =>
                    p.data?.tipo === 'factor' && !p.data.factorManual
                        ? 'italic opacity-70'
                        : '',
                valueFormatter: (p) =>
                    p.data?.tipo === 'ghost' ? '' : fmtNum(p.value, 4),
            },
            {
                headerName: 'P.U.',
                field: 'precio',
                width: 130,
                type: 'numericColumn',
                editable: (p) =>
                    !readOnly &&
                    ((p.data?.tipo === 'registro' && p.data.insumoId != null) ||
                        p.data?.tipo === 'factor'),
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (
                        !p.data ||
                        p.data.tipo === 'footer' ||
                        p.data.tipo === 'ghost'
                    ) {
                        return null;
                    }
                    if (p.data.precio == null) {
                        return <span className="opacity-40 italic">—</span>;
                    }
                    const override =
                        p.data.precio !== p.data.precioObra ||
                        (p.data.insumoId != null &&
                            props.preciosOverride[String(p.data.insumoId)] != null);
                    return (
                        <div className="flex items-center justify-between gap-1">
                            <span className={override ? 'font-bold' : 'opacity-80'}>
                                {fmtMoney.format(Number(p.data.precio))}
                            </span>
                            <span
                                className={override ? 'text-success' : 'opacity-30'}
                                title={
                                    override
                                        ? `Override de tarjeta. P.U. de obra: ${fmtMoney.format(Number(p.data.precioObra ?? 0))}.`
                                        : 'Click para fijar un P.U. solo para esta tarjeta.'
                                }
                            >
                                ✎
                            </span>
                        </div>
                    );
                },
            },
            {
                headerName: 'Imp. sug.',
                field: 'importeSugerido',
                width: 120,
                type: 'numericColumn',
                cellClass: 'opacity-60',
                valueFormatter: (p) =>
                    p.data?.tipo === 'footer' || p.data?.tipo === 'ghost'
                        ? ''
                        : fmtMoney.format(Number(p.value ?? 0)),
            },
            {
                headerName: 'Fórmula',
                field: 'formula',
                width: 230,
                editable: (p) => !readOnly && p.data?.tipo === 'factor',
                cellEditor: FormulaCellEditor,
                cellEditorPopup: true,
                cellRenderer: (p: ICellRendererParams<Row>) => {
                    if (p.data?.tipo !== 'factor') {
                        return null;
                    }
                    const override =
                        p.data.formula != null &&
                        p.data.formula !== p.data.formulaGlobal;
                    return (
                        <div className="flex items-center justify-between gap-1">
                            <span
                                className={`truncate font-mono text-xs ${override ? 'font-bold' : 'opacity-80'}`}
                            >
                                {p.data.formula ?? '(manual)'}
                            </span>
                            <span
                                className={override ? 'text-success' : 'opacity-30'}
                                title="Click para editar la fórmula (override por tarjeta)"
                            >
                                ✎
                            </span>
                        </div>
                    );
                },
            },
            {
                headerName: 'Importe',
                field: 'importe',
                width: 160,
                type: 'numericColumn',
                editable: (p) => !readOnly && p.data?.tipo === 'factor',
                cellEditor: 'agNumberCellEditor',
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
                width: 76,
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
                headerName: 'Área m²',
                field: 'area',
                width: 100,
                type: 'numericColumn',
                cellClass: 'opacity-70',
                valueFormatter: (p) =>
                    p.data?.tipo === 'registro' && Number(p.value) > 0
                        ? fmtNum(p.value)
                        : '',
            },
            {
                headerName: 'Fórmula pintura',
                width: 150,
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
                width: 50,
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

    const pinnedBottom = readOnly
        ? [blank('footer')]
        : [blank('ghost'), blank('footer')];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${tarjeta.descripcion}`} />

            <div className="flex h-[calc(100vh-3.5rem)] flex-col gap-3 p-4">
                {/* Barra de título + badges + acciones */}
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
                        className="input input-sm input-bordered w-64 text-lg font-semibold"
                    />
                    <span
                        className="badge badge-lg badge-info"
                        title="Σ del Análisis de kilos reales (TIRAS+RAZ+KG+CNX)."
                    >
                        Kg reales: {fmtNum(totales.kg_reales_total)} kg
                    </span>
                    <span
                        className="badge badge-lg badge-warning"
                        title="(kg fab − kg reales) / kg reales"
                    >
                        Desp.: {fmtPct(desperdicio)}
                    </span>
                    <span className="badge badge-lg badge-accent">
                        Área: {fmtNum(totales.area_pintura)} m²
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
                    {!readOnly && (
                        <>
                            <button
                                type="button"
                                className="btn btn-outline btn-sm"
                                onClick={() =>
                                    router.post(
                                        `/admin/cotiz/tarjetas/${tarjeta.id}/aplicar-sugerido`,
                                        {},
                                        reloadOpts,
                                    )
                                }
                            >
                                Aplicar sugerido
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline btn-sm"
                                onClick={() =>
                                    router.post(
                                        `/admin/cotiz/tarjetas/${tarjeta.id}/validar-todas`,
                                        {},
                                        reloadOpts,
                                    )
                                }
                            >
                                Validar todas
                            </button>
                        </>
                    )}
                </div>

                <LockBanner state={lockState} fallback={lock} />

                <GeneradorasRow
                    tarjeta={tarjeta}
                    generadorasDisponibles={props.generadorasDisponibles}
                    readOnly={readOnly}
                />

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
    const vincular = (id: number) =>
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras`,
            { generadora_id: id },
            { preserveScroll: true },
        );
    const resincronizar = (id: number) =>
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras/${id}/resincronizar`,
            {},
            { preserveScroll: true },
        );
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
                        <>
                            <button
                                type="button"
                                className="ml-1 hover:text-warning"
                                title="Resincronizar (importar líneas nuevas, quitar las sin material)"
                                onClick={() => resincronizar(g.id)}
                            >
                                ↻
                            </button>
                            <button
                                type="button"
                                className="hover:text-error"
                                title="Desvincular"
                                onClick={() => desvincular(g)}
                            >
                                ✕
                            </button>
                        </>
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
            <button type="button" className="modal-backdrop" onClick={onClose}>
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
                La está editando {nombre}. {desde ? `Inició ${desde}. ` : ''}
                Solo lectura hasta que termine o expire su sesión.
            </span>
        </div>
    );
}
