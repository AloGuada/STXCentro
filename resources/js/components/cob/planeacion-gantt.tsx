import { router } from '@inertiajs/react';
import { Loader2Icon, PencilIcon, PlusIcon, SaveIcon, XIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { EstadoBadge } from '@/components/cob/estado-badge';
import { formatearMXN } from '@/components/cob/money-display';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { CobEstimacion, CobEstimacionEstado, CobEstimacionEstadoHistorial, Proyecto } from '@/types/models';

/**
 * Gantt único del proyecto, en una sola tabla con escala semanal compartida:
 *  - PMO   — etapas planeadas por obra (arrastrables).
 *  - Plan  — estimaciones planeadas del plan de cobro (arrastrables).
 *  - Real  — estimaciones por estado (del historial, solo lectura).
 * Alta por modales; arrastre (mover/redimensionar) + "Guardar cambios".
 */

const DAY = 86_400_000;
const ROW_HEIGHT = 32;
const BAR_HEIGHT = 20;
const WEEK_COL_PX = 70;

const ESTADO_COLORS: Record<CobEstimacionEstado, string> = {
    ingresada: 'bg-amber-200 text-black',
    autorizada: 'bg-blue-400 text-white',
    facturada: 'bg-pink-400 text-white',
    pago_parcial: 'bg-orange-400 text-black',
    pagado: 'bg-green-400 text-black',
};
const ESTADO_LABELS: Record<CobEstimacionEstado, string> = {
    ingresada: 'Ingresada', autorizada: 'Autorizada', facturada: 'Facturada',
    pago_parcial: 'Pago parcial', pagado: 'Pagado',
};

function parse(s: string | null | undefined): Date | null {
    if (!s) return null;
    const [y, m, d] = s.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
}
function addDays(d: Date, n: number): Date {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
}
function daysBetween(a: Date, b: Date): number {
    return Math.round((b.getTime() - a.getTime()) / DAY);
}
function iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function fmt(d: Date): string {
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: '2-digit' });
}
function getWeekNumber(d: Date): number {
    const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    t.setUTCDate(t.getUTCDate() + 4 - (t.getUTCDay() || 7));
    const ys = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
    return Math.ceil(((t.getTime() - ys.getTime()) / DAY + 1) / 7);
}

type StatePeriod = { estado: CobEstimacionEstado; start: Date; end: Date };
function buildStatePeriods(historial: CobEstimacionEstadoHistorial[]): StatePeriod[] {
    if (historial.length === 0) return [];
    const sorted = [...historial].sort((a, b) => new Date(a.fecha_cambio).getTime() - new Date(b.fecha_cambio).getTime());
    return sorted.map((entry, i) => ({
        estado: entry.estado_nuevo as CobEstimacionEstado,
        start: new Date(entry.fecha_cambio),
        end: sorted[i + 1] ? new Date(sorted[i + 1].fecha_cambio) : new Date(),
    }));
}

function etapaColor(desc: string): string {
    const d = desc.toLowerCase();
    if (d.includes('sumin')) return 'bg-blue-400 text-white';
    if (d.includes('mont')) return 'bg-orange-400 text-black';
    return 'bg-info text-white';
}

type PlanItem = { orden: number; inicio: Date; fin: Date };
type EtapaItem = { id: number; obraNo: string; descripcion: string; inicio: Date; fin: Date };
type RealRow = { estimacion: CobEstimacion; obraNo: string; periods: StatePeriod[] };
type Drag = { mode: 'move' | 'l' | 'r'; startX: number; startInicio: Date; startFin: Date; apply: (inicio: Date, fin: Date) => void };

const COLS = 4; // Cliente, Obra, Concepto, Monto

export function PlaneacionGantt({ proyecto }: { proyecto: Proyecto }) {
    const obras = proyecto.obras ?? [];
    const planStart = parse(proyecto.fecha_inicio_plan) ?? new Date();

    const [planItems, setPlanItems] = useState<PlanItem[]>([]);
    const [etapaItems, setEtapaItems] = useState<EtapaItem[]>([]);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [planModal, setPlanModal] = useState(false);
    const [mFecha, setMFecha] = useState(proyecto.fecha_inicio_plan?.slice(0, 10) ?? '');
    const [mNumero, setMNumero] = useState(3);
    const [mDias, setMDias] = useState(30);
    const [mSaving, setMSaving] = useState(false);

    const [etapaModal, setEtapaModal] = useState(false);
    const [eObra, setEObra] = useState('');
    const [eDescripcion, setEDescripcion] = useState('');
    const [eInicio, setEInicio] = useState('');
    const [eFin, setEFin] = useState('');
    const [eSaving, setESaving] = useState(false);

    useEffect(() => {
        // Re-sincroniza el estado editable con las props tras carga/recarga de Inertia.
        /* eslint-disable react-hooks/set-state-in-effect */
        setPlanItems((proyecto.plan_cobro ?? []).map((p) => ({ orden: p.orden, inicio: parse(p.fecha_inicio_plan)!, fin: parse(p.fecha_fin_plan)! })));
        setEtapaItems(
            (proyecto.obras ?? []).flatMap((o) =>
                (o.etapas_pmo ?? [])
                    .filter((e) => e.fecha_inicio_plan && e.fecha_fin_plan)
                    .map((e) => ({ id: e.id, obraNo: o.no, descripcion: e.descripcion, inicio: parse(e.fecha_inicio_plan)!, fin: parse(e.fecha_fin_plan)! })),
            ),
        );
        setDirty(false);
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [proyecto]);

    const realRows = useMemo<RealRow[]>(
        () =>
            (proyecto.obras ?? [])
                .flatMap((o) => (o.estimaciones ?? []).map((estimacion) => ({ estimacion, obraNo: o.no })))
                .filter((r) => r.estimacion.historial && r.estimacion.historial.length > 0)
                .map((r) => ({ ...r, periods: buildStatePeriods(r.estimacion.historial!) }))
                .filter((r) => r.periods.length > 0)
                .sort((a, b) => a.estimacion.numero_estimacion - b.estimacion.numero_estimacion),
        [proyecto],
    );

    // Escala común a partir de la unión de todas las barras.
    const { minDate, totalDays } = useMemo(() => {
        const dates: number[] = [];
        for (const p of planItems) dates.push(p.inicio.getTime(), p.fin.getTime());
        for (const e of etapaItems) dates.push(e.inicio.getTime(), e.fin.getTime());
        for (const r of realRows) for (const p of r.periods) dates.push(p.start.getTime(), p.end.getTime());
        if (dates.length === 0) {
            const now = new Date();
            return { minDate: addDays(now, -7), totalDays: 60 };
        }
        const min = new Date(Math.min(...dates) - 7 * DAY);
        const max = new Date(Math.max(...dates) + 7 * DAY);
        return { minDate: min, totalDays: Math.max(daysBetween(min, max), 1) };
    }, [planItems, etapaItems, realRows]);

    const weekMarkers = useMemo(() => {
        const out: { pct: number; week: number; date: Date }[] = [];
        const d = new Date(minDate);
        d.setDate(d.getDate() + ((8 - d.getDay()) % 7));
        while (d.getTime() <= minDate.getTime() + totalDays * DAY) {
            out.push({ pct: (daysBetween(minDate, d) / totalDays) * 100, week: getWeekNumber(d), date: new Date(d) });
            d.setDate(d.getDate() + 7);
        }
        return out;
    }, [minDate, totalDays]);

    const monthMarkers = useMemo(() => {
        const out: { label: string; pct: number; widthPct: number }[] = [];
        const end = new Date(minDate.getTime() + totalDays * DAY);
        const d = new Date(minDate.getFullYear(), minDate.getMonth(), 1);
        while (d <= end) {
            const ms = new Date(Math.max(d.getTime(), minDate.getTime()));
            const nm = new Date(d.getFullYear(), d.getMonth() + 1, 1);
            const me = new Date(Math.min(nm.getTime(), end.getTime()));
            out.push({
                label: d.toLocaleDateString('es-MX', { month: 'short', year: '2-digit' }),
                pct: (daysBetween(minDate, ms) / totalDays) * 100,
                widthPct: (daysBetween(ms, me) / totalDays) * 100,
            });
            d.setMonth(d.getMonth() + 1);
        }
        return out;
    }, [minDate, totalDays]);

    const timelineWidth = Math.max(weekMarkers.length * WEEK_COL_PX, 400);
    const pxPorDiaRef = useRef(1);
    useEffect(() => {
        pxPorDiaRef.current = timelineWidth / totalDays;
    }, [timelineWidth, totalDays]);

    // -- Drag --
    const dragRef = useRef<Drag | null>(null);
    useEffect(() => {
        const onMove = (e: PointerEvent) => {
            const d = dragRef.current;
            if (!d) return;
            const delta = Math.round((e.clientX - d.startX) / pxPorDiaRef.current);
            let inicio = d.startInicio;
            let fin = d.startFin;
            if (d.mode === 'move') {
                inicio = addDays(d.startInicio, delta);
                fin = addDays(d.startFin, delta);
            } else if (d.mode === 'l') {
                inicio = addDays(d.startInicio, delta);
                if (inicio >= fin) inicio = addDays(fin, -1);
            } else {
                fin = addDays(d.startFin, delta);
                if (fin <= inicio) fin = addDays(inicio, 1);
            }
            d.apply(inicio, fin);
        };
        const onUp = () => {
            dragRef.current = null;
        };
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
    }, []);

    const startDrag = (e: React.PointerEvent, mode: Drag['mode'], inicio: Date, fin: Date, apply: Drag['apply']) => {
        e.preventDefault();
        e.stopPropagation();
        dragRef.current = { mode, startX: e.clientX, startInicio: inicio, startFin: fin, apply };
        setDirty(true);
    };

    const applyPlan = (orden: number) => (inicio: Date, fin: Date) => setPlanItems((prev) => prev.map((p) => (p.orden === orden ? { ...p, inicio, fin } : p)));
    const applyEtapa = (id: number) => (inicio: Date, fin: Date) => setEtapaItems((prev) => prev.map((et) => (et.id === id ? { ...et, inicio, fin } : et)));

    const guardar = () => {
        setSaving(true);
        router.put(
            `/admin/cob/proyectos/${proyecto.id}/planeacion`,
            {
                plan: planItems.map((p) => ({ orden: p.orden, fecha_inicio_plan: iso(p.inicio), fecha_fin_plan: iso(p.fin) })),
                etapas: etapaItems.map((e) => ({ id: e.id, fecha_inicio_plan: iso(e.inicio), fecha_fin_plan: iso(e.fin) })),
            },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };
    const regenerar = () => {
        setMSaving(true);
        router.put(
            `/admin/cob/proyectos/${proyecto.id}/plan-cobro`,
            { fecha_inicio_plan: mFecha, numero: mNumero, dias: mDias },
            { preserveScroll: true, onSuccess: () => setPlanModal(false), onFinish: () => setMSaving(false) },
        );
    };
    const abrirEtapaModal = () => {
        setEObra(obras[0] ? String(obras[0].id) : '');
        setEDescripcion('');
        setEInicio(proyecto.fecha_inicio_plan?.slice(0, 10) ?? iso(planStart));
        setEFin(iso(addDays(planStart, 14)));
        setEtapaModal(true);
    };
    const crearEtapa = () => {
        setESaving(true);
        router.post(
            `/admin/cob/proyectos/${proyecto.id}/etapas`,
            { obra_id: Number(eObra), descripcion: eDescripcion, fecha_inicio_plan: eInicio, fecha_fin_plan: eFin },
            { preserveScroll: true, onSuccess: () => setEtapaModal(false), onFinish: () => setESaving(false) },
        );
    };
    const eliminarEtapa = (id: number) => {
        if (!window.confirm('¿Eliminar esta etapa?')) return;
        router.delete(`/admin/cob/proyectos/${proyecto.id}/etapas/${id}`, { preserveScroll: true });
    };

    const pctOf = (inicio: Date, fin: Date) => ({
        left: `${(daysBetween(minDate, inicio) / totalDays) * 100}%`,
        width: `${(Math.max(daysBetween(inicio, fin), 1) / totalDays) * 100}%`,
    });

    const weekGrid = (
        <>
            {weekMarkers.map((m, i) => (
                <div key={i} className="absolute top-0 bottom-0 border-l border-base-300/30" style={{ left: `${m.pct}%` }} />
            ))}
        </>
    );

    const dragBar =(inicio: Date, fin: Date, color: string, label: string, apply: Drag['apply'], onDelete?: () => void) => (
        <div
            className={`group absolute flex items-center overflow-hidden rounded shadow ${color}`}
            style={{ ...pctOf(inicio, fin), minWidth: 22, top: (ROW_HEIGHT - BAR_HEIGHT) / 2, height: BAR_HEIGHT, cursor: 'grab' }}
            title={`${label}: ${fmt(inicio)} → ${fmt(fin)}`}
            onPointerDown={(e) => startDrag(e, 'move', inicio, fin, apply)}
        >
            <span className="absolute left-0 top-0 h-full w-1.5 cursor-ew-resize bg-black/20" onPointerDown={(e) => startDrag(e, 'l', inicio, fin, apply)} />
            <span className="pointer-events-none truncate px-2 text-[9px] font-medium">{label}</span>
            {onDelete && (
                <button
                    type="button"
                    className="absolute right-2 hidden group-hover:block"
                    onPointerDown={(e) => e.stopPropagation()}
                    onClick={onDelete}
                    title="Eliminar"
                >
                    <XIcon className="size-3" />
                </button>
            )}
            <span className="absolute right-0 top-0 h-full w-1.5 cursor-ew-resize bg-black/20" onPointerDown={(e) => startDrag(e, 'r', inicio, fin, apply)} />
        </div>
    );

    const timelineCell = (children: React.ReactNode) => (
        <td className="!p-0">
            <div className="relative" style={{ height: ROW_HEIGHT, minWidth: timelineWidth }}>
                {weekGrid}
                {children}
            </div>
        </td>
    );

    // Columnas izquierdas fijas (sticky) con anchos exactos para que se alineen.
    const STICKY = [
        { left: 0, w: 150 }, // Cliente
        { left: 150, w: 90 }, // Obra
        { left: 240, w: 170 }, // Concepto
        { left: 410, w: 110 }, // Monto
    ];
    const sc = (i: number, header = false, extra = ''): { className: string; style: React.CSSProperties } => ({
        className: `sticky bg-base-100 ${header ? 'z-20' : 'z-10'} ${i === 3 ? 'border-r' : ''} ${extra}`,
        style: { left: STICKY[i].left, minWidth: STICKY[i].w, maxWidth: STICKY[i].w, width: STICKY[i].w },
    });

    const clienteNombre = proyecto.cliente?.nombre ?? '-';
    let primeraFila = true;
    const clienteCell = () => {
        const props = sc(0, false, 'truncate align-top');
        const cell = <td className={props.className} style={props.style} title={clienteNombre}>{primeraFila ? clienteNombre : ''}</td>;
        primeraFila = false;
        return cell;
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
                <div className="flex flex-wrap gap-2 text-xs">
                    {(Object.entries(ESTADO_COLORS) as [CobEstimacionEstado, string][]).map(([estado, color]) => (
                        <div key={estado} className={`rounded px-2 py-0.5 ${color}`}>{ESTADO_LABELS[estado]}</div>
                    ))}
                </div>
                <div className="flex items-center gap-2">
                    {dirty && (
                        <Button size="sm" onClick={guardar} disabled={saving}>
                            {saving ? <Loader2Icon className="size-4 animate-spin" /> : <SaveIcon className="size-4" />} Guardar cambios
                        </Button>
                    )}
                    <Button size="sm" variant="outline" onClick={abrirEtapaModal}><PlusIcon className="size-4" /> Etapa PMO</Button>
                    <Button size="sm" variant="outline" onClick={() => setPlanModal(true)}><PencilIcon className="size-4" /> Plan de cobro</Button>
                </div>
            </div>

            <div className="max-h-[calc(100vh-12rem)] overflow-auto rounded-box border border-base-300">
                <table className="table table-zebra whitespace-nowrap text-xs">
                    <thead className="sticky top-0 z-30 bg-base-100">
                        <tr>
                            <th rowSpan={2} {...sc(0, true)}>Cliente</th>
                            <th rowSpan={2} {...sc(1, true)}>Obra</th>
                            <th rowSpan={2} {...sc(2, true)}>Concepto</th>
                            <th rowSpan={2} {...sc(3, true, 'text-right')}>Monto</th>
                            <th className="!p-0" style={{ minWidth: timelineWidth }}>
                                <div className="relative h-5 w-full" style={{ minWidth: timelineWidth }}>
                                    {monthMarkers.map((m, i) => (
                                        <div key={i} className="absolute top-0 flex h-full items-center overflow-hidden border-l border-base-300/50 px-1 text-[10px] font-semibold" style={{ left: `${m.pct}%`, width: `${m.widthPct}%` }}>{m.label}</div>
                                    ))}
                                </div>
                            </th>
                        </tr>
                        <tr>
                            <th className="!p-0" style={{ minWidth: timelineWidth }}>
                                <div className="relative h-4 w-full" style={{ minWidth: timelineWidth }}>
                                    {weekMarkers.map((m, i) => (
                                        <div key={i} title={fmt(m.date)} className="absolute top-0 flex h-full items-center justify-center overflow-hidden border-l border-base-300/40 text-[9px] text-base-content/50" style={{ left: `${m.pct}%`, width: `${(7 / totalDays) * 100}%` }}>S{m.week}</div>
                                    ))}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {/* PMO */}
                        {etapaItems.length === 0 ? (
                            <tr><td className="sticky left-0 bg-base-100" colSpan={COLS}>—</td>{timelineCell(<span className="px-2 text-[10px] leading-8 opacity-40">Sin etapas — usa "Etapa PMO"</span>)}</tr>
                        ) : (
                            etapaItems.map((et) => (
                                <tr key={`et-${et.id}`} className="hover">
                                    {clienteCell()}
                                    <td {...sc(1, false, 'truncate')} title={et.obraNo}>{et.obraNo}</td>
                                    <td {...sc(2, false, 'truncate')} title={et.descripcion}>{et.descripcion}</td>
                                    <td {...sc(3, false, 'text-right opacity-50')}>—</td>
                                    {timelineCell(dragBar(et.inicio, et.fin, etapaColor(et.descripcion), et.descripcion, applyEtapa(et.id), () => eliminarEtapa(et.id)))}
                                </tr>
                            ))
                        )}

                        {/* Plan */}
                        {planItems.length === 0 ? (
                            <tr><td className="sticky left-0 bg-base-100" colSpan={COLS}>—</td>{timelineCell(<span className="px-2 text-[10px] leading-8 opacity-40">Sin plan — usa "Plan de cobro"</span>)}</tr>
                        ) : (
                            planItems.map((p) => (
                                <tr key={`pl-${p.orden}`} className="hover">
                                    {clienteCell()}
                                    <td {...sc(1, false, 'opacity-50')}>—</td>
                                    <td {...sc(2, false, 'truncate')}>Estimación #{p.orden}</td>
                                    <td {...sc(3, false, 'text-right opacity-50')}>—</td>
                                    {timelineCell(dragBar(p.inicio, p.fin, 'bg-primary text-primary-content', `#${p.orden}`, applyPlan(p.orden)))}
                                </tr>
                            ))
                        )}

                        {/* Real */}
                        {realRows.length === 0 ? (
                            <tr><td className="sticky left-0 bg-base-100" colSpan={COLS}>—</td>{timelineCell(<span className="px-2 text-[10px] leading-8 opacity-40">Sin historial</span>)}</tr>
                        ) : (
                            realRows.map((row) => (
                                <tr key={`re-${row.estimacion.id}`} className="hover">
                                    {clienteCell()}
                                    <td {...sc(1, false, 'truncate')} title={row.obraNo}>{row.obraNo}</td>
                                    <td {...sc(2, false)}><span className="flex items-center gap-1">EST {row.estimacion.numero_estimacion} <EstadoBadge estado={row.estimacion.estado} /></span></td>
                                    <td {...sc(3, false, 'text-right')}>{formatearMXN(row.estimacion.monto_estimado)}</td>
                                    {timelineCell(
                                        row.periods.map((period, pIdx) => {
                                            const dur = Math.max(daysBetween(period.start, period.end), 1);
                                            return (
                                                <div
                                                    key={pIdx}
                                                    className={`group/bar absolute flex items-center justify-center rounded shadow text-[9px] font-medium ${ESTADO_COLORS[period.estado]}`}
                                                    style={{ ...pctOf(period.start, period.end), minWidth: 18, top: (ROW_HEIGHT - BAR_HEIGHT) / 2, height: BAR_HEIGHT }}
                                                    title={`${ESTADO_LABELS[period.estado]}: ${fmt(period.start)} → ${fmt(period.end)} (${dur} días)`}
                                                >
                                                    {(dur / totalDays) * 100 > 4 ? ESTADO_LABELS[period.estado] : ''}
                                                </div>
                                            );
                                        }),
                                    )}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Modal plan de cobro */}
            <Dialog open={planModal} onOpenChange={setPlanModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Definir plan de cobro</DialogTitle>
                        <DialogDescription>Genera N estimaciones de la misma duración; luego ajústalas arrastrando.</DialogDescription>
                    </DialogHeader>
                    <div className="grid grid-cols-3 gap-4">
                        <FormField label="Fecha inicial" htmlFor="m_fecha"><Input type="date" value={mFecha} onChange={(e) => setMFecha(e.target.value)} /></FormField>
                        <FormField label="N.º estimaciones" htmlFor="m_numero"><Input type="number" min={1} max={60} value={mNumero} onChange={(e) => setMNumero(Number(e.target.value))} /></FormField>
                        <FormField label="Días por estimación" htmlFor="m_dias"><Input type="number" min={1} value={mDias} onChange={(e) => setMDias(Number(e.target.value))} /></FormField>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPlanModal(false)}>Cancelar</Button>
                        <Button onClick={regenerar} disabled={mSaving || !mFecha || mNumero < 1 || mDias < 1}>
                            {mSaving && <Loader2Icon className="size-4 animate-spin" />} Generar plan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Modal etapa PMO */}
            <Dialog open={etapaModal} onOpenChange={setEtapaModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Nueva etapa PMO</DialogTitle>
                        <DialogDescription>Liga la etapa a una obra; al guardar aparece su barra en el Gantt.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <FormField label="Obra" htmlFor="e_obra">
                            <Select value={eObra} onValueChange={setEObra} placeholder="Seleccionar obra">
                                {obras.map((o) => (<SelectItem key={o.id} value={String(o.id)}>{o.no} — {o.descripcion}</SelectItem>))}
                            </Select>
                        </FormField>
                        <FormField label="Descripción" htmlFor="e_desc"><Input value={eDescripcion} onChange={(e) => setEDescripcion(e.target.value)} placeholder="Ej. Suministro, Montaje, Fabricación…" /></FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Fecha inicial" htmlFor="e_inicio"><Input type="date" value={eInicio} onChange={(e) => setEInicio(e.target.value)} /></FormField>
                            <FormField label="Fecha final" htmlFor="e_fin"><Input type="date" value={eFin} onChange={(e) => setEFin(e.target.value)} /></FormField>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEtapaModal(false)}>Cancelar</Button>
                        <Button onClick={crearEtapa} disabled={eSaving || !eObra || !eDescripcion || !eInicio || !eFin}>
                            {eSaving && <Loader2Icon className="size-4 animate-spin" />} Agregar etapa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
