import { router } from '@inertiajs/react';
import { FileTextIcon, PlusIcon, XIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type {
    CostosRequisicion,
    CostosRequisicionCotizacionOpcion,
    CostosRequisicionCotizacionPrecio,
    CostosRequisicionDetalle,
    CostosTipoFiscalPartida,
    CostosTipoMoneda,
    Proveedor,
} from '@/types/models';
import { TIPO_MONEDA_LABELS } from '@/types/models';

type ProveedorMin = Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial'>;

const fmt = (n: number) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const etiquetaOpcion = (o: CostosRequisicionCotizacionOpcion) =>
    o.etiqueta || `Opción ${o.orden}`;

/**
 * Matriz de cotización: filas = partidas, columnas = OPCIONES de proveedor. Un
 * proveedor puede tener varias columnas-opción (ej. distintas marcas). Cada
 * celda captura descripción (opcional) + P. unitario + moneda. El encabezado
 * agrupa las opciones bajo su proveedor (col-span). Permite agregar/quitar
 * proveedores y opciones, editar la etiqueta de cada opción, y clasificar
 * fiscalmente cada partida.
 */
export function CotizacionMatriz({
    requisicion,
    proveedores,
    editable,
}: {
    requisicion: CostosRequisicion;
    proveedores: ProveedorMin[];
    editable: boolean;
}) {
    const detalles = useMemo(
        () => requisicion.detalles ?? [],
        [requisicion.detalles],
    );

    const proveedoresMap = useMemo(() => {
        const m = new Map<number, ProveedorMin>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    const opciones = useMemo(
        () => requisicion.cotizacion_opciones ?? [],
        [requisicion.cotizacion_opciones],
    );

    // Agrupa las opciones por proveedor (ordenado por nombre; opciones por orden).
    const grupos = useMemo(() => {
        const byProv = new Map<number, CostosRequisicionCotizacionOpcion[]>();
        opciones.forEach((o) => {
            const arr = byProv.get(o.proveedor_id) ?? [];
            arr.push(o);
            byProv.set(o.proveedor_id, arr);
        });
        const list = Array.from(byProv.entries()).map(([pid, ops]) => ({
            proveedorId: pid,
            nombre:
                proveedoresMap.get(pid)?.razon_social ??
                ops[0].proveedor?.razon_social ??
                `#${pid}`,
            opciones: [...ops].sort((a, b) => a.orden - b.orden),
        }));
        list.sort((a, b) => a.nombre.localeCompare(b.nombre));
        return list;
    }, [opciones, proveedoresMap]);

    // Columnas aplanadas en el mismo orden que el encabezado; cada una recuerda
    // cuántas opciones tiene su grupo (para permitir/impedir quitar la última).
    const columnas = useMemo(
        () =>
            grupos.flatMap((g) =>
                g.opciones.map((op) => ({ op, groupSize: g.opciones.length })),
            ),
        [grupos],
    );

    const [agregando, setAgregando] = useState(false);

    const disponiblesParaAgregar = proveedores.filter(
        (p) => !grupos.some((g) => g.proveedorId === p.id),
    );

    const agregarProveedor = (proveedorId: number) => {
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/opciones`,
            { proveedor_id: proveedorId },
            { preserveScroll: true },
        );
        setAgregando(false);
    };

    const agregarOpcion = (proveedorId: number) => {
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/opciones`,
            { proveedor_id: proveedorId },
            { preserveScroll: true },
        );
    };

    const quitarOpcion = (opcionId: number) => {
        if (
            !confirm(
                '¿Quitar esta opción? Se borrarán sus precios y selecciones.',
            )
        ) {
            return;
        }
        router.delete(`/admin/costos/requisiciones/opciones/${opcionId}`, {
            preserveScroll: true,
        });
    };

    const quitarProveedor = (proveedorId: number, nombre: string) => {
        if (
            !confirm(
                `¿Quitar a ${nombre} de la cotización? Se borrarán todas sus opciones, precios y selecciones.`,
            )
        ) {
            return;
        }
        router.delete(
            `/admin/costos/requisiciones/${requisicion.id}/proveedores/${proveedorId}`,
            { preserveScroll: true },
        );
    };

    const cotizacionDe = (
        d: CostosRequisicionDetalle,
        opcionId: number,
    ): CostosRequisicionCotizacionPrecio | undefined =>
        d.cotizaciones?.find((c) => c.opcion_id === opcionId);

    // Total por opción (PU × cantidad) para el renglón comparativo final.
    const totalesPorOpcion: Record<number, number> = {};
    columnas.forEach(({ op }) => (totalesPorOpcion[op.id] = 0));
    detalles.forEach((d) => {
        columnas.forEach(({ op }) => {
            const c = cotizacionDe(d, op.id);
            if (c)
                totalesPorOpcion[op.id] +=
                    Number(c.precio_unitario) * Number(d.cantidad);
        });
    });
    const totalMin = Math.min(
        ...Object.values(totalesPorOpcion).filter((v) => v > 0),
    );

    // Días de envío por opción: se toma el máximo de sus celdas.
    const diasPorOpcion = new Map<number, number | null>();
    columnas.forEach(({ op }) => {
        const valores = detalles
            .map((d) => cotizacionDe(d, op.id)?.tiempo_entrega_dias)
            .filter((v): v is number => v != null);
        diasPorOpcion.set(op.id, valores.length > 0 ? Math.max(...valores) : null);
    });

    return (
        <div className="space-y-3">
            <div className="rounded-lg border border-base-300 p-3">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-xs tracking-wider text-base-content/60 uppercase">
                        Cotización · partidas × opciones
                    </h3>
                    {editable &&
                        (agregando ? (
                            <div className="flex items-center gap-2">
                                <select
                                    className="select-bordered select w-56 select-xs"
                                    defaultValue=""
                                    onChange={(e) => {
                                        const id = Number(e.target.value);
                                        if (id) {
                                            agregarProveedor(id);
                                        }
                                    }}
                                >
                                    <option value="">
                                        Selecciona proveedor...
                                    </option>
                                    {disponiblesParaAgregar.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.razon_social}
                                        </option>
                                    ))}
                                </select>
                                <Button
                                    variant="outline"
                                    onClick={() => setAgregando(false)}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() => setAgregando(true)}
                                disabled={disponiblesParaAgregar.length === 0}
                            >
                                <PlusIcon className="size-3" /> Agregar
                                proveedor
                            </Button>
                        ))}
                </div>

                <div className="overflow-x-auto">
                    <table className="table table-xs [&_td]:align-top">
                        <thead>
                            <tr>
                                <th rowSpan={2} className="min-w-48">Partida</th>
                                <th rowSpan={2} className="min-w-28">Código</th>
                                <th rowSpan={2} className="text-right">Req.</th>
                                <th rowSpan={2} className="min-w-36">Tipo fiscal</th>
                                {grupos.map((g) => (
                                    <th
                                        key={g.proveedorId}
                                        colSpan={g.opciones.length}
                                        className="border-l border-base-300 text-center"
                                    >
                                        <div className="flex items-center justify-center gap-1">
                                            <span className="truncate" title={g.nombre}>
                                                {g.nombre}
                                            </span>
                                            {editable && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="btn px-1 btn-ghost btn-xs"
                                                        title="Agregar opción"
                                                        onClick={() => agregarOpcion(g.proveedorId)}
                                                    >
                                                        <PlusIcon className="size-3" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn px-1 text-error btn-ghost btn-xs"
                                                        title="Quitar proveedor"
                                                        onClick={() => quitarProveedor(g.proveedorId, g.nombre)}
                                                    >
                                                        <XIcon className="size-3" />
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </th>
                                ))}
                                {grupos.length === 0 && (
                                    <th rowSpan={2} className="text-base-content/40">
                                        Agrega un proveedor para cotizar
                                    </th>
                                )}
                            </tr>
                            <tr>
                                {columnas.map(({ op, groupSize }) => (
                                    <th key={op.id} className="border-l border-base-300 text-right">
                                        <EtiquetaOpcion
                                            opcion={op}
                                            puedeQuitar={editable && groupSize > 1}
                                            editable={editable}
                                            onQuitar={() => quitarOpcion(op.id)}
                                        />
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => {
                                const precios = columnas
                                    .map(({ op }) =>
                                        Number(cotizacionDe(d, op.id)?.precio_unitario ?? 0),
                                    )
                                    .filter((n) => n > 0);
                                const min = precios.length > 0 ? Math.min(...precios) : 0;
                                return (
                                    <tr key={d.id}>
                                        <ProductoCelda
                                            key={`${d.id}-${d.descripcion}-${d.codigo_producto ?? ''}`}
                                            detalle={d}
                                            editable={editable}
                                        />
                                        <td className="text-right">
                                            {Number(d.cantidad).toLocaleString('es-MX')}
                                        </td>
                                        <td>
                                            <TipoFiscalSelect detalle={d} editable={editable} />
                                        </td>
                                        {columnas.map(({ op }) => {
                                            const cot = cotizacionDe(d, op.id);
                                            return (
                                                <td key={op.id} className="border-l border-base-300 text-right">
                                                    <CeldaCotizacion
                                                        key={`${cot?.id ?? 'new'}-${cot?.precio_unitario ?? ''}-${cot?.moneda ?? ''}-${cot?.descripcion ?? ''}`}
                                                        requisicionDetalleId={d.id}
                                                        opcionId={op.id}
                                                        cotizacion={cot}
                                                        esMejor={
                                                            Number(cot?.precio_unitario ?? 0) > 0 &&
                                                            Number(cot?.precio_unitario) === min
                                                        }
                                                        editable={editable}
                                                    />
                                                </td>
                                            );
                                        })}
                                        {columnas.length === 0 && <td />}
                                    </tr>
                                );
                            })}
                            {columnas.length > 0 && (
                                <tr className="bg-base-200/50">
                                    <td className="font-semibold">Total si todo a uno</td>
                                    <td />
                                    <td />
                                    <td />
                                    {columnas.map(({ op }) => {
                                        const t = totalesPorOpcion[op.id];
                                        const isMin = t > 0 && t === totalMin;
                                        return (
                                            <td
                                                key={op.id}
                                                className={`border-l border-base-300 text-right font-semibold ${isMin ? 'bg-success/10 text-success' : ''}`}
                                            >
                                                {fmt(t)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            )}
                        </tbody>
                        {columnas.length > 0 && (
                            <tfoot>
                                <tr>
                                    <td colSpan={4} className="text-xs font-medium text-base-content/60">
                                        Días de envío
                                    </td>
                                    {columnas.map(({ op }) => (
                                        <td key={op.id} className="border-l border-base-300 text-right">
                                            <DiasEntregaCelda
                                                key={`${op.id}-${diasPorOpcion.get(op.id) ?? ''}`}
                                                requisicionId={requisicion.id}
                                                opcionId={op.id}
                                                dias={diasPorOpcion.get(op.id) ?? null}
                                                editable={editable}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            <DocumentosCotizacion
                requisicion={requisicion}
                editable={editable}
            />
        </div>
    );
}

/**
 * Encabezado de una columna-opción: etiqueta editable (onBlur) + quitar opción.
 */
function EtiquetaOpcion({
    opcion,
    puedeQuitar,
    editable,
    onQuitar,
}: {
    opcion: CostosRequisicionCotizacionOpcion;
    puedeQuitar: boolean;
    editable: boolean;
    onQuitar: () => void;
}) {
    const [valor, setValor] = useState(opcion.etiqueta ?? '');

    if (!editable) {
        return (
            <span className="text-xs font-medium">{etiquetaOpcion(opcion)}</span>
        );
    }

    const guardar = () => {
        if ((valor.trim() || null) === (opcion.etiqueta ?? null)) {
            return;
        }
        router.patch(
            `/admin/costos/requisiciones/opciones/${opcion.id}`,
            { etiqueta: valor.trim() || null },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <input
                type="text"
                className="input-bordered input input-xs w-24 text-right"
                value={valor}
                placeholder={`Opción ${opcion.orden}`}
                onChange={(e) => setValor(e.target.value)}
                onBlur={guardar}
                title="Etiqueta de la opción (ej. marca)"
            />
            {puedeQuitar && (
                <button
                    type="button"
                    className="btn px-1 text-error btn-ghost btn-xs"
                    title="Quitar opción"
                    onClick={onQuitar}
                >
                    <XIcon className="size-3" />
                </button>
            )}
        </div>
    );
}

function DocumentosCotizacion({
    requisicion,
    editable,
}: {
    requisicion: CostosRequisicion;
    editable: boolean;
}) {
    const [archivo, setArchivo] = useState<File | null>(null);
    const [titulo, setTitulo] = useState('');
    const [subiendo, setSubiendo] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const documentos = requisicion.media ?? [];

    const subir = () => {
        if (!archivo) {
            return;
        }
        setSubiendo(true);
        router.post(
            `/admin/costos/requisiciones/${requisicion.id}/documentos`,
            { documento: archivo, titulo },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    setArchivo(null);
                    setTitulo('');
                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
                onFinish: () => setSubiendo(false),
            },
        );
    };

    const eliminar = (id: number) => {
        if (confirm('¿Eliminar este documento?')) {
            router.delete(
                `/admin/costos/requisiciones/${requisicion.id}/documentos/${id}`,
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-3 text-xs tracking-wider text-base-content/60 uppercase">
                Documentos de cotización (PDF) · información extra
            </h3>

            {documentos.length === 0 ? (
                <p className="mb-3 text-sm text-base-content/50">
                    Aún no hay documentos adjuntos.
                </p>
            ) : (
                <div className="mb-3 space-y-2">
                    {documentos.map((doc) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-2 rounded border border-base-300 bg-base-200 p-2 text-sm"
                        >
                            <FileTextIcon className="size-4 text-base-content/60" />
                            <a
                                href={`/storage/${doc.path}`}
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium hover:underline"
                            >
                                {doc.descripcion || doc.nombre_original}
                            </a>
                            {editable && (
                                <button
                                    type="button"
                                    className="btn ml-auto text-error btn-ghost btn-xs"
                                    title="Eliminar"
                                    onClick={() => eliminar(doc.id)}
                                >
                                    <XIcon className="size-3" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {editable && (
                <div className="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        className="input-bordered input input-sm w-56"
                        placeholder="Título (opcional)"
                        value={titulo}
                        onChange={(e) => setTitulo(e.target.value)}
                    />
                    <input
                        ref={inputRef}
                        type="file"
                        accept="application/pdf"
                        className="file-input-bordered file-input w-64 file-input-sm"
                        onChange={(e) =>
                            setArchivo(e.target.files?.[0] ?? null)
                        }
                    />
                    <Button onClick={subir} disabled={!archivo || subiendo}>
                        <PlusIcon className="size-3" /> Agregar documento
                    </Button>
                </div>
            )}
        </div>
    );
}

function ProductoCelda({
    detalle,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    editable: boolean;
}) {
    const [descripcion, setDescripcion] = useState(detalle.descripcion);
    const [codigo, setCodigo] = useState(detalle.codigo_producto ?? '');

    // Sin producto del catálogo (partidas históricas) o no editable: solo lectura.
    if (!detalle.producto_id || !editable) {
        return (
            <>
                <td>
                    <div className="font-medium">{detalle.descripcion}</div>
                    <div className="text-[10px] text-base-content/50">
                        {detalle.unidad}
                    </div>
                </td>
                <td className="text-[10px] text-base-content/50">
                    {detalle.codigo_producto ?? '—'}
                </td>
            </>
        );
    }

    const guardar = () => {
        if (descripcion.trim() === '') return;
        if (
            descripcion === detalle.descripcion &&
            (codigo.trim() || null) === (detalle.codigo_producto ?? null)
        )
            return;
        router.patch(
            `/admin/costos/requisiciones/detalles/${detalle.id}/producto`,
            { descripcion: descripcion.trim(), codigo: codigo.trim() || null },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <td>
                <input
                    type="text"
                    className="input-bordered input input-xs w-full font-medium"
                    value={descripcion}
                    onChange={(e) => setDescripcion(e.target.value)}
                    onBlur={guardar}
                    title="Descripción del producto (catálogo)"
                />
                <div className="mt-1 text-[10px] text-base-content/50">
                    {detalle.unidad}
                </div>
            </td>
            <td>
                <input
                    type="text"
                    className="input-bordered input input-xs w-full"
                    value={codigo}
                    placeholder="Código"
                    onChange={(e) => setCodigo(e.target.value)}
                    onBlur={guardar}
                />
            </td>
        </>
    );
}

function DiasEntregaCelda({
    requisicionId,
    opcionId,
    dias,
    editable,
}: {
    requisicionId: number;
    opcionId: number;
    dias: number | null;
    editable: boolean;
}) {
    const [valor, setValor] = useState(dias != null ? String(dias) : '');

    if (!editable) {
        return (
            <span className="text-xs text-base-content/70">
                {dias != null ? `${dias} días` : '—'}
            </span>
        );
    }

    const guardar = () => {
        const n = valor.trim() === '' ? null : Number(valor);
        if (n === dias) return;
        router.post(
            `/admin/costos/requisiciones/${requisicionId}/cotizaciones/tiempo-entrega`,
            { opcion_id: opcionId, tiempo_entrega_dias: n },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <input
                type="number"
                min={0}
                className="input-bordered input input-xs w-14 text-right"
                value={valor}
                placeholder="—"
                onChange={(e) => setValor(e.target.value)}
                onBlur={guardar}
            />
            <span className="text-[10px] text-base-content/50">días</span>
        </div>
    );
}

function TipoFiscalSelect({
    detalle,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    editable: boolean;
}) {
    const [tipoFiscal, setTipoFiscal] = useState<CostosTipoFiscalPartida>(
        detalle.tipo_fiscal ?? 'mercancia',
    );

    return (
        <select
            className="select-bordered select w-full select-xs"
            value={tipoFiscal}
            disabled={!editable}
            onChange={(e) => {
                const v = e.target.value as CostosTipoFiscalPartida;
                setTipoFiscal(v);
                router.post(
                    `/admin/costos/requisiciones/detalles/${detalle.id}/clasificacion`,
                    { tipo_fiscal: v },
                    { preserveScroll: true },
                );
            }}
        >
            <option value="mercancia">Mercancía</option>
            <option value="flete">Flete</option>
            <option value="servicio_profesional">Servicio profesional</option>
            <option value="renta">Renta</option>
        </select>
    );
}

function CeldaCotizacion({
    requisicionDetalleId,
    opcionId,
    cotizacion,
    esMejor,
    editable,
}: {
    requisicionDetalleId: number;
    opcionId: number;
    cotizacion?: CostosRequisicionCotizacionPrecio;
    esMejor: boolean;
    editable: boolean;
}) {
    // El estado inicial viene de las props; el componente se remonta (via `key`
    // en el padre) cuando el server devuelve el registro persistido.
    const [precio, setPrecio] = useState(
        cotizacion ? String(cotizacion.precio_unitario) : '',
    );
    const [descripcion, setDescripcion] = useState(cotizacion?.descripcion ?? '');
    const [moneda, setMoneda] = useState<CostosTipoMoneda>(
        cotizacion?.moneda ?? 'mxn',
    );

    const guardar = (monedaOverride?: CostosTipoMoneda) => {
        const p = Number(precio);
        const m = monedaOverride ?? moneda;
        const desc = descripcion.trim() || null;

        // Precio vacío o 0: si existía cotización, eliminarla.
        if (!Number.isFinite(p) || p <= 0) {
            if (cotizacion) {
                router.delete(
                    `/admin/costos/requisiciones/cotizaciones/${cotizacion.id}`,
                    { preserveScroll: true },
                );
            }
            return;
        }

        // Sin cambios respecto a lo persistido.
        if (
            cotizacion &&
            p === Number(cotizacion.precio_unitario) &&
            m === (cotizacion.moneda ?? 'mxn') &&
            desc === (cotizacion.descripcion ?? null)
        ) {
            return;
        }

        router.post(
            '/admin/costos/requisiciones/cotizaciones',
            {
                requisicion_detalle_id: requisicionDetalleId,
                opcion_id: opcionId,
                precio_unitario: p,
                descripcion: desc,
                moneda: m,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col items-end gap-1">
            <input
                type="text"
                className="input-bordered input input-xs w-32"
                value={descripcion}
                disabled={!editable}
                placeholder="Descripción (opcional)"
                onChange={(e) => setDescripcion(e.target.value)}
                onBlur={() => guardar()}
                title="Ej. marca / modelo cotizado"
            />
            <div className="flex items-center justify-end gap-1">
                <input
                    type="number"
                    step="0.01"
                    min={0}
                    className={`input-bordered input input-xs w-20 text-right font-semibold ${esMejor ? 'border-success text-success' : ''}`}
                    value={precio}
                    disabled={!editable}
                    placeholder="—"
                    onChange={(e) => setPrecio(e.target.value)}
                    onBlur={() => guardar()}
                />
                <select
                    className="select-bordered select w-16 select-xs"
                    value={moneda}
                    disabled={!editable}
                    onChange={(e) => {
                        const m = e.target.value as CostosTipoMoneda;
                        setMoneda(m);
                        guardar(m);
                    }}
                    title={TIPO_MONEDA_LABELS[moneda]}
                >
                    <option value="mxn">MXN</option>
                    <option value="usd">USD</option>
                    <option value="eur">EUR</option>
                </select>
            </div>
        </div>
    );
}
