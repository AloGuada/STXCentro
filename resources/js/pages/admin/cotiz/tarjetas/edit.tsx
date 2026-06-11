import { EditableGrid } from '@/components/cotiz/editable-grid';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import type {
    ColDef,
    ICellRendererParams,
    ValueSetterParams,
} from 'ag-grid-community';
import { LockIcon, Loader2Icon, Trash2Icon, UnlinkIcon } from 'lucide-react';
import type { FormEvent } from 'react';
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

const fmtMoney = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtNum = (n: number | null | undefined, d = 2) =>
    n == null ? '' : Number(n).toFixed(d);

export default function TarjetaEdit(props: Props) {
    const { tarjeta, lock } = props;
    const lockState = useCotizEditLock('tarjeta', tarjeta.id);
    const readOnly = lockState.status !== 'owned';

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${tarjeta.descripcion}`} />

            <div className="space-y-5 p-6">
                <Cabecera tarjeta={tarjeta} readOnly={readOnly} />
                <LockBanner state={lockState} fallback={lock} />
                <TotalesPanel totales={props.totales} />

                <RegistrosSection
                    tarjeta={tarjeta}
                    registros={props.registros}
                    preciosOverride={props.preciosOverride}
                    catalogos={props.catalogos}
                    readOnly={readOnly}
                />

                <FactoresSection
                    tarjeta={tarjeta}
                    factores={props.factores}
                    catalogos={props.catalogos}
                    readOnly={readOnly}
                />

                <KilosRealesSection
                    tarjeta={tarjeta}
                    estructuras={props.estructuras}
                    categoriasKilos={props.categoriasKilos}
                    celdas={props.celdas}
                    catalogos={props.catalogos}
                    readOnly={readOnly}
                />

                <GeneradorasSection
                    tarjeta={tarjeta}
                    generadorasDisponibles={props.generadorasDisponibles}
                    readOnly={readOnly}
                />

                <div className="flex justify-end">
                    <ButtonLink
                        variant="ghost"
                        href={`/admin/cotiz/obras/${obra.id}/tarjetas`}
                    >
                        ← Volver a tarjetas
                    </ButtonLink>
                </div>
            </div>
        </AppLayout>
    );
}

// ===== Cabecera =====

function Cabecera({
    tarjeta,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    readOnly: boolean;
}) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: tarjeta.descripcion,
        orden: String(tarjeta.orden),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/tarjetas/${tarjeta.id}`, { preserveScroll: true });
    };

    return (
        <div>
            <h1 className="text-2xl font-semibold">{tarjeta.descripcion}</h1>
            <p className="mb-3 text-sm text-base-content/60">
                Obra: {tarjeta.obra.nombre} · {tarjeta.registros_count} registros
            </p>
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div className="min-w-64 flex-1">
                    <label className="label" htmlFor="descripcion">
                        <span className="label-text">Descripción</span>
                    </label>
                    <Input
                        id="descripcion"
                        value={data.descripcion}
                        disabled={readOnly}
                        onChange={(e) => setData('descripcion', e.target.value)}
                    />
                    {errors.descripcion && (
                        <p className="mt-1 text-xs text-error">
                            {errors.descripcion}
                        </p>
                    )}
                </div>
                <div className="w-24">
                    <label className="label" htmlFor="orden">
                        <span className="label-text">Orden</span>
                    </label>
                    <Input
                        id="orden"
                        type="number"
                        value={data.orden}
                        disabled={readOnly}
                        onChange={(e) => setData('orden', e.target.value)}
                    />
                </div>
                <Button
                    type="submit"
                    variant="primary"
                    disabled={readOnly || processing}
                >
                    Guardar
                </Button>
            </form>
        </div>
    );
}

// ===== Totales =====

function TotalesPanel({ totales }: { totales: Totales }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <TotalCard
                label="Total importe"
                value={fmtMoney.format(totales.total_importe)}
                accent
            />
            <TotalCard
                label="Materiales"
                value={fmtMoney.format(totales.total_registros)}
            />
            <TotalCard
                label="Factores"
                value={fmtMoney.format(totales.total_factores)}
            />
            <TotalCard label="Kg fab." value={fmtNum(totales.kg_fab)} />
            <TotalCard
                label="Área pintura (m²)"
                value={fmtNum(totales.area_pintura)}
            />
            <TotalCard label="Kg reales" value={fmtNum(totales.kg_reales_total)} />
        </div>
    );
}

function TotalCard({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div
            className={`rounded-box border p-3 ${accent ? 'border-primary/40 bg-primary/5' : 'border-base-300'}`}
        >
            <p className="text-xs text-base-content/60">{label}</p>
            <p className={`mt-1 font-semibold ${accent ? 'text-primary' : ''}`}>
                {value}
            </p>
        </div>
    );
}

// ===== Registros =====

function RegistrosSection({
    tarjeta,
    registros,
    preciosOverride,
    catalogos,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    registros: CotizTarjetaRegistroResuelto[];
    preciosOverride: Record<string, string>;
    catalogos: Catalogos;
    readOnly: boolean;
}) {
    const [insumoId, setInsumoId] = useState<number | ''>('');
    const [cantidad, setCantidad] = useState('');

    const tipoLabels = Object.values(catalogos.tiposPintura);
    const tipoPorLabel = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(catalogos.tiposPintura).map(([k, v]) => [v, k]),
            ),
        [catalogos.tiposPintura],
    );

    const guardar = (row: CotizTarjetaRegistroResuelto, field?: string) => {
        if (field === 'precio_unitario') {
            if (row.insumo_id == null) {
                return;
            }
            router.put(
                `/admin/cotiz/tarjetas/${tarjeta.id}/precios/${row.insumo_id}`,
                { precio_unitario: row.precio_unitario },
                { preserveScroll: true, preserveState: true, only: ONLY },
            );
            return;
        }
        router.put(
            `/admin/cotiz/tarjeta-registros/${row.id}`,
            {
                cantidad: row.cantidad,
                tipo_pintura: row.tipo_pintura,
                validado: row.validado,
            },
            { preserveScroll: true, preserveState: true, only: ONLY },
        );
    };

    const columnDefs = useMemo<ColDef<CotizTarjetaRegistroResuelto>[]>(
        () => [
            {
                field: 'categoria',
                headerName: 'Categoría',
                minWidth: 150,
                valueGetter: (p) => p.data?.categoria ?? '(sin clasificar)',
            },
            {
                field: 'descripcion',
                headerName: 'Insumo',
                minWidth: 220,
                pinned: 'left',
                cellRenderer: (
                    p: ICellRendererParams<CotizTarjetaRegistroResuelto>,
                ) =>
                    p.data ? (
                        <span>
                            {p.data.descripcion}
                            {!p.data.es_manual && (
                                <span className="badge badge-ghost badge-xs ml-2">
                                    gen
                                </span>
                            )}
                        </span>
                    ) : null,
            },
            { field: 'unidad', headerName: 'Unidad', minWidth: 90 },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                minWidth: 120,
                type: 'numericColumn',
                editable: (p) => !readOnly && !!p.data?.es_manual,
                cellEditor: 'agNumberCellEditor',
                valueFormatter: (p) => fmtNum(p.value, 4),
            },
            {
                field: 'precio_unitario',
                headerName: 'P.U.',
                minWidth: 130,
                type: 'numericColumn',
                editable: (p) => !readOnly && p.data?.insumo_id != null,
                cellEditor: 'agNumberCellEditor',
                cellEditorParams: { precision: 4, min: 0 },
                valueFormatter: (p) => fmtMoney.format(Number(p.value ?? 0)),
                cellClass: (p) =>
                    p.data?.insumo_id != null &&
                    preciosOverride[String(p.data.insumo_id)] != null
                        ? 'font-semibold text-warning'
                        : '',
            },
            {
                field: 'importe',
                headerName: 'Importe',
                minWidth: 130,
                type: 'numericColumn',
                valueFormatter: (p) => fmtMoney.format(Number(p.value ?? 0)),
                cellClass: 'font-medium',
            },
            {
                headerName: 'Pintura',
                minWidth: 170,
                editable: !readOnly,
                cellEditor: 'agSelectCellEditor',
                cellEditorParams: { values: tipoLabels },
                valueGetter: (p) =>
                    catalogos.tiposPintura[p.data?.tipo_pintura ?? 'auto'] ??
                    p.data?.tipo_pintura,
                valueSetter: (
                    p: ValueSetterParams<CotizTarjetaRegistroResuelto>,
                ) => {
                    const clave = tipoPorLabel[p.newValue];
                    if (!clave) {
                        return false;
                    }
                    p.data.tipo_pintura = clave;
                    return true;
                },
            },
            {
                field: 'validado',
                headerName: '✓',
                minWidth: 80,
                editable: !readOnly,
                cellEditor: 'agCheckboxCellEditor',
                cellRenderer: 'agCheckboxCellRenderer',
            },
            {
                headerName: '',
                width: 60,
                sortable: false,
                filter: false,
                cellRenderer: (
                    p: ICellRendererParams<CotizTarjetaRegistroResuelto>,
                ) =>
                    p.data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            disabled={readOnly}
                            title="Eliminar registro"
                            onClick={() => {
                                if (confirm('¿Eliminar este registro?')) {
                                    router.delete(
                                        `/admin/cotiz/tarjeta-registros/${p.data!.id}`,
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            only: ONLY,
                                        },
                                    );
                                }
                            }}
                        >
                            <Trash2Icon className="size-4" />
                        </button>
                    ) : null,
            },
        ],
        [readOnly, preciosOverride, catalogos.tiposPintura, tipoLabels, tipoPorLabel],
    );

    const sorted = useMemo(
        () =>
            [...registros].sort(
                (a, b) =>
                    a.categoria_orden - b.categoria_orden ||
                    a.descripcion.localeCompare(b.descripcion),
            ),
        [registros],
    );

    const agregarManual = (e: FormEvent) => {
        e.preventDefault();
        if (insumoId === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/registros-manual`,
            { insumo_id: insumoId, cantidad: cantidad === '' ? null : cantidad },
            {
                preserveScroll: true,
                preserveState: true,
                only: ONLY,
                onSuccess: () => {
                    setInsumoId('');
                    setCantidad('');
                },
            },
        );
    };

    return (
        <section className="space-y-2">
            <h2 className="font-medium">Registros (insumos)</h2>
            <EditableGrid<CotizTarjetaRegistroResuelto>
                rowData={sorted}
                columnDefs={columnDefs}
                getRowId={(row) => String(row.id)}
                onCellEdited={readOnly ? undefined : guardar}
                height="46vh"
            />
            {!readOnly && (
                <form
                    onSubmit={agregarManual}
                    className="flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3"
                >
                    <div className="min-w-64 flex-1">
                        <label className="label py-0">
                            <span className="label-text text-xs">
                                Agregar insumo manual
                            </span>
                        </label>
                        <select
                            className="select w-full select-sm select-bordered"
                            value={insumoId}
                            onChange={(e) =>
                                setInsumoId(
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">Selecciona insumo…</option>
                            {catalogos.insumos.map((i) => (
                                <option key={i.id} value={i.id}>
                                    {i.descripcion}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="w-32">
                        <label className="label py-0">
                            <span className="label-text text-xs">Cantidad</span>
                        </label>
                        <Input
                            className="input-sm"
                            type="number"
                            step="any"
                            value={cantidad}
                            onChange={(e) => setCantidad(e.target.value)}
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        className="btn-sm"
                        disabled={insumoId === ''}
                    >
                        Agregar
                    </Button>
                </form>
            )}
        </section>
    );
}

// ===== Factores =====

function FactoresSection({
    tarjeta,
    factores,
    catalogos,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    factores: CotizTarjetaFactorResuelto[];
    catalogos: Catalogos;
    readOnly: boolean;
}) {
    const [factorId, setFactorId] = useState<number | ''>('');

    const guardar = async (
        row: CotizTarjetaFactorResuelto,
        field?: string,
    ) => {
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
                `/admin/cotiz/tarjeta-factores/${row.id}`,
                { formula_override: row.formula },
                { preserveScroll: true, preserveState: true, only: ONLY },
            );
            return;
        }
        router.put(
            `/admin/cotiz/tarjeta-factores/${row.id}`,
            { validado: row.validado },
            { preserveScroll: true, preserveState: true, only: ONLY },
        );
    };

    const columnDefs = useMemo<ColDef<CotizTarjetaFactorResuelto>[]>(
        () => [
            {
                field: 'codigo',
                headerName: 'Código',
                minWidth: 150,
                pinned: 'left',
                cellClass: 'font-mono text-xs',
            },
            { field: 'nombre', headerName: 'Nombre', minWidth: 180 },
            {
                field: 'formula',
                headerName: 'Fórmula',
                minWidth: 260,
                editable: !readOnly,
                valueFormatter: (p) => p.value || '(manual)',
                cellClass: 'font-mono text-xs',
            },
            {
                field: 'cantidad',
                headerName: 'Cantidad',
                minWidth: 120,
                type: 'numericColumn',
                valueFormatter: (p) => fmtNum(p.value, 4),
            },
            {
                field: 'precio_unitario',
                headerName: 'P.U.',
                minWidth: 120,
                type: 'numericColumn',
                valueFormatter: (p) => fmtMoney.format(Number(p.value ?? 0)),
            },
            {
                field: 'importe',
                headerName: 'Importe',
                minWidth: 130,
                type: 'numericColumn',
                valueFormatter: (p) => fmtMoney.format(Number(p.value ?? 0)),
                cellClass: 'font-medium',
            },
            {
                field: 'validado',
                headerName: '✓',
                minWidth: 80,
                editable: !readOnly,
                cellEditor: 'agCheckboxCellEditor',
                cellRenderer: 'agCheckboxCellRenderer',
            },
            {
                headerName: '',
                width: 60,
                sortable: false,
                filter: false,
                cellRenderer: (
                    p: ICellRendererParams<CotizTarjetaFactorResuelto>,
                ) =>
                    p.data ? (
                        <button
                            type="button"
                            className="btn text-error btn-ghost btn-xs"
                            disabled={readOnly}
                            title="Quitar factor"
                            onClick={() => {
                                if (confirm('¿Quitar este factor?')) {
                                    router.delete(
                                        `/admin/cotiz/tarjeta-factores/${p.data!.id}`,
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            only: ONLY,
                                        },
                                    );
                                }
                            }}
                        >
                            <Trash2Icon className="size-4" />
                        </button>
                    ) : null,
            },
        ],
        [readOnly],
    );

    const vinculados = new Set(factores.map((f) => f.factor_id));
    const disponibles = catalogos.factores.filter((f) => !vinculados.has(f.id));

    const vincular = (e: FormEvent) => {
        e.preventDefault();
        if (factorId === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/factores`,
            { factor_id: factorId },
            {
                preserveScroll: true,
                preserveState: true,
                only: ONLY,
                onSuccess: () => setFactorId(''),
            },
        );
    };

    return (
        <section className="space-y-2">
            <h2 className="font-medium">Factores</h2>
            <EditableGrid<CotizTarjetaFactorResuelto>
                rowData={factores}
                columnDefs={columnDefs}
                getRowId={(row) => String(row.id)}
                onCellEdited={readOnly ? undefined : guardar}
                height="36vh"
            />
            {!readOnly && disponibles.length > 0 && (
                <form
                    onSubmit={vincular}
                    className="flex items-end gap-2 rounded-box border border-base-300 p-3"
                >
                    <div className="min-w-64 flex-1">
                        <label className="label py-0">
                            <span className="label-text text-xs">
                                Vincular factor
                            </span>
                        </label>
                        <select
                            className="select w-full select-sm select-bordered"
                            value={factorId}
                            onChange={(e) =>
                                setFactorId(
                                    e.target.value === ''
                                        ? ''
                                        : Number(e.target.value),
                                )
                            }
                        >
                            <option value="">Selecciona factor…</option>
                            {disponibles.map((f) => (
                                <option key={f.id} value={f.id}>
                                    {f.codigo} — {f.nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        className="btn-sm"
                        disabled={factorId === ''}
                    >
                        Vincular
                    </Button>
                </form>
            )}
        </section>
    );
}

// ===== Kilos reales (matriz) =====

function KilosRealesSection({
    tarjeta,
    estructuras,
    categoriasKilos,
    celdas,
    catalogos,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    estructuras: CotizTarjetaEstructura[];
    categoriasKilos: CotizTarjetaCategoriaKilos[];
    celdas: CotizTarjetaKilosCelda[];
    catalogos: Catalogos;
    readOnly: boolean;
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

    const setCelda = (
        categoria_id: number,
        estructura_id: number,
        kilos: string,
    ) => {
        router.put(
            `/admin/cotiz/tarjetas/${tarjeta.id}/kr-celdas`,
            { categoria_id, estructura_id, kilos: kilos === '' ? 0 : kilos },
            { preserveScroll: true, preserveState: true, only: ONLY },
        );
    };

    const agregarEstructura = (e: FormEvent) => {
        e.preventDefault();
        if (estructura.trim() === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/estructuras`,
            { nombre: estructura, orden: estructuras.length },
            {
                preserveScroll: true,
                preserveState: true,
                only: ONLY,
                onSuccess: () => setEstructura(''),
            },
        );
    };

    const agregarCategoria = (e: FormEvent) => {
        e.preventDefault();
        if (categoriaId === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/kr-categorias`,
            {
                categoria_id: categoriaId,
                porcentual: porcentual === '' ? null : porcentual,
                orden: categoriasKilos.length,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ONLY,
                onSuccess: () => {
                    setCategoriaId('');
                    setPorcentual('');
                },
            },
        );
    };

    return (
        <section className="space-y-2">
            <h2 className="font-medium">Análisis de kilos reales</h2>

            {categoriasKilos.length === 0 || estructuras.length === 0 ? (
                <p className="text-sm text-base-content/60">
                    Agrega al menos una estructura (columna) y una categoría (fila)
                    para capturar kilos.
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
                                                            {
                                                                preserveScroll: true,
                                                                preserveState: true,
                                                                only: ONLY,
                                                            },
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
                                            <td key={est.id} className="text-right">
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
                                                <span className="badge badge-info badge-sm mr-1">
                                                    {(
                                                        Number(cat.porcentual) *
                                                        100
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
                                                            {
                                                                preserveScroll: true,
                                                                preserveState: true,
                                                                only: ONLY,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2Icon className="size-3.5" />
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
                <div className="flex flex-wrap gap-4">
                    <form
                        onSubmit={agregarEstructura}
                        className="flex items-end gap-2 rounded-box border border-base-300 p-3"
                    >
                        <div className="w-48">
                            <label className="label py-0">
                                <span className="label-text text-xs">
                                    Nueva estructura (columna)
                                </span>
                            </label>
                            <Input
                                className="input-sm"
                                value={estructura}
                                placeholder="Ej. NAVE"
                                onChange={(e) => setEstructura(e.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            className="btn-sm"
                        >
                            + Columna
                        </Button>
                    </form>

                    <form
                        onSubmit={agregarCategoria}
                        className="flex items-end gap-2 rounded-box border border-base-300 p-3"
                    >
                        <div className="w-56">
                            <label className="label py-0">
                                <span className="label-text text-xs">
                                    Nueva categoría (fila)
                                </span>
                            </label>
                            <select
                                className="select w-full select-sm select-bordered"
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
                        </div>
                        <div className="w-28">
                            <label className="label py-0">
                                <span className="label-text text-xs">
                                    % (opcional)
                                </span>
                            </label>
                            <Input
                                className="input-sm"
                                type="number"
                                step="any"
                                placeholder="0.22"
                                value={porcentual}
                                onChange={(e) => setPorcentual(e.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            className="btn-sm"
                            disabled={categoriaId === ''}
                        >
                            + Fila
                        </Button>
                    </form>
                </div>
            )}
        </section>
    );
}

// ===== Generadoras =====

function GeneradorasSection({
    tarjeta,
    generadorasDisponibles,
    readOnly,
}: {
    tarjeta: TarjetaProp;
    generadorasDisponibles: Pick<CotizGeneradora, 'id' | 'titulo' | 'orden'>[];
    readOnly: boolean;
}) {
    const [generadoraId, setGeneradoraId] = useState<number | ''>('');

    const vincular = () => {
        if (generadoraId === '') {
            return;
        }
        router.post(
            `/admin/cotiz/tarjetas/${tarjeta.id}/generadoras`,
            { generadora_id: generadoraId },
            { preserveScroll: true, onSuccess: () => setGeneradoraId('') },
        );
    };

    return (
        <section className="rounded-box border border-base-300 p-4">
            <h2 className="mb-3 font-medium">Generadoras vinculadas</h2>
            {tarjeta.generadoras.length === 0 ? (
                <p className="text-sm text-base-content/60">
                    Sin generadoras vinculadas.
                </p>
            ) : (
                <ul className="divide-y divide-base-200">
                    {tarjeta.generadoras.map((g) => (
                        <li
                            key={g.id}
                            className="flex items-center justify-between py-2"
                        >
                            <span>{g.titulo}</span>
                            <button
                                type="button"
                                className="btn text-error btn-ghost btn-xs"
                                disabled={readOnly}
                                onClick={() => {
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
                                }}
                            >
                                <UnlinkIcon className="size-4" />
                                Desvincular
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {!readOnly && generadorasDisponibles.length > 0 && (
                <div className="mt-3 flex items-end gap-2">
                    <select
                        className="select select-sm select-bordered"
                        value={generadoraId}
                        onChange={(e) =>
                            setGeneradoraId(
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                    >
                        <option value="">Vincular generadora…</option>
                        {generadorasDisponibles.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.titulo}
                            </option>
                        ))}
                    </select>
                    <Button
                        type="button"
                        variant="secondary"
                        className="btn-sm"
                        disabled={generadoraId === ''}
                        onClick={vincular}
                    >
                        Vincular
                    </Button>
                </div>
            )}
        </section>
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
                    Puede ver la tarjeta pero no guardar cambios hasta que termine
                    o su sesión expire.
                </p>
            </div>
        </div>
    );
}
