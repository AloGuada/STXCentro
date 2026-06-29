import { router } from '@inertiajs/react';
import { PlusIcon, XIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
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
    const detalles = useMemo(() => requisicion.detalles ?? [], [requisicion.detalles]);

    const proveedoresMap = useMemo(() => {
        const m = new Map<number, ProveedorMin>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    // Columnas persistidas: proveedores con al menos una cotización capturada.
    const persistedIds = useMemo(() => {
        const s = new Set<number>();
        detalles.forEach((d) => d.cotizaciones?.forEach((c) => s.add(c.proveedor_id)));
        return Array.from(s);
    }, [detalles]);

    // Columnas agregadas localmente que todavía no tienen ningún precio.
    const [extraIds, setExtraIds] = useState<number[]>([]);

    const columnIds = useMemo(() => {
        const all = Array.from(new Set([...persistedIds, ...extraIds]));
        return all.sort((a, b) =>
            (proveedoresMap.get(a)?.razon_social ?? '').localeCompare(proveedoresMap.get(b)?.razon_social ?? ''),
        );
    }, [persistedIds, extraIds, proveedoresMap]);

    const [agregando, setAgregando] = useState(false);

    const disponiblesParaAgregar = proveedores.filter((p) => !columnIds.includes(p.id));

    const quitarProveedor = (proveedorId: number) => {
        // Columna solo local (sin precios persistidos): basta quitarla del estado.
        if (!persistedIds.includes(proveedorId)) {
            setExtraIds((prev) => prev.filter((id) => id !== proveedorId));
            return;
        }
        const nombre = proveedoresMap.get(proveedorId)?.razon_social ?? 'este proveedor';
        if (!confirm(`¿Quitar a ${nombre} de la cotización? Se borrarán sus precios y selecciones.`)) {
            return;
        }
        setExtraIds((prev) => prev.filter((id) => id !== proveedorId));
        router.delete(`/admin/costos/requisiciones/${requisicion.id}/proveedores/${proveedorId}`, {
            preserveScroll: true,
        });
    };

    const cotizacionDe = (d: CostosRequisicionDetalle, proveedorId: number): CostosRequisicionCotizacionPrecio | undefined =>
        d.cotizaciones?.find((c) => c.proveedor_id === proveedorId);

    // Total por proveedor (PU × cantidad) para el renglón comparativo final.
    const totalesPorProveedor: Record<number, number> = {};
    columnIds.forEach((pid) => (totalesPorProveedor[pid] = 0));
    detalles.forEach((d) => {
        columnIds.forEach((pid) => {
            const c = cotizacionDe(d, pid);
            if (c) totalesPorProveedor[pid] += Number(c.precio_unitario) * Number(d.cantidad);
        });
    });
    const totalMin = Math.min(...Object.values(totalesPorProveedor).filter((v) => v > 0));

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-xs uppercase tracking-wider text-base-content/60">
                    Cotización · partidas × proveedores
                </h3>
                {editable && (
                    agregando ? (
                        <div className="flex items-center gap-2">
                            <select
                                className="select select-bordered select-xs w-56"
                                defaultValue=""
                                onChange={(e) => {
                                    const id = Number(e.target.value);
                                    if (id) {
                                        setExtraIds((prev) => (prev.includes(id) ? prev : [...prev, id]));
                                        setAgregando(false);
                                    }
                                }}
                            >
                                <option value="">Selecciona proveedor...</option>
                                {disponiblesParaAgregar.map((p) => (
                                    <option key={p.id} value={p.id}>{p.razon_social}</option>
                                ))}
                            </select>
                            <Button variant="outline" onClick={() => setAgregando(false)}>Cancelar</Button>
                        </div>
                    ) : (
                        <Button
                            variant="outline"
                            onClick={() => setAgregando(true)}
                            disabled={disponiblesParaAgregar.length === 0}
                        >
                            <PlusIcon className="size-3" /> Agregar proveedor
                        </Button>
                    )
                )}
            </div>

            <div className="overflow-x-auto">
                <table className="table table-xs">
                    <thead>
                        <tr>
                            <th className="min-w-48">Partida</th>
                            <th className="text-right">Req.</th>
                            <th className="min-w-36">Tipo fiscal</th>
                            {columnIds.map((pid) => (
                                <th key={pid} className="text-right">
                                    <div className="flex items-center justify-end gap-1">
                                        <span className="truncate" title={proveedoresMap.get(pid)?.razon_social}>
                                            {proveedoresMap.get(pid)?.razon_social ?? `#${pid}`}
                                        </span>
                                        {editable && (
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs px-1 text-error"
                                                title="Quitar proveedor"
                                                onClick={() => quitarProveedor(pid)}
                                            >
                                                <XIcon className="size-3" />
                                            </button>
                                        )}
                                    </div>
                                </th>
                            ))}
                            {columnIds.length === 0 && (
                                <th className="text-base-content/40">Agrega un proveedor para cotizar</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {detalles.map((d) => {
                            const precios = columnIds
                                .map((pid) => Number(cotizacionDe(d, pid)?.precio_unitario ?? 0))
                                .filter((n) => n > 0);
                            const min = precios.length > 0 ? Math.min(...precios) : 0;
                            return (
                                <tr key={d.id}>
                                    <td>
                                        <ProductoCelda
                                            key={`${d.id}-${d.descripcion}-${d.codigo_producto ?? ''}`}
                                            detalle={d}
                                            editable={editable}
                                        />
                                    </td>
                                    <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')}</td>
                                    <td>
                                        <TipoFiscalSelect detalle={d} editable={editable} />
                                    </td>
                                    {columnIds.map((pid) => {
                                        const cot = cotizacionDe(d, pid);
                                        return (
                                            <td key={pid} className="text-right align-top">
                                                <CeldaCotizacion
                                                    key={`${cot?.id ?? 'new'}-${cot?.precio_unitario ?? ''}-${cot?.moneda ?? ''}`}
                                                    requisicionDetalleId={d.id}
                                                    proveedorId={pid}
                                                    cotizacion={cot}
                                                    esMejor={Number(cot?.precio_unitario ?? 0) > 0 && Number(cot?.precio_unitario) === min}
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
                                <td className="font-semibold">Total si todo a uno</td>
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
                </table>
            </div>
        </div>
    );
}

function ProductoCelda({ detalle, editable }: { detalle: CostosRequisicionDetalle; editable: boolean }) {
    const [descripcion, setDescripcion] = useState(detalle.descripcion);
    const [codigo, setCodigo] = useState(detalle.codigo_producto ?? '');

    // Sin producto del catálogo (partidas históricas) o no editable: solo lectura.
    if (!detalle.producto_id || !editable) {
        return (
            <div>
                <div className="font-medium">{detalle.descripcion}</div>
                <div className="text-[10px] text-base-content/50">
                    {detalle.codigo_producto ? `${detalle.codigo_producto} · ` : ''}{detalle.unidad}
                </div>
            </div>
        );
    }

    const guardar = () => {
        if (descripcion.trim() === '') return;
        if (descripcion === detalle.descripcion && (codigo.trim() || null) === (detalle.codigo_producto ?? null)) return;
        router.patch(
            `/admin/costos/requisiciones/detalles/${detalle.id}/producto`,
            { descripcion: descripcion.trim(), codigo: codigo.trim() || null },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-1">
            <input
                type="text"
                className="input input-bordered input-xs w-full font-medium"
                value={descripcion}
                onChange={(e) => setDescripcion(e.target.value)}
                onBlur={guardar}
                title="Descripción del producto (catálogo)"
            />
            <input
                type="text"
                className="input input-bordered input-xs w-full"
                value={codigo}
                placeholder="Código del catálogo"
                onChange={(e) => setCodigo(e.target.value)}
                onBlur={guardar}
            />
            <div className="text-[10px] text-base-content/50">{detalle.unidad}</div>
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
    const [tipoFiscal, setTipoFiscal] = useState<CostosTipoFiscalPartida>(detalle.tipo_fiscal ?? 'mercancia');

    return (
        <select
            className="select select-bordered select-xs w-full"
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
    const [precio, setPrecio] = useState(cotizacion ? String(cotizacion.precio_unitario) : '');
    const [moneda, setMoneda] = useState<CostosTipoMoneda>(cotizacion?.moneda ?? 'mxn');

    const guardar = (monedaOverride?: CostosTipoMoneda) => {
        const p = Number(precio);
        const m = monedaOverride ?? moneda;

        // Precio vacío o 0: si existía cotización, eliminarla.
        if (!Number.isFinite(p) || p <= 0) {
            if (cotizacion) {
                router.delete(`/admin/costos/requisiciones/cotizaciones/${cotizacion.id}`, { preserveScroll: true });
            }
            return;
        }

        // Sin cambios respecto a lo persistido.
        if (cotizacion && p === Number(cotizacion.precio_unitario) && m === (cotizacion.moneda ?? 'mxn')) {
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
                className={`input input-bordered input-xs w-24 text-right font-semibold ${esMejor ? 'border-success text-success' : ''}`}
                value={precio}
                disabled={!editable}
                placeholder="—"
                onChange={(e) => setPrecio(e.target.value)}
                onBlur={() => guardar()}
            />
            <select
                className="select select-bordered select-xs w-16"
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
