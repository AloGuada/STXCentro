import { router } from '@inertiajs/react';
import { FileTextIcon, PlusIcon, XIcon } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type {
    CostosRequisicion,
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

/**
 * Matriz de cotización estricta: filas = partidas, columnas = proveedores.
 * Cada celda captura únicamente P. unitario + moneda (se persiste en blur sin
 * pisar código/días/observaciones, que se editan en el tab de OC). Permite
 * agregar/quitar columnas de proveedor y clasificar fiscalmente cada partida.
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

    // Columnas persistidas: proveedores con al menos una cotización capturada.
    const persistedIds = useMemo(() => {
        const s = new Set<number>();
        detalles.forEach((d) =>
            d.cotizaciones?.forEach((c) => s.add(c.proveedor_id)),
        );
        return Array.from(s);
    }, [detalles]);

    // Columnas agregadas localmente que todavía no tienen ningún precio.
    const [extraIds, setExtraIds] = useState<number[]>([]);

    const columnIds = useMemo(() => {
        const all = Array.from(new Set([...persistedIds, ...extraIds]));
        return all.sort((a, b) =>
            (proveedoresMap.get(a)?.razon_social ?? '').localeCompare(
                proveedoresMap.get(b)?.razon_social ?? '',
            ),
        );
    }, [persistedIds, extraIds, proveedoresMap]);

    const [agregando, setAgregando] = useState(false);

    const disponiblesParaAgregar = proveedores.filter(
        (p) => !columnIds.includes(p.id),
    );

    const quitarProveedor = (proveedorId: number) => {
        // Columna solo local (sin precios persistidos): basta quitarla del estado.
        if (!persistedIds.includes(proveedorId)) {
            setExtraIds((prev) => prev.filter((id) => id !== proveedorId));
            return;
        }
        const nombre =
            proveedoresMap.get(proveedorId)?.razon_social ?? 'este proveedor';
        if (
            !confirm(
                `¿Quitar a ${nombre} de la cotización? Se borrarán sus precios y selecciones.`,
            )
        ) {
            return;
        }
        setExtraIds((prev) => prev.filter((id) => id !== proveedorId));
        router.delete(
            `/admin/costos/requisiciones/${requisicion.id}/proveedores/${proveedorId}`,
            {
                preserveScroll: true,
            },
        );
    };

    const cotizacionDe = (
        d: CostosRequisicionDetalle,
        proveedorId: number,
    ): CostosRequisicionCotizacionPrecio | undefined =>
        d.cotizaciones?.find((c) => c.proveedor_id === proveedorId);

    // Total por proveedor (PU × cantidad) para el renglón comparativo final.
    const totalesPorProveedor: Record<number, number> = {};
    columnIds.forEach((pid) => (totalesPorProveedor[pid] = 0));
    detalles.forEach((d) => {
        columnIds.forEach((pid) => {
            const c = cotizacionDe(d, pid);
            if (c)
                totalesPorProveedor[pid] +=
                    Number(c.precio_unitario) * Number(d.cantidad);
        });
    });
    const totalMin = Math.min(
        ...Object.values(totalesPorProveedor).filter((v) => v > 0),
    );

    // Días de envío por proveedor: se toma el máximo de sus cotizaciones (el
    // envío tarda lo que la partida más lenta).
    const diasPorProveedor = new Map<number, number | null>();
    columnIds.forEach((pid) => {
        const valores = detalles
            .map((d) => cotizacionDe(d, pid)?.tiempo_entrega_dias)
            .filter((v): v is number => v != null);
        diasPorProveedor.set(
            pid,
            valores.length > 0 ? Math.max(...valores) : null,
        );
    });

    return (
        <div className="space-y-3">
            <div className="rounded-lg border border-base-300 p-3">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-xs tracking-wider text-base-content/60 uppercase">
                        Cotización · partidas × proveedores
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
                                            setExtraIds((prev) =>
                                                prev.includes(id)
                                                    ? prev
                                                    : [...prev, id],
                                            );
                                            setAgregando(false);
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
                                <th className="min-w-48">Partida</th>
                                <th className="min-w-28">Código</th>
                                <th className="text-right">Req.</th>
                                <th className="min-w-36">Tipo fiscal</th>
                                {columnIds.map((pid) => (
                                    <th key={pid} className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <span
                                                className="truncate"
                                                title={
                                                    proveedoresMap.get(pid)
                                                        ?.razon_social
                                                }
                                            >
                                                {proveedoresMap.get(pid)
                                                    ?.razon_social ?? `#${pid}`}
                                            </span>
                                            {editable && (
                                                <button
                                                    type="button"
                                                    className="btn px-1 text-error btn-ghost btn-xs"
                                                    title="Quitar proveedor"
                                                    onClick={() =>
                                                        quitarProveedor(pid)
                                                    }
                                                >
                                                    <XIcon className="size-3" />
                                                </button>
                                            )}
                                        </div>
                                    </th>
                                ))}
                                {columnIds.length === 0 && (
                                    <th className="text-base-content/40">
                                        Agrega un proveedor para cotizar
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => {
                                const precios = columnIds
                                    .map((pid) =>
                                        Number(
                                            cotizacionDe(d, pid)
                                                ?.precio_unitario ?? 0,
                                        ),
                                    )
                                    .filter((n) => n > 0);
                                const min =
                                    precios.length > 0
                                        ? Math.min(...precios)
                                        : 0;
                                return (
                                    <tr key={d.id}>
                                        <ProductoCelda
                                            key={`${d.id}-${d.descripcion}-${d.codigo_producto ?? ''}`}
                                            detalle={d}
                                            editable={editable}
                                        />
                                        <td className="text-right">
                                            {Number(d.cantidad).toLocaleString(
                                                'es-MX',
                                            )}
                                        </td>
                                        <td>
                                            <TipoFiscalSelect
                                                detalle={d}
                                                editable={editable}
                                            />
                                        </td>
                                        {columnIds.map((pid) => {
                                            const cot = cotizacionDe(d, pid);
                                            return (
                                                <td
                                                    key={pid}
                                                    className="text-right"
                                                >
                                                    <CeldaCotizacion
                                                        key={`${cot?.id ?? 'new'}-${cot?.precio_unitario ?? ''}-${cot?.moneda ?? ''}`}
                                                        requisicionDetalleId={
                                                            d.id
                                                        }
                                                        proveedorId={pid}
                                                        cotizacion={cot}
                                                        esMejor={
                                                            Number(
                                                                cot?.precio_unitario ??
                                                                    0,
                                                            ) > 0 &&
                                                            Number(
                                                                cot?.precio_unitario,
                                                            ) === min
                                                        }
                                                        editable={editable}
                                                    />
                                                </td>
                                            );
                                        })}
                                        {columnIds.length === 0 && <td />}
                                    </tr>
                                );
                            })}
                            {columnIds.length > 0 && (
                                <tr className="bg-base-200/50">
                                    <td className="font-semibold">
                                        Total si todo a uno
                                    </td>
                                    <td />
                                    <td />
                                    <td />
                                    {columnIds.map((pid) => {
                                        const t = totalesPorProveedor[pid];
                                        const isMin = t > 0 && t === totalMin;
                                        return (
                                            <td
                                                key={pid}
                                                className={`text-right font-semibold ${isMin ? 'bg-success/10 text-success' : ''}`}
                                            >
                                                {fmt(t)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            )}
                        </tbody>
                        {columnIds.length > 0 && (
                            <tfoot>
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-xs font-medium text-base-content/60"
                                    >
                                        Días de envío
                                    </td>
                                    {columnIds.map((pid) => (
                                        <td key={pid} className="text-right">
                                            <DiasEntregaCelda
                                                key={`${pid}-${diasPorProveedor.get(pid) ?? ''}`}
                                                requisicionId={requisicion.id}
                                                proveedorId={pid}
                                                dias={
                                                    diasPorProveedor.get(pid) ??
                                                    null
                                                }
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
    proveedorId,
    dias,
    editable,
}: {
    requisicionId: number;
    proveedorId: number;
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
            { proveedor_id: proveedorId, tiempo_entrega_dias: n },
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
    proveedorId,
    cotizacion,
    esMejor,
    editable,
}: {
    requisicionDetalleId: number;
    proveedorId: number;
    cotizacion?: CostosRequisicionCotizacionPrecio;
    esMejor: boolean;
    editable: boolean;
}) {
    // El estado inicial viene de las props; el componente se remonta (via `key`
    // en el padre) cuando el server devuelve el registro persistido.
    const [precio, setPrecio] = useState(
        cotizacion ? String(cotizacion.precio_unitario) : '',
    );
    const [moneda, setMoneda] = useState<CostosTipoMoneda>(
        cotizacion?.moneda ?? 'mxn',
    );

    const guardar = (monedaOverride?: CostosTipoMoneda) => {
        const p = Number(precio);
        const m = monedaOverride ?? moneda;

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
            m === (cotizacion.moneda ?? 'mxn')
        ) {
            return;
        }

        router.post(
            '/admin/costos/requisiciones/cotizaciones',
            {
                requisicion_detalle_id: requisicionDetalleId,
                proveedor_id: proveedorId,
                precio_unitario: p,
                moneda: m,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <input
                type="number"
                step="0.01"
                min={0}
                className={`input-bordered input input-xs w-24 text-right font-semibold ${esMejor ? 'border-success text-success' : ''}`}
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
    );
}
