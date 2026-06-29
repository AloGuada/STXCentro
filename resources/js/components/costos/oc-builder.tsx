import { router } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';
import { Fragment, useMemo, useRef, useState } from 'react';
import { calcularRetenciones, IVA_RATE } from '@/components/costos/retenciones';
import { Button } from '@/components/ui/button';
import type {
    CostosRequisicion,
    CostosRequisicionDetalle,
    CostosRequisicionOc,
    CostosRequisicionOcPago,
    CostosTipoMoneda,
    ModoPago,
    Proveedor,
} from '@/types/models';
import { TIPO_MONEDA_LABELS } from '@/types/models';

type ProveedorMin = Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial' | 'maneja_credito' | 'tipo_persona' | 'regimen_fiscal'>;

const fmt = (n: number) =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const groupKey = (proveedorId: number, numeroOc: number) => `${proveedorId}|${numeroOc}`;

type Linea = {
    seleccion_id: number;
    detalle: CostosRequisicionDetalle;
    cantidad: number;
    precio_unitario: number;
    moneda: CostosTipoMoneda;
    cotizacion_precio_id: number;
};

type Grupo = { proveedor_id: number; numero_oc: number; lineas: Linea[] };
type Cobertura = { detalle: CostosRequisicionDetalle; requerido: number; asignado: number; restante: number };
type Draft = { localId: number; proveedor_id: number | null; numero_oc: number | null };

/**
 * Constructor de OCs simplificado: arranca con una sola OC vacía; eliges el
 * proveedor dentro de la tarjeta, agregas partidas/cantidades, defines modo de
 * pago, fecha, notas y —en contado— parcialidades por % (cada hito genera una
 * solicitud de pago al liberar). Botón para añadir otra OC debajo.
 */
export function OcBuilder({
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

    const ocsMetaMap = useMemo(() => {
        const m = new Map<string, CostosRequisicionOc>();
        (requisicion.ocs ?? []).forEach((oc) => m.set(groupKey(oc.proveedor_id, oc.numero_oc), oc));
        return m;
    }, [requisicion.ocs]);

    const gruposReales = useMemo(() => {
        const map = new Map<string, Grupo>();
        detalles.forEach((d) => {
            (d.selecciones ?? []).forEach((s) => {
                const numeroOc = s.numero_oc ?? 1;
                const key = groupKey(s.proveedor_id, numeroOc);
                if (!map.has(key)) map.set(key, { proveedor_id: s.proveedor_id, numero_oc: numeroOc, lineas: [] });
                map.get(key)!.lineas.push({
                    seleccion_id: s.id,
                    detalle: d,
                    cantidad: Number(s.cantidad),
                    precio_unitario: Number(s.cotizacion_precio?.precio_unitario ?? 0),
                    moneda: (s.cotizacion_precio?.moneda ?? 'mxn') as CostosTipoMoneda,
                    cotizacion_precio_id: s.cotizacion_precio_id,
                });
            });
        });
        return map;
    }, [detalles]);

    const cobertura: Cobertura[] = useMemo(() =>
        detalles.map((d) => {
            const requerido = Number(d.cantidad);
            const asignado = (d.selecciones ?? []).reduce((s, sel) => s + Number(sel.cantidad), 0);
            return { detalle: d, requerido, asignado, restante: Math.max(0, requerido - asignado) };
        }), [detalles]);

    const partidasSinCubrir = cobertura.filter((c) => c.restante > 0.001).length;

    // OCs nuevas (sin selecciones aún): el proveedor se elige dentro de la tarjeta.
    const nextLocalId = useRef(1);
    const [drafts, setDrafts] = useState<Draft[]>(() =>
        gruposReales.size === 0 ? [{ localId: 0, proveedor_id: null, numero_oc: null }] : [],
    );

    const proveedoresCotizadores = useMemo(() => {
        const ids = new Set<number>();
        detalles.forEach((d) => d.cotizaciones?.forEach((c) => ids.add(c.proveedor_id)));
        return proveedores.filter((p) => ids.has(p.id));
    }, [detalles, proveedores]);

    const nextNumeroOc = (proveedorId: number, exceptLocalId: number): number => {
        const usados = [
            ...Array.from(gruposReales.values()).filter((g) => g.proveedor_id === proveedorId).map((g) => g.numero_oc),
            ...drafts.filter((d) => d.proveedor_id === proveedorId && d.numero_oc !== null && d.localId !== exceptLocalId).map((d) => d.numero_oc as number),
        ];
        return usados.length > 0 ? Math.max(...usados) + 1 : 1;
    };

    const pickProveedor = (localId: number, proveedorId: number | null) => {
        setDrafts((prev) =>
            prev.map((d) =>
                d.localId === localId
                    ? { ...d, proveedor_id: proveedorId, numero_oc: proveedorId ? nextNumeroOc(proveedorId, localId) : null }
                    : d,
            ),
        );
    };

    const removeDraft = (localId: number) => setDrafts((prev) => prev.filter((d) => d.localId !== localId));
    const agregarOtraOc = () => setDrafts((prev) => [...prev, { localId: nextLocalId.current++, proveedor_id: null, numero_oc: null }]);

    const realCards = Array.from(gruposReales.values()).sort((a, b) => {
        const na = proveedoresMap.get(a.proveedor_id)?.razon_social ?? '';
        const nb = proveedoresMap.get(b.proveedor_id)?.razon_social ?? '';
        return na.localeCompare(nb) || a.numero_oc - b.numero_oc;
    });

    return (
        <div className="space-y-3">
            {realCards.map((g) => (
                <OcCard
                    key={groupKey(g.proveedor_id, g.numero_oc)}
                    requisicionId={requisicion.id}
                    proveedorId={g.proveedor_id}
                    numeroOc={g.numero_oc}
                    lineas={g.lineas}
                    proveedor={proveedoresMap.get(g.proveedor_id)}
                    meta={ocsMetaMap.get(groupKey(g.proveedor_id, g.numero_oc))}
                    cobertura={cobertura}
                    proveedoresCotizadores={proveedoresCotizadores}
                    editable={editable}
                />
            ))}

            {drafts.map((d) => (
                <OcCard
                    key={`draft-${d.localId}-${d.proveedor_id ?? 'none'}`}
                    requisicionId={requisicion.id}
                    proveedorId={d.proveedor_id}
                    numeroOc={d.numero_oc}
                    lineas={[]}
                    proveedor={d.proveedor_id ? proveedoresMap.get(d.proveedor_id) : undefined}
                    meta={d.proveedor_id && d.numero_oc ? ocsMetaMap.get(groupKey(d.proveedor_id, d.numero_oc)) : undefined}
                    cobertura={cobertura}
                    proveedoresCotizadores={proveedoresCotizadores}
                    editable={editable}
                    isDraft
                    onPickProveedor={(pid) => pickProveedor(d.localId, pid)}
                    onRemoveDraft={() => removeDraft(d.localId)}
                    onConverted={() => removeDraft(d.localId)}
                />
            ))}

            {editable && (
                <Button variant="outline" onClick={agregarOtraOc}>
                    <PlusIcon className="size-3" /> Agregar otra OC
                </Button>
            )}

            {partidasSinCubrir > 0 && (
                <p className="text-xs text-warning">
                    Faltan {partidasSinCubrir} {partidasSinCubrir === 1 ? 'partida' : 'partidas'} por cubrir al 100% para poder enviar a aprobación.
                </p>
            )}
        </div>
    );
}

function OcCard({
    requisicionId,
    proveedorId,
    numeroOc,
    lineas,
    proveedor,
    meta,
    cobertura,
    proveedoresCotizadores,
    editable,
    isDraft = false,
    onPickProveedor,
    onRemoveDraft,
    onConverted,
}: {
    requisicionId: number;
    proveedorId: number | null;
    numeroOc: number | null;
    lineas: Linea[];
    proveedor?: ProveedorMin;
    meta?: CostosRequisicionOc;
    cobertura: Cobertura[];
    proveedoresCotizadores: ProveedorMin[];
    editable: boolean;
    isDraft?: boolean;
    onPickProveedor?: (proveedorId: number | null) => void;
    onRemoveDraft?: () => void;
    onConverted?: () => void;
}) {
    const manejaCredito = proveedor?.maneja_credito === true;

    const [modoPago, setModoPago] = useState<ModoPago>(meta?.modo_pago ?? (manejaCredito ? 'credito' : 'contado'));
    const [metodoPago, setMetodoPago] = useState<'transferencia' | 'cheque' | 'efectivo'>(meta?.metodo_pago ?? 'transferencia');
    const [fecha, setFecha] = useState(meta?.fecha_entrega ?? '');
    const [fechaPago, setFechaPago] = useState(meta?.fecha_pago ?? '');
    const [notas, setNotas] = useState(meta?.notas ?? '');
    const [pagos, setPagos] = useState<CostosRequisicionOcPago[]>(meta?.pagos ?? []);

    const hasLineas = lineas.length > 0;

    // ── Selector de proveedor (OC nueva sin partidas) ──
    if (proveedorId === null) {
        return (
            <div className="rounded-lg border border-dashed border-base-300 p-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-xs uppercase tracking-wider text-base-content/60">Nueva OC · proveedor</span>
                    <select
                        className="select select-bordered select-sm w-64"
                        value=""
                        disabled={!editable}
                        onChange={(e) => onPickProveedor?.(e.target.value ? Number(e.target.value) : null)}
                    >
                        <option value="">Elige el proveedor...</option>
                        {proveedoresCotizadores.map((p) => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>
                    {editable && onRemoveDraft && (
                        <button type="button" className="btn btn-ghost btn-xs text-error" onClick={onRemoveDraft} title="Quitar OC">
                            <Trash2Icon className="size-3" />
                        </button>
                    )}
                </div>
            </div>
        );
    }

    const persistMeta = (patch: { modo_pago?: ModoPago; metodo_pago?: 'transferencia' | 'cheque' | 'efectivo'; fecha_entrega?: string; fecha_pago?: string; notas?: string; pagos?: CostosRequisicionOcPago[] }) => {
        const modo = patch.modo_pago ?? modoPago;
        const listaPagos = modo === 'contado' ? (patch.pagos ?? pagos) : [];
        router.post(
            `/admin/costos/requisiciones/${requisicionId}/ocs`,
            {
                proveedor_id: proveedorId,
                numero_oc: numeroOc,
                modo_pago: modo,
                metodo_pago: patch.metodo_pago ?? metodoPago,
                fecha_entrega: (patch.fecha_entrega ?? fecha) || null,
                fecha_pago: (patch.fecha_pago ?? fechaPago) || null,
                notas: (patch.notas ?? notas).trim() || null,
                pagos: listaPagos.map((p) => ({ porcentaje: p.porcentaje, concepto: p.concepto })),
            },
            { preserveScroll: true },
        );
    };

    const cambiarModoPago = (modo: ModoPago) => {
        setModoPago(modo);
        if (modo === 'credito') setPagos([]);
        persistMeta({ modo_pago: modo, pagos: modo === 'credito' ? [] : pagos });
    };

    const subtotal = lineas.reduce((s, l) => s + l.precio_unitario * l.cantidad, 0);
    const iva = subtotal * IVA_RATE;
    const total = subtotal + iva;
    const retenciones = calcularRetenciones(
        proveedor,
        lineas.map((l) => ({ tipo_fiscal: l.detalle.tipo_fiscal, subtotal: l.precio_unitario * l.cantidad })),
    );
    const totalRet = retenciones.reduce((s, r) => s + r.monto, 0);

    const monedas = new Set(lineas.map((l) => l.moneda));
    const monedaConflicto = monedas.size > 1;
    const moneda = lineas[0]?.moneda ?? 'mxn';

    const idsEnOc = new Set(lineas.map((l) => l.detalle.id));
    const partidasAgregables = cobertura
        .filter((c) => c.restante > 0.001 && !idsEnOc.has(c.detalle.id))
        .map((c) => ({ ...c, cot: c.detalle.cotizaciones?.find((x) => x.proveedor_id === proveedorId) }))
        .filter((c) => c.cot);

    const agregarPartida = (detalleId: number) => {
        const objetivo = partidasAgregables.find((c) => c.detalle.id === detalleId);
        if (!objetivo?.cot) return;
        router.post(
            '/admin/costos/requisiciones/selecciones',
            { cotizacion_precio_id: objetivo.cot.id, cantidad: objetivo.restante, numero_oc: numeroOc },
            { preserveScroll: true, onSuccess: () => { if (!hasLineas) onConverted?.(); } },
        );
    };

    // ── Pagos múltiples (parcialidades) ──
    const sumaPagos = pagos.reduce((s, p) => s + Number(p.porcentaje || 0), 0);
    const pagosValidos = pagos.length === 0 || Math.abs(sumaPagos - 100) <= 0.01;

    const setPagoCampo = (idx: number, campo: 'porcentaje' | 'concepto', valor: string) => {
        setPagos((prev) => prev.map((p, i) => (i === idx ? { ...p, [campo]: campo === 'porcentaje' ? Number(valor) : valor } : p)));
    };
    const guardarPagos = (lista: CostosRequisicionOcPago[]) => {
        if (lista.length === 0 || Math.abs(lista.reduce((s, p) => s + Number(p.porcentaje || 0), 0) - 100) <= 0.01) {
            persistMeta({ pagos: lista });
        }
    };
    const activarParcialidades = () => {
        const seed: CostosRequisicionOcPago[] = [
            { porcentaje: 50, concepto: 'Anticipo' },
            { porcentaje: 50, concepto: 'Contra entrega' },
        ];
        setPagos(seed);
        guardarPagos(seed);
    };
    const desactivarParcialidades = () => { setPagos([]); guardarPagos([]); };
    const agregarPago = () => setPagos((prev) => [...prev, { porcentaje: 0, concepto: '' }]);
    const quitarPago = (idx: number) => { const lista = pagos.filter((_, i) => i !== idx); setPagos(lista); guardarPagos(lista); };

    return (
        <div className="overflow-hidden rounded-lg border border-base-300">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 bg-base-200/40 px-3 py-2">
                <div className="flex items-center gap-2 text-sm font-semibold">
                    {hasLineas || !editable ? (
                        <span>{proveedor?.razon_social ?? `#${proveedorId}`}</span>
                    ) : (
                        <select
                            className="select select-bordered select-xs w-56"
                            value={proveedorId}
                            onChange={(e) => onPickProveedor?.(e.target.value ? Number(e.target.value) : null)}
                        >
                            {proveedoresCotizadores.map((p) => (
                                <option key={p.id} value={p.id}>{p.razon_social}</option>
                            ))}
                        </select>
                    )}
                    <span className="text-base-content/60">· OC-{numeroOc}</span>
                    {monedaConflicto ? (
                        <span className="badge badge-error badge-sm">Monedas mezcladas</span>
                    ) : (
                        <span className="badge badge-ghost badge-sm">{TIPO_MONEDA_LABELS[moneda]}</span>
                    )}
                </div>
                <div className="flex items-center gap-3 text-xs">
                    <label className="cursor-pointer">
                        <input
                            type="radio"
                            className="mr-1"
                            name={`pay-${proveedorId}-${numeroOc}`}
                            checked={modoPago === 'contado'}
                            disabled={!editable}
                            onChange={() => cambiarModoPago('contado')}
                        />
                        Contado
                    </label>
                    {manejaCredito && (
                        <label className="cursor-pointer">
                            <input
                                type="radio"
                                className="mr-1"
                                name={`pay-${proveedorId}-${numeroOc}`}
                                checked={modoPago === 'credito'}
                                disabled={!editable}
                                onChange={() => cambiarModoPago('credito')}
                            />
                            Crédito
                        </label>
                    )}
                    {editable && isDraft && onRemoveDraft && (
                        <button type="button" className="btn btn-ghost btn-xs text-error" onClick={onRemoveDraft} title="Quitar OC">
                            <Trash2Icon className="size-3" />
                        </button>
                    )}
                </div>
            </div>

            {monedaConflicto && (
                <div className="alert alert-error rounded-none text-xs">
                    <span>Esta OC mezcla monedas. Separa las partidas por moneda en OCs distintas para poder liberar.</span>
                </div>
            )}

            <div className="px-3 py-2">
                <div className="grid grid-cols-[1fr_70px_90px_90px_30px] gap-2 border-b border-base-200 pb-1 text-[10px] uppercase tracking-wider text-base-content/60">
                    <div>Concepto</div>
                    <div className="text-right">Cant.</div>
                    <div className="text-right">P. unit</div>
                    <div className="text-right">Subtotal</div>
                    <div></div>
                </div>

                {lineas.length === 0 ? (
                    <div className="py-2 text-xs text-base-content/50">Sin partidas. Agrega abajo.</div>
                ) : (
                    lineas.map((l) => (
                        <OcLinea
                            key={`${l.seleccion_id}-${l.cantidad}-${l.cotizacion_precio_id}`}
                            linea={l}
                            requisicionDetalleId={l.detalle.id}
                            proveedorId={proveedorId}
                            editable={editable}
                        />
                    ))
                )}

                {editable && partidasAgregables.length > 0 && (
                    <div className="mt-2">
                        <select
                            className="select select-bordered select-xs w-full max-w-md"
                            value=""
                            onChange={(e) => { if (e.target.value) agregarPartida(Number(e.target.value)); }}
                        >
                            <option value="">+ Agregar partida a esta OC...</option>
                            {partidasAgregables.map((c) => (
                                <option key={c.detalle.id} value={c.detalle.id}>
                                    {c.detalle.descripcion} · faltan {c.restante.toLocaleString('es-MX')} {c.detalle.unidad} · {fmt(Number(c.cot!.precio_unitario))}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <div className="mt-2 grid grid-cols-[1fr_90px] gap-2 border-t border-base-200 pt-1 text-xs">
                    <div className="text-base-content/60">Subtotal</div>
                    <div className="text-right">{fmt(subtotal)}</div>
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
                            <div className="text-right font-semibold">{fmt(total - totalRet)}</div>
                        </>
                    )}
                </div>

                <div className="mt-3 grid grid-cols-1 gap-2 border-t border-base-200 pt-2 md:grid-cols-[200px_1fr]">
                    <div>
                        <label className="label-text text-[10px] uppercase tracking-wider text-base-content/60">Fecha de entrega</label>
                        <input
                            type="date"
                            className="input input-bordered input-xs w-full"
                            value={fecha}
                            disabled={!editable}
                            onChange={(e) => setFecha(e.target.value)}
                            onBlur={() => persistMeta({})}
                        />
                    </div>
                    <div>
                        <label className="label-text text-[10px] uppercase tracking-wider text-base-content/60">Notas para esta OC</label>
                        <input
                            type="text"
                            className="input input-bordered input-xs w-full"
                            value={notas}
                            disabled={!editable}
                            maxLength={1000}
                            placeholder="Opcional. Instrucciones para el proveedor"
                            onChange={(e) => setNotas(e.target.value)}
                            onBlur={() => persistMeta({})}
                        />
                    </div>
                </div>

                {modoPago === 'contado' && (
                    <div className="mt-3 border-t border-base-200 pt-2">
                        <div className="mb-2 flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <label className="text-[10px] uppercase tracking-wider text-base-content/60">Método de pago</label>
                                <select
                                    className="select select-bordered select-xs w-40"
                                    value={metodoPago}
                                    disabled={!editable}
                                    onChange={(e) => {
                                        const m = e.target.value as 'transferencia' | 'cheque' | 'efectivo';
                                        setMetodoPago(m);
                                        persistMeta({ metodo_pago: m });
                                    }}
                                >
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="efectivo">Efectivo</option>
                                </select>
                            </div>
                            <div className="flex items-center gap-2">
                                <label className="text-[10px] uppercase tracking-wider text-base-content/60">Fecha de pago</label>
                                <input
                                    type="date"
                                    className="input input-bordered input-xs w-40"
                                    value={fechaPago}
                                    disabled={!editable}
                                    onChange={(e) => setFechaPago(e.target.value)}
                                    onBlur={() => persistMeta({})}
                                    title="Fecha solicitada de pago del anticipo (opcional; si se deja vacía, usa la fecha del día al liberar)"
                                />
                            </div>
                        </div>
                        {pagos.length === 0 ? (
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <input type="checkbox" className="checkbox checkbox-xs" disabled={!editable} checked={false} onChange={activarParcialidades} />
                                Pagar en parcialidades (varios pagos)
                            </label>
                        ) : (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] uppercase tracking-wider text-base-content/60">Parcialidades de pago</span>
                                    <button type="button" className="link link-error text-[11px]" onClick={desactivarParcialidades} disabled={!editable}>
                                        Quitar parcialidades
                                    </button>
                                </div>
                                {pagos.map((p, idx) => (
                                    <div key={idx} className="grid grid-cols-[70px_1fr_90px_30px] items-center gap-2 text-xs">
                                        <div className="flex items-center gap-1">
                                            <input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                max={100}
                                                className="input input-bordered input-xs w-14 text-right"
                                                value={p.porcentaje}
                                                disabled={!editable}
                                                onChange={(e) => setPagoCampo(idx, 'porcentaje', e.target.value)}
                                                onBlur={() => guardarPagos(pagos)}
                                            />
                                            <span>%</span>
                                        </div>
                                        <input
                                            type="text"
                                            className="input input-bordered input-xs w-full"
                                            placeholder="Concepto (ej. anticipo)"
                                            value={p.concepto ?? ''}
                                            disabled={!editable}
                                            maxLength={120}
                                            onChange={(e) => setPagoCampo(idx, 'concepto', e.target.value)}
                                            onBlur={() => guardarPagos(pagos)}
                                        />
                                        <div className="text-right text-base-content/70">{fmt(total * Number(p.porcentaje || 0) / 100)}</div>
                                        <div className="flex justify-end">
                                            {editable && (
                                                <button type="button" className="btn btn-ghost btn-xs text-error" onClick={() => quitarPago(idx)} title="Quitar pago">
                                                    <Trash2Icon className="size-3" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between">
                                    {editable && (
                                        <button type="button" className="link link-primary text-[11px]" onClick={agregarPago}>
                                            + Agregar pago
                                        </button>
                                    )}
                                    <span className={`text-[11px] font-medium ${pagosValidos ? 'text-success' : 'text-error'}`}>
                                        Suma: {sumaPagos.toFixed(2)}%{pagosValidos ? ' ✓' : ' (debe ser 100%)'}
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                <div className="mt-2 flex items-center justify-between border-t border-dashed border-base-200 pt-2 text-sm font-semibold">
                    <div>Total</div>
                    <div>
                        {fmt(total)}{' '}
                        <span className={`ml-1 rounded px-2 py-0.5 text-[10px] ${modoPago === 'credito' ? 'bg-warning/20 text-warning' : 'bg-success/20 text-success'}`}>
                            {modoPago === 'credito' ? 'CRÉDITO' : pagos.length > 0 ? `CONTADO · ${pagos.length} PAGOS` : 'CONTADO'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function OcLinea({
    linea,
    requisicionDetalleId,
    proveedorId,
    editable,
}: {
    linea: Linea;
    requisicionDetalleId: number;
    proveedorId: number;
    editable: boolean;
}) {
    const [cantidad, setCantidad] = useState(String(linea.cantidad));
    const [codigo, setCodigo] = useState(linea.detalle.cotizaciones?.find((c) => c.proveedor_id === proveedorId)?.codigo_producto ?? '');

    const guardarCantidad = () => {
        const c = Number(cantidad);
        if (!Number.isFinite(c) || c === linea.cantidad) return;
        router.patch(`/admin/costos/requisiciones/selecciones/${linea.seleccion_id}`, { cantidad: c }, { preserveScroll: true });
    };

    const guardarCodigo = () => {
        const cot = linea.detalle.cotizaciones?.find((c) => c.proveedor_id === proveedorId);
        if (!cot || codigo === (cot.codigo_producto ?? '')) return;
        router.post(
            '/admin/costos/requisiciones/cotizaciones',
            { requisicion_detalle_id: requisicionDetalleId, proveedor_id: proveedorId, precio_unitario: linea.precio_unitario, codigo_producto: codigo || null },
            { preserveScroll: true },
        );
    };

    const eliminar = () => router.delete(`/admin/costos/requisiciones/selecciones/${linea.seleccion_id}`, { preserveScroll: true });

    return (
        <div className="grid grid-cols-[1fr_70px_90px_90px_30px] items-center gap-2 py-1 text-xs">
            <div>
                <div>{linea.detalle.descripcion}</div>
                <input
                    type="text"
                    className="input input-bordered input-xs mt-1 w-40"
                    value={codigo}
                    disabled={!editable}
                    placeholder="Código producto"
                    onChange={(e) => setCodigo(e.target.value)}
                    onBlur={guardarCodigo}
                />
            </div>
            <input
                type="number"
                step="0.01"
                min={0}
                className="input input-bordered input-xs w-full text-right"
                value={cantidad}
                disabled={!editable}
                onChange={(e) => setCantidad(e.target.value)}
                onBlur={guardarCantidad}
            />
            <div className="text-right">{fmt(linea.precio_unitario)}</div>
            <div className="text-right">{fmt(linea.precio_unitario * Number(cantidad || 0))}</div>
            <div className="flex justify-end">
                {editable && (
                    <button type="button" className="btn btn-ghost btn-xs text-error" onClick={eliminar} title="Quitar partida">
                        <Trash2Icon className="size-3" />
                    </button>
                )}
            </div>
        </div>
    );
}
