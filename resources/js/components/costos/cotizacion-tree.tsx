import { router } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import type {
    CostosRequisicion,
    CostosRequisicionCotizacionPrecio,
    CostosRequisicionDetalle,
    CostosRequisicionSeleccion,
    CostosTipoFiscalPartida,
    CostosTipoMoneda,
    ModoPago,
    Proveedor,
} from '@/types/models';
import { TIPO_MONEDA_LABELS } from '@/types/models';

const fmt = (n: number) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;


const IVA_RATE = 0.16;

// Tasas de retención — reflejan config/costos.php (cálculo informativo en cotización).
const RET_TASAS = {
    isr_resico: 0.0125,
    isr_fletes: 0.04,
    isr_honorarios: 0.1,
    iva_honorarios: 0.1067,
    iva_renta: 0.1067,
};

type ProveedorMin = Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial' | 'maneja_credito' | 'tipo_persona' | 'regimen_fiscal'>;

type RetencionLinea = { clave: string; concepto: string; tasa: number; monto: number };

/**
 * Espeja App\Services\Costos\RetencionCalculator para el preview de cotización.
 */
function calcularRetenciones(
    proveedor: ProveedorMin | undefined,
    lines: Array<{ tipo_fiscal: CostosTipoFiscalPartida; subtotal: number }>,
): RetencionLinea[] {
    const esPF = proveedor?.tipo_persona === 'fisica';
    const esResico = esPF && proveedor?.regimen_fiscal?.clave === '626';
    const acc = new Map<string, RetencionLinea>();
    const add = (clave: string, concepto: string, tasa: number, base: number) => {
        if (base <= 0 || tasa <= 0) return;
        const prev = acc.get(clave) ?? { clave, concepto, tasa, monto: 0 };
        prev.monto += base * tasa;
        acc.set(clave, prev);
    };
    lines.forEach(({ tipo_fiscal, subtotal }) => {
        if (esResico) add('isr_resico', 'ISR RESICO', RET_TASAS.isr_resico, subtotal);
        if (tipo_fiscal === 'flete') add('isr_fletes', 'ISR Fletes', RET_TASAS.isr_fletes, subtotal);
        if (tipo_fiscal === 'servicio_profesional' && esPF) {
            if (!esResico) add('isr_honorarios', 'ISR Honorarios', RET_TASAS.isr_honorarios, subtotal);
            add('iva_honorarios', 'IVA Honorarios', RET_TASAS.iva_honorarios, subtotal);
        }
        if (tipo_fiscal === 'renta' && esPF) add('iva_renta', 'IVA Renta', RET_TASAS.iva_renta, subtotal);
    });
    return Array.from(acc.values()).map((r) => ({ ...r, monto: Math.round(r.monto * 100) / 100 }));
}

export type OcOverride = {
    proveedor_id: number;
    numero_oc: number;
    modo_pago: ModoPago;
    fecha_entrega: string;
    notas: string;
};

type Props = {
    requisicion: CostosRequisicion;
    proveedores: ProveedorMin[];
    editable: boolean;
    previewEditable?: boolean;
    onPreviewChange?: (overrides: OcOverride[]) => void;
};

/**
 * Tab unificado de Cotización: matriz comparativa items × proveedores +
 * árbol editable por partida (precio, días, observaciones, cantidad, OC#)
 * + preview de OCs agrupadas por (proveedor, numero_oc) con modo de pago,
 * envío editables. Las observaciones, precios y selecciones se persisten
 * en cada blur; el preview se mantiene en estado local hasta liberar.
 */
export function CotizacionTree({ requisicion, proveedores, editable, onPreviewChange }: Props) {
    const proveedoresMap = useMemo(() => {
        const m = new Map<number, ProveedorMin>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    // Selecciones agrupadas por (proveedor, numero_oc) → preview de OCs
    const previewGroups = useMemo(() => buildPreviewGroups(requisicion), [requisicion]);

    // Estado del preview: overrides por (proveedor, oc) — modo de pago, envío,
    // notas, fecha. Default modo_pago según proveedor.maneja_credito.
    const [overrides, setOverrides] = useState<Record<string, OcOverride>>(() =>
        seedOverrides(previewGroups, proveedoresMap),
    );

    // Sembrar overrides para grupos nuevos cuando cambien las selecciones.
    useEffect(() => {
        setOverrides((prev) => {
            const seeded = seedOverrides(previewGroups, proveedoresMap);
            const merged: Record<string, OcOverride> = {};
            previewGroups.forEach((g) => {
                const key = groupKey(g.proveedor_id, g.numero_oc);
                merged[key] = prev[key] ?? seeded[key];
            });
            return merged;
        });
    }, [previewGroups, proveedoresMap, requisicion]);

    useEffect(() => {
        const list = previewGroups.map((g) => overrides[groupKey(g.proveedor_id, g.numero_oc)]).filter(Boolean);
        onPreviewChange?.(list);
    }, [overrides, previewGroups, onPreviewChange]);

    const updateOverride = (proveedorId: number, numeroOc: number, patch: Partial<OcOverride>) => {
        setOverrides((prev) => {
            const key = groupKey(proveedorId, numeroOc);
            return { ...prev, [key]: { ...prev[key], ...patch } };
        });
    };

    return (
        <div className="space-y-6">
            <MatrizComparativa requisicion={requisicion} proveedores={proveedores} />

            <ArbolPartidas
                requisicion={requisicion}
                proveedores={proveedores}
                editable={editable}
            />

            <PreviewOcs
                groups={previewGroups}
                proveedoresMap={proveedoresMap}
                overrides={overrides}
                onOverrideChange={updateOverride}
                editable={editable}
            />

            <ResumenTotales groups={previewGroups} overrides={overrides} />
        </div>
    );
}

// ---------- Matriz comparativa items × proveedores ----------

function MatrizComparativa({
    requisicion,
    proveedores,
}: {
    requisicion: CostosRequisicion;
    proveedores: ProveedorMin[];
}) {
    const detalles = requisicion.detalles ?? [];
    const proveedoresActivos = useMemo(() => {
        const ids = new Set<number>();
        detalles.forEach((d) => d.cotizaciones?.forEach((c) => ids.add(c.proveedor_id)));
        return Array.from(ids);
    }, [detalles]);

    const proveedoresMap = useMemo(() => {
        const m = new Map<number, ProveedorMin>();
        proveedores.forEach((p) => m.set(p.id, p));
        return m;
    }, [proveedores]);

    if (proveedoresActivos.length === 0) {
        return null;
    }

    const totalesPorProveedor: Record<number, number> = {};
    proveedoresActivos.forEach((pid) => (totalesPorProveedor[pid] = 0));
    detalles.forEach((d) => {
        proveedoresActivos.forEach((pid) => {
            const precio = Number(d.cotizaciones?.find((c) => c.proveedor_id === pid)?.precio_unitario ?? 0);
            totalesPorProveedor[pid] += precio * Number(d.cantidad);
        });
    });
    const totalMin = Math.min(...Object.values(totalesPorProveedor).filter((v) => v > 0));

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-2 text-xs uppercase tracking-wider text-base-content/60">
                Comparativo: items × proveedores
            </h3>
            <div className="overflow-x-auto">
                <table className="table table-xs">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th className="text-right">Req.</th>
                            {proveedoresActivos.map((pid) => (
                                <th key={pid} className="text-right">
                                    {proveedoresMap.get(pid)?.razon_social ?? `#${pid}`}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {detalles.map((d) => {
                            const precios: Record<number, number> = {};
                            const monedas: Record<number, CostosTipoMoneda> = {};
                            proveedoresActivos.forEach((pid) => {
                                const p = d.cotizaciones?.find((c) => c.proveedor_id === pid);
                                if (p) {
                                    precios[pid] = Number(p.precio_unitario);
                                    monedas[pid] = p.moneda ?? 'mxn';
                                }
                            });
                            const min = Object.values(precios).length > 0 ? Math.min(...Object.values(precios)) : 0;
                            return (
                                <tr key={d.id}>
                                    <td>
                                        <div>{d.descripcion}</div>
                                        <div className="text-[10px] text-base-content/50">{d.unidad}</div>
                                    </td>
                                    <td className="text-right">{Number(d.cantidad).toLocaleString('es-MX')}</td>
                                    {proveedoresActivos.map((pid) => {
                                        const has = pid in precios;
                                        const isMin = has && precios[pid] === min;
                                        return (
                                            <td
                                                key={pid}
                                                className={`text-right ${isMin ? 'bg-success/10 text-success font-semibold' : ''}`}
                                            >
                                                {has ? (
                                                    <span>
                                                        {fmt(precios[pid])}
                                                        <span className="ml-1 text-[10px] text-base-content/50">{TIPO_MONEDA_LABELS[monedas[pid]]}</span>
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            );
                        })}
                        <tr className="bg-base-200/50">
                            <td className="font-semibold">Total si todo a uno</td>
                            <td></td>
                            {proveedoresActivos.map((pid) => {
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
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ---------- Árbol de partidas con edición inline ----------

function ArbolPartidas({
    requisicion,
    proveedores,
    editable,
}: {
    requisicion: CostosRequisicion;
    proveedores: ProveedorMin[];
    editable: boolean;
}) {
    const detalles = requisicion.detalles ?? [];

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-2 text-xs uppercase tracking-wider text-base-content/60">Edición</h3>
            <div className="space-y-2">
                {detalles.map((d) => (
                    <PartidaNode
                        key={d.id}
                        detalle={d}
                        proveedores={proveedores}
                        editable={editable}
                    />
                ))}
            </div>
        </div>
    );
}

function PartidaNode({
    detalle,
    proveedores,
    editable,
}: {
    detalle: CostosRequisicionDetalle;
    proveedores: ProveedorMin[];
    editable: boolean;
}) {
    const [open, setOpen] = useState(true);
    const [agregando, setAgregando] = useState(false);
    const cotizaciones = detalle.cotizaciones ?? [];
    const selecciones = detalle.selecciones ?? [];

    const cantidadAsignada = selecciones.reduce((s, sel) => s + Number(sel.cantidad), 0);
    const cantidadRequerida = Number(detalle.cantidad);
    const cubierta = Math.abs(cantidadAsignada - cantidadRequerida) < 0.001;

    // Para mostrar en el header los proveedores elegidos
    const picksByProveedor = new Map<number, number>();
    selecciones.forEach((s) => {
        picksByProveedor.set(s.proveedor_id, (picksByProveedor.get(s.proveedor_id) ?? 0) + Number(s.cantidad));
    });
    const picks = Array.from(picksByProveedor.entries()).map(([pid, qty]) => {
        const p = proveedores.find((x) => x.id === pid);
        return { name: p?.razon_social ?? `#${pid}`, qty };
    });

    const proveedoresUsados = new Set(cotizaciones.map((c) => c.proveedor_id));
    const proveedoresDisponibles = proveedores.filter((p) => !proveedoresUsados.has(p.id));

    return (
        <div className="rounded border border-base-200">
            <div
                className="flex cursor-pointer items-center justify-between px-3 py-2 hover:bg-base-100"
                onClick={() => setOpen(!open)}
            >
                <div className="flex items-center gap-2">
                    <span className={`inline-block w-3 transition-transform ${open ? 'rotate-90' : ''}`}>▶</span>
                    <span className="text-sm font-semibold">{detalle.descripcion}</span>
                    {picks.length > 0 && (
                        <span className="text-xs text-base-content/60">
                            ·{' '}
                            {picks.length === 1
                                ? picks[0].name
                                : picks.map((p) => `${p.name} (${p.qty})`).join(' + ')}
                        </span>
                    )}
                </div>
                <span className={`text-xs ${cubierta ? 'text-base-content/60' : 'text-warning font-semibold'}`}>
                    {cantidadAsignada}/{cantidadRequerida} {detalle.unidad}
                </span>
            </div>

            {open && (
                <div className="border-t border-base-200 px-3 py-2">
                    <div className="grid grid-cols-[1fr_60px_60px_100px_72px_70px_1fr_30px] gap-2 border-b border-base-200 pb-1 text-[10px] uppercase tracking-wider text-base-content/60">
                        <div>Proveedor</div>
                        <div className="text-right">Cant.</div>
                        <div className="text-center">OC#</div>
                        <div className="text-right">P. unit</div>
                        <div className="text-center">Moneda</div>
                        <div className="text-right">Días</div>
                        <div>Observaciones</div>
                        <div></div>
                    </div>
                    {cotizaciones.map((c) => (
                        <CotizacionRow
                            key={c.id}
                            cotizacion={c}
                            detalle={detalle}
                            proveedor={proveedores.find((p) => p.id === c.proveedor_id)}
                            selecciones={selecciones.filter((s) => s.cotizacion_precio_id === c.id)}
                            editable={editable}
                            cubierta={cubierta}
                        />
                    ))}

                    {editable && (
                        <div className="mt-2">
                            {agregando ? (
                                <AgregarProveedorRow
                                    detalle={detalle}
                                    proveedoresDisponibles={proveedoresDisponibles}
                                    onClose={() => setAgregando(false)}
                                />
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={() => setAgregando(true)}
                                    disabled={proveedoresDisponibles.length === 0}
                                >
                                    <PlusIcon className="size-3" /> Agregar proveedor
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// Una fila por (cotizacion = partida×proveedor). Permite tener varias
// selecciones del mismo proveedor en distintos numero_oc → genera filas
// de selección extras. Aquí mostramos UNA fila editable por (cotización,
// numero_oc) — el usuario captura cantidad y OC#; al hacer blur se persiste.
function CotizacionRow({
    cotizacion,
    detalle,
    proveedor,
    selecciones,
    editable,
    cubierta,
}: {
    cotizacion: CostosRequisicionCotizacionPrecio;
    detalle: CostosRequisicionDetalle;
    proveedor?: ProveedorMin;
    selecciones: CostosRequisicionSeleccion[];
    editable: boolean;
    cubierta: boolean;
}) {
    // Si hay varias selecciones (numero_oc distintos), mostramos UNA fila por selección.
    // Si no hay ninguna, mostramos una fila con cantidad=0, numero_oc=1 para capturar.
    const filas = selecciones.length > 0
        ? selecciones.map((s) => ({ id: s.id, cantidad: Number(s.cantidad), numero_oc: s.numero_oc }))
        : [{ id: null as number | null, cantidad: 0, numero_oc: 1 }];

    return (
        <>
            {filas.map((fila, idx) => (
                <CotizacionFila
                    key={`${cotizacion.id}-${fila.id ?? `new-${idx}`}`}
                    cotizacion={cotizacion}
                    detalle={detalle}
                    proveedor={proveedor}
                    seleccion={fila.id ? { id: fila.id, cantidad: fila.cantidad, numero_oc: fila.numero_oc } : null}
                    showHeader={idx === 0}
                    showRemoveCotizacion={idx === 0 && filas.length === 1 && fila.cantidad === 0}
                    editable={editable}
                />
            ))}
            {/* Si ya hay selecciones y la partida no está cubierta al 100%,
                ofrece otra fila para capturar una OC adicional del mismo proveedor */}
            {editable && selecciones.length > 0 && !cubierta && (
                <CotizacionFila
                    key={`${cotizacion.id}-extra`}
                    cotizacion={cotizacion}
                    detalle={detalle}
                    proveedor={proveedor}
                    seleccion={null}
                    showHeader={false}
                    showRemoveCotizacion={false}
                    editable={editable}
                    extraOc
                />
            )}
        </>
    );
}

function CotizacionFila({
    cotizacion,
    detalle,
    proveedor,
    seleccion,
    showRemoveCotizacion,
    editable,
    extraOc,
}: {
    cotizacion: CostosRequisicionCotizacionPrecio;
    detalle: CostosRequisicionDetalle;
    proveedor?: ProveedorMin;
    seleccion: { id: number; cantidad: number; numero_oc: number } | null;
    showHeader: boolean;
    showRemoveCotizacion: boolean;
    editable: boolean;
    extraOc?: boolean;
}) {
    const sugerirNumeroOc = () => {
        const usados = (detalle.selecciones ?? [])
            .filter((s) => s.cotizacion_precio_id === cotizacion.id)
            .map((s) => s.numero_oc);
        return usados.length === 0 ? 1 : Math.max(...usados) + 1;
    };

    const [precio, setPrecio] = useState(String(cotizacion.precio_unitario));
    const [moneda, setMoneda] = useState<CostosTipoMoneda>(cotizacion.moneda ?? 'mxn');
    const [tiempo, setTiempo] = useState(cotizacion.tiempo_entrega_dias != null ? String(cotizacion.tiempo_entrega_dias) : '');
    const [observ, setObserv] = useState(cotizacion.observaciones ?? '');
    const [cantidad, setCantidad] = useState(seleccion ? String(seleccion.cantidad) : '');
    const [numeroOc, setNumeroOc] = useState(seleccion ? String(seleccion.numero_oc) : extraOc ? String(sugerirNumeroOc()) : '1');

    const guardarPrecio = (monedaOverride?: CostosTipoMoneda) => {
        const p = Number(precio);
        if (!Number.isFinite(p) || p <= 0) return;
        const monedaActual = monedaOverride ?? moneda;
        if (
            p === Number(cotizacion.precio_unitario)
            && monedaActual === (cotizacion.moneda ?? 'mxn')
            && (tiempo === '' ? null : Number(tiempo)) === cotizacion.tiempo_entrega_dias
            && observ === (cotizacion.observaciones ?? '')
        ) return;

        router.post('/admin/costos/requisiciones/cotizaciones', {
            requisicion_detalle_id: detalle.id,
            proveedor_id: cotizacion.proveedor_id,
            precio_unitario: p,
            moneda: monedaActual,
            tiempo_entrega_dias: tiempo ? Number(tiempo) : null,
            observaciones: observ || null,
        }, { preserveScroll: true });
    };

    const guardarSeleccion = () => {
        const cant = Number(cantidad) || 0;
        const oc = Math.max(1, Number(numeroOc) || 1);

        if (seleccion) {
            // Si la cantidad bajó a 0, eliminar la selección.
            if (cant <= 0) {
                router.delete(`/admin/costos/requisiciones/selecciones/${seleccion.id}`, { preserveScroll: true });
                return;
            }
            // Si cambió cantidad u OC#, eliminar y volver a crear (no hay endpoint update).
            if (cant !== seleccion.cantidad || oc !== seleccion.numero_oc) {
                router.delete(`/admin/costos/requisiciones/selecciones/${seleccion.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.post('/admin/costos/requisiciones/selecciones', {
                            cotizacion_precio_id: cotizacion.id,
                            cantidad: cant,
                            numero_oc: oc,
                        }, { preserveScroll: true });
                    },
                });
            }
        } else if (cant > 0) {
            router.post('/admin/costos/requisiciones/selecciones', {
                cotizacion_precio_id: cotizacion.id,
                cantidad: cant,
                numero_oc: oc,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    if (extraOc) {
                        setCantidad('');
                        setNumeroOc(String(sugerirNumeroOc()));
                    }
                },
            });
        }
    };

    const eliminarCotizacion = () => {
        if (!confirm('¿Eliminar este proveedor de la cotización?')) return;
        router.delete(`/admin/costos/requisiciones/cotizaciones/${cotizacion.id}`, { preserveScroll: true });
    };

    const selected = (Number(cantidad) || 0) > 0;

    return (
        <div
            className={`grid grid-cols-[1fr_60px_60px_100px_72px_70px_1fr_30px] gap-2 items-center py-1 ${selected ? 'bg-success/5' : ''}`}
        >
            <div className="text-xs">{proveedor?.razon_social ?? `#${cotizacion.proveedor_id}`}</div>
            <input
                type="number"
                step="1"
                min={0}
                className="input input-bordered input-xs w-full text-right"
                value={cantidad}
                disabled={!editable}
                placeholder="0"
                onChange={(e) => setCantidad(e.target.value)}
                onBlur={guardarSeleccion}
            />
            <input
                type="number"
                min={1}
                className="input input-bordered input-xs w-full text-center"
                value={numeroOc}
                disabled={!editable || (Number(cantidad) || 0) <= 0}
                onChange={(e) => setNumeroOc(e.target.value)}
                onBlur={guardarSeleccion}
            />
            <input
                type="number"
                step="0.01"
                min={0}
                className="input input-bordered input-xs w-full text-right font-semibold"
                value={precio}
                disabled={!editable}
                onChange={(e) => setPrecio(e.target.value)}
                onBlur={() => guardarPrecio()}
            />
            <select
                className="select select-bordered select-xs w-full"
                value={moneda}
                disabled={!editable}
                onChange={(e) => {
                    const m = e.target.value as CostosTipoMoneda;
                    setMoneda(m);
                    guardarPrecio(m);
                }}
            >
                <option value="mxn">MXN</option>
                <option value="usd">USD</option>
                <option value="eur">EUR</option>
            </select>
            <input
                type="number"
                min={0}
                className="input input-bordered input-xs w-full text-right"
                value={tiempo}
                disabled={!editable}
                onChange={(e) => setTiempo(e.target.value)}
                onBlur={() => guardarPrecio()}
            />
            <input
                type="text"
                className="input input-bordered input-xs w-full"
                value={observ}
                disabled={!editable}
                onChange={(e) => setObserv(e.target.value)}
                onBlur={() => guardarPrecio()}
            />
            <div className="flex justify-end">
                {showRemoveCotizacion && editable && (
                    <button
                        type="button"
                        onClick={eliminarCotizacion}
                        className="btn btn-ghost btn-xs text-error"
                        title="Quitar proveedor"
                    >
                        <Trash2Icon className="size-3" />
                    </button>
                )}
            </div>
        </div>
    );
}

function AgregarProveedorRow({
    detalle,
    proveedoresDisponibles,
    onClose,
}: {
    detalle: CostosRequisicionDetalle;
    proveedoresDisponibles: ProveedorMin[];
    onClose: () => void;
}) {
    const [proveedorId, setProveedorId] = useState<number | ''>('');
    const [precio, setPrecio] = useState('');
    const [moneda, setMoneda] = useState<CostosTipoMoneda>('mxn');
    const submit = () => {
        if (proveedorId === '' || !precio) return;
        router.post('/admin/costos/requisiciones/cotizaciones', {
            requisicion_detalle_id: detalle.id,
            proveedor_id: proveedorId,
            precio_unitario: Number(precio),
            moneda,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };
    return (
        <div className="flex items-end gap-2">
            <div className="flex-1">
                <select
                    className="select select-bordered select-xs w-full"
                    value={proveedorId}
                    onChange={(e) => setProveedorId(e.target.value ? Number(e.target.value) : '')}
                >
                    <option value="">Selecciona proveedor...</option>
                    {proveedoresDisponibles.map((p) => (
                        <option key={p.id} value={p.id}>{p.razon_social}</option>
                    ))}
                </select>
            </div>
            <input
                type="number"
                step="0.01"
                min="0.01"
                placeholder="Precio"
                className="input input-bordered input-xs w-28 text-right"
                value={precio}
                onChange={(e) => setPrecio(e.target.value)}
            />
            <select
                className="select select-bordered select-xs w-20"
                value={moneda}
                onChange={(e) => setMoneda(e.target.value as CostosTipoMoneda)}
            >
                <option value="mxn">MXN</option>
                <option value="usd">USD</option>
                <option value="eur">EUR</option>
            </select>
            <Button onClick={submit} disabled={proveedorId === '' || !precio}>Agregar</Button>
            <Button variant="outline" onClick={onClose}>Cancelar</Button>
        </div>
    );
}

// ---------- Preview de OCs ----------

type PreviewGroup = {
    proveedor_id: number;
    numero_oc: number;
    lines: Array<{
        descripcion: string;
        unidad: string;
        cantidad: number;
        precio_unitario: number;
        subtotal: number;
        observaciones: string | null;
        tipo_fiscal: CostosTipoFiscalPartida;
    }>;
    subtotal_lineas: number;
    has_no_credito: boolean; // proveedor.maneja_credito === false
    dias_max: number; // máx. tiempo_entrega_dias de las selecciones del grupo
    moneda: CostosTipoMoneda; // moneda de las cotizaciones del grupo (la primera)
    moneda_conflicto: boolean; // true si las líneas mezclan monedas
};

function PreviewOcs({
    groups,
    proveedoresMap,
    overrides,
    onOverrideChange,
    editable,
}: {
    groups: PreviewGroup[];
    proveedoresMap: Map<number, ProveedorMin>;
    overrides: Record<string, OcOverride>;
    onOverrideChange: (proveedorId: number, numeroOc: number, patch: Partial<OcOverride>) => void;
    editable: boolean;
}) {
    if (groups.length === 0) {
        return (
            <div className="rounded-lg border border-base-300 p-3 text-sm text-base-content/60">
                <h3 className="mb-2 text-xs uppercase tracking-wider text-base-content/60">Órdenes de compra</h3>
                Asigna cantidades para generar órdenes.
            </div>
        );
    }

    return (
        <div className="rounded-lg border border-base-300 p-3">
            <h3 className="mb-2 text-xs uppercase tracking-wider text-base-content/60">Órdenes de compra</h3>
            <div className="space-y-3">
                {groups.map((g) => {
                    const key = groupKey(g.proveedor_id, g.numero_oc);
                    const ov = overrides[key];
                    if (!ov) return null;
                    const base = g.subtotal_lineas;
                    const iva = base * IVA_RATE;
                    const total = base + iva;
                    const proveedor = proveedoresMap.get(g.proveedor_id);
                    const retenciones = calcularRetenciones(
                        proveedor,
                        g.lines.map((l) => ({ tipo_fiscal: l.tipo_fiscal, subtotal: l.subtotal })),
                    );
                    const totalRetenciones = retenciones.reduce((s, r) => s + r.monto, 0);
                    const totalNeto = total - totalRetenciones;

                    const manejaCredito = !g.has_no_credito;

                    return (
                        <div key={key} className="overflow-hidden rounded border border-base-300">
                            <div className="flex items-center justify-between border-b border-base-300 bg-base-200/40 px-3 py-2">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    {proveedor?.razon_social ?? `#${g.proveedor_id}`} · OC-{g.numero_oc}
                                    {g.moneda_conflicto ? (
                                        <span className="badge badge-error badge-sm">Monedas mezcladas</span>
                                    ) : (
                                        <span className="badge badge-ghost badge-sm">{TIPO_MONEDA_LABELS[g.moneda]}</span>
                                    )}
                                </div>
                                <div className="flex items-center gap-3 text-xs">
                                    <label className="cursor-pointer">
                                        <input
                                            type="radio"
                                            className="mr-1"
                                            name={`pay-${key}`}
                                            value="contado"
                                            checked={ov.modo_pago === 'contado'}
                                            disabled={!editable}
                                            onChange={() => onOverrideChange(g.proveedor_id, g.numero_oc, { modo_pago: 'contado' })}
                                        />
                                        Contado
                                    </label>
                                    {manejaCredito && (
                                        <label className="cursor-pointer">
                                            <input
                                                type="radio"
                                                className="mr-1"
                                                name={`pay-${key}`}
                                                value="credito"
                                                checked={ov.modo_pago === 'credito'}
                                                disabled={!editable}
                                                onChange={() => onOverrideChange(g.proveedor_id, g.numero_oc, { modo_pago: 'credito' })}
                                            />
                                            Crédito
                                        </label>
                                    )}
                                </div>
                            </div>
                            {g.moneda_conflicto && (
                                <div className="alert alert-error rounded-none text-xs">
                                    <span>Esta OC mezcla monedas distintas. Separa las partidas por moneda en OCs diferentes (cambia el OC#) para poder liberar.</span>
                                </div>
                            )}
                            <div className="px-3 py-2">
                                <div className="grid grid-cols-[1fr_60px_90px_90px] gap-2 border-b border-base-200 pb-1 text-[10px] uppercase tracking-wider text-base-content/60">
                                    <div>Concepto</div>
                                    <div className="text-right">Cant.</div>
                                    <div className="text-right">P. unit</div>
                                    <div className="text-right">Subtotal</div>
                                </div>
                                {g.lines.map((l, idx) => (
                                    <div key={idx} className="grid grid-cols-[1fr_60px_90px_90px] gap-2 py-1 text-xs">
                                        <div>{l.descripcion}</div>
                                        <div className="text-right">{l.cantidad}</div>
                                        <div className="text-right">{fmt(l.precio_unitario)}</div>
                                        <div className="text-right">{fmt(l.subtotal)}</div>
                                    </div>
                                ))}
                                <div className="mt-2 grid grid-cols-[1fr_90px] gap-2 border-t border-base-200 pt-1 text-xs">
                                    <div className="text-base-content/60">Subtotal</div>
                                    <div className="text-right">{fmt(g.subtotal_lineas)}</div>
                                    <div className="text-base-content/60">IVA (16%)</div>
                                    <div className="text-right">{fmt(iva)}</div>
                                    {retenciones.map((r) => (
                                        <Fragment key={r.clave}>
                                            <div className="text-error/80">Ret. {r.concepto} ({(r.tasa * 100).toFixed(2)}%)</div>
                                            <div className="text-right text-error/80">−{fmt(r.monto)}</div>
                                        </Fragment>
                                    ))}
                                    {retenciones.length > 0 && (
                                        <>
                                            <div className="font-semibold">Total neto a pagar</div>
                                            <div className="text-right font-semibold">{fmt(totalNeto)}</div>
                                        </>
                                    )}
                                </div>
                                <div className="mt-3 grid grid-cols-1 gap-2 border-t border-base-200 pt-2 md:grid-cols-[200px_1fr]">
                                    <div>
                                        <label className="label-text text-[10px] uppercase tracking-wider text-base-content/60">Fecha de entrega</label>
                                        <input
                                            type="date"
                                            className="input input-bordered input-xs w-full"
                                            value={ov.fecha_entrega}
                                            disabled={!editable}
                                            onChange={(e) => onOverrideChange(g.proveedor_id, g.numero_oc, { fecha_entrega: e.target.value })}
                                        />
                                    </div>
                                    <div>
                                        <label className="label-text text-[10px] uppercase tracking-wider text-base-content/60">Notas para esta OC</label>
                                        <input
                                            type="text"
                                            className="input input-bordered input-xs w-full"
                                            value={ov.notas}
                                            disabled={!editable}
                                            placeholder="Opcional. Instrucciones para el proveedor"
                                            onChange={(e) => onOverrideChange(g.proveedor_id, g.numero_oc, { notas: e.target.value })}
                                        />
                                    </div>
                                </div>

                                <div className="mt-2 flex items-center justify-between border-t border-dashed border-base-200 pt-2 text-sm font-semibold">
                                    <div>Total</div>
                                    <div>
                                        {fmt(total)}{' '}
                                        <span
                                            className={`ml-1 rounded px-2 py-0.5 text-[10px] ${
                                                ov.modo_pago === 'credito'
                                                    ? 'bg-warning/20 text-warning'
                                                    : 'bg-success/20 text-success'
                                            }`}
                                        >
                                            {ov.modo_pago === 'credito' ? 'CRÉDITO' : 'CONTADO'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ---------- Resumen general ----------

function ResumenTotales({
    groups,
    overrides,
}: {
    groups: PreviewGroup[];
    overrides: Record<string, OcOverride>;
}) {
    if (groups.length === 0) return null;

    let subtotal = 0;
    let totalContado = 0;
    let totalCredito = 0;

    groups.forEach((g) => {
        const ov = overrides[groupKey(g.proveedor_id, g.numero_oc)];
        if (!ov) return;
        const total = g.subtotal_lineas * (1 + IVA_RATE);
        subtotal += g.subtotal_lineas;
        if (ov.modo_pago === 'credito') totalCredito += total;
        else totalContado += total;
    });
    const iva = subtotal * IVA_RATE;
    const totalGeneral = subtotal + iva;

    return (
        <div className="rounded-lg border border-base-300 bg-base-200/30 p-3">
            <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
                <span className="text-base-content/60">Subtotal</span>
                <span className="text-right">{fmt(subtotal)}</span>
                <span className="text-base-content/60">IVA (16%)</span>
                <span className="text-right">{fmt(iva)}</span>
                <span className="text-base-content/60">Contado</span>
                <span className="text-right text-success">{fmt(totalContado)}</span>
                <span className="text-base-content/60">Crédito</span>
                <span className="text-right text-warning">{fmt(totalCredito)}</span>
                <span className="border-t border-base-300 pt-1 text-sm font-semibold">Total general</span>
                <span className="border-t border-base-300 pt-1 text-right text-sm font-semibold text-success">
                    {fmt(totalGeneral)}
                </span>
            </div>
        </div>
    );
}

// ---------- Helpers ----------

function groupKey(proveedorId: number, numeroOc: number): string {
    return `${proveedorId}|${numeroOc}`;
}

function buildPreviewGroups(requisicion: CostosRequisicion): PreviewGroup[] {
    const groups = new Map<string, PreviewGroup>();
    requisicion.detalles?.forEach((d) => {
        d.selecciones?.forEach((s) => {
            const numeroOc = s.numero_oc ?? 1;
            const key = groupKey(s.proveedor_id, numeroOc);
            const precio = Number(s.cotizacion_precio?.precio_unitario ?? 0);
            const cantidad = Number(s.cantidad);
            const subtotal = precio * cantidad;
            if (!groups.has(key)) {
                groups.set(key, {
                    proveedor_id: s.proveedor_id,
                    numero_oc: numeroOc,
                    lines: [],
                    subtotal_lineas: 0,
                    has_no_credito: false,
                    dias_max: 0,
                    moneda: (s.cotizacion_precio?.moneda ?? 'mxn') as CostosTipoMoneda,
                    moneda_conflicto: false,
                });
            }
            const g = groups.get(key)!;
            const lineaMoneda = (s.cotizacion_precio?.moneda ?? 'mxn') as CostosTipoMoneda;
            if (lineaMoneda !== g.moneda) g.moneda_conflicto = true;
            g.lines.push({
                descripcion: d.descripcion,
                unidad: d.unidad,
                cantidad,
                precio_unitario: precio,
                subtotal,
                observaciones: s.cotizacion_precio?.observaciones ?? null,
                tipo_fiscal: d.tipo_fiscal ?? 'mercancia',
            });
            g.subtotal_lineas += subtotal;
            const dias = Number(s.cotizacion_precio?.tiempo_entrega_dias ?? 0);
            if (dias > g.dias_max) g.dias_max = dias;
        });
    });
    return Array.from(groups.values()).sort((a, b) => {
        if (a.proveedor_id !== b.proveedor_id) return a.proveedor_id - b.proveedor_id;
        return a.numero_oc - b.numero_oc;
    });
}

function seedOverrides(
    groups: PreviewGroup[],
    proveedoresMap: Map<number, ProveedorMin>,
): Record<string, OcOverride> {
    const out: Record<string, OcOverride> = {};
    groups.forEach((g) => {
        const proveedor = proveedoresMap.get(g.proveedor_id);
        const manejaCredito = proveedor?.maneja_credito ?? true;
        // Detectar "sin crédito" en observaciones como heurística adicional
        const obsHasNoCredito = g.lines.some((l) => /sin\s*cr[eé]dito/i.test(l.observaciones ?? ''));
        const noCreditoEffective = !manejaCredito || obsHasNoCredito;
        const dias = g.dias_max > 0 ? g.dias_max : 7;
        const fecha = new Date();
        fecha.setDate(fecha.getDate() + dias);
        out[groupKey(g.proveedor_id, g.numero_oc)] = {
            proveedor_id: g.proveedor_id,
            numero_oc: g.numero_oc,
            modo_pago: noCreditoEffective ? 'contado' : 'credito',
            fecha_entrega: fecha.toISOString().slice(0, 10),
            notas: '',
        };
        // Mark whether proveedor doesn't take credit (so warnings can fire)
        g.has_no_credito = !manejaCredito;
    });
    return out;
}
