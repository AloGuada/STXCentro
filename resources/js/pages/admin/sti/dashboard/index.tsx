import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/dashboard' },
    { title: 'Dashboard', href: '/admin/sti/dashboard' },
];

const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const C = {
    azul: '#3abff8',
    verde: '#36d399',
    rojo: '#f87272',
    amarillo: '#fbbd23',
    morado: '#a78bfa',
    naranja: '#fb923c',
};

const STAR_COLORS: Record<number, string> = {
    1: C.rojo,
    2: '#f97316',
    3: C.amarillo,
    4: '#84cc16',
    5: C.verde,
};

function semaforo(valor: number, max = 5): string {
    const pct = valor / max;
    if (pct >= 0.8) return C.verde;
    if (pct >= 0.6) return C.amarillo;
    return C.rojo;
}

function formatPeriodo(periodo: string): string {
    const [, mes] = periodo.split('-');
    return MESES[parseInt(mes, 10) - 1] ?? periodo;
}

function StarRating({ value, max = 5 }: { value: number; max?: number }) {
    return (
        <span className="text-warning">
            {Array.from({ length: max }, (_, i) => (
                <span key={i} className={i < Math.round(value) ? 'opacity-100' : 'opacity-20'}>
                    ★
                </span>
            ))}
        </span>
    );
}

function EmptyChart({ label = 'Sin datos disponibles' }: { label?: string }) {
    return (
        <div className="text-base-content/50 flex h-48 items-center justify-center">
            <p className="text-sm">{label}</p>
        </div>
    );
}

type Kpis = {
    total: number;
    sin_asignar: number;
    en_proceso: number;
    completados: number;
    tasa_resolucion: number;
    promedio_satisfaccion: number;
};

type TicketPorTecnico = { tecnico_id: number; tecnico: string; total: number; completados: number; activos: number };
type TendenciaMensual = { periodo: string; total: number };
type TicketPorDepartamento = { departamento_id: number; departamento: string; total: number };
type DistribucionCalificacion = { estrellas: number; label: string; total: number };
type SatisfaccionPorDepartamento = { departamento_id: number; departamento: string; promedio: number; total: number };
type CalificacionPorTecnico = { tecnico_id: number; tecnico: string; promedio: number; total: number };

type TiemposPorTecnico = { tecnico: string; promedio_total: number; promedio_activo: number; promedio_detenido: number };
type EstadoDetencion = { estado: string; promedio_h: number; ocurrencias: number };
type Tiempos = {
    promedio_total: number;
    promedio_activo: number;
    promedio_detenido: number;
    total_analizados: number;
    por_tecnico: TiemposPorTecnico[];
    estados_detencion: EstadoDetencion[];
};

type Props = {
    kpis: Kpis;
    tickets_por_tecnico: TicketPorTecnico[];
    tendencia_mensual: TendenciaMensual[];
    tickets_por_departamento: TicketPorDepartamento[];
    distribucion_calificaciones: DistribucionCalificacion[];
    satisfaccion_por_departamento: SatisfaccionPorDepartamento[];
    calificaciones_por_tecnico: CalificacionPorTecnico[];
    tiempos: Tiempos;
};

function formatHoras(h: number): string {
    if (h === 0) return '0h';
    if (h < 1) return `${Math.round(h * 60)}m`;
    const hrs = Math.floor(h);
    const mins = Math.round((h - hrs) * 60);
    return mins === 0 ? `${hrs}h` : `${hrs}h ${mins}m`;
}

type Tab = 'general' | 'tecnicos' | 'tiempos';

export default function StiDashboardIndex({
    kpis,
    tickets_por_tecnico,
    tendencia_mensual,
    tickets_por_departamento,
    distribucion_calificaciones,
    satisfaccion_por_departamento,
    calificaciones_por_tecnico,
    tiempos,
}: Props) {
    const [tab, setTab] = useState<Tab>('general');

    const goToTickets = (params: Record<string, string | number>) => {
        const query = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => query.set(k, String(v)));
        router.visit(`/admin/sti/tickets?${query.toString()}`);
    };

    // Tabla resumen por técnico: fusiona tickets + calificaciones
    const resumenTecnicos = tickets_por_tecnico.map((t) => {
        const calif = calificaciones_por_tecnico.find((c) => c.tecnico === t.tecnico);
        return {
            tecnico: t.tecnico,
            total: t.total,
            completados: t.completados,
            activos: t.activos,
            tasa: t.total > 0 ? Math.round((t.completados / t.total) * 100) : 0,
            promedio: calif?.promedio ?? null,
            califTotal: calif?.total ?? 0,
        };
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard STI" />

            <div className="space-y-6 p-6">
                {/* ── Encabezado ── */}
                <div>
                    <h1 className="text-2xl font-semibold">Dashboard — Soporte TI</h1>
                    <p className="text-base-content/50 mt-1 text-sm">Atención al cliente e indicadores de calidad</p>
                </div>

                {/* ── KPI Cards (siempre visibles) ── */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <div className="bg-base-100 border-base-300 rounded-box cursor-pointer border p-4 transition-shadow hover:shadow-md" onClick={() => router.visit('/admin/sti/tickets')}>
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">Total tickets</p>
                        <p className="mt-1 text-3xl font-bold">{kpis.total}</p>
                    </div>
                    <div className="bg-base-100 border-base-300 rounded-box cursor-pointer border p-4 transition-shadow hover:shadow-md" onClick={() => goToTickets({ estado: 'sin_asignar' })}>
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">Sin asignar</p>
                        <p className="text-error mt-1 text-3xl font-bold">{kpis.sin_asignar}</p>
                    </div>
                    <div className="bg-base-100 border-base-300 rounded-box cursor-pointer border p-4 transition-shadow hover:shadow-md" onClick={() => goToTickets({ estado: 'en_proceso' })}>
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">En proceso</p>
                        <p className="text-warning mt-1 text-3xl font-bold">{kpis.en_proceso}</p>
                    </div>
                    <div className="bg-base-100 border-base-300 rounded-box cursor-pointer border p-4 transition-shadow hover:shadow-md" onClick={() => goToTickets({ estado: 'completados' })}>
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">Completados</p>
                        <p className="text-success mt-1 text-3xl font-bold">{kpis.completados}</p>
                    </div>
                    <div className="bg-base-100 border-base-300 rounded-box border p-4">
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">Tasa resolución</p>
                        <p className="text-success mt-1 text-3xl font-bold">{kpis.tasa_resolucion}%</p>
                    </div>
                    <div className="bg-base-100 border-base-300 rounded-box border p-4">
                        <p className="text-base-content/60 text-xs uppercase tracking-wide">Satisfacción</p>
                        <p className="mt-1 text-3xl font-bold">
                            {kpis.promedio_satisfaccion > 0 ? kpis.promedio_satisfaccion : '—'}
                            {kpis.promedio_satisfaccion > 0 && (
                                <span className="text-base-content/40 ml-1 text-base font-normal">/5</span>
                            )}
                        </p>
                        {kpis.promedio_satisfaccion > 0 && <StarRating value={kpis.promedio_satisfaccion} />}
                    </div>
                </div>

                {/* ── Tabs ── */}
                <div className="tabs tabs-boxed w-fit">
                    <button type="button" className={`tab ${tab === 'general' ? 'tab-active' : ''}`} onClick={() => setTab('general')}>
                        General
                    </button>
                    <button type="button" className={`tab ${tab === 'tecnicos' ? 'tab-active' : ''}`} onClick={() => setTab('tecnicos')}>
                        Por técnico
                    </button>
                    <button type="button" className={`tab ${tab === 'tiempos' ? 'tab-active' : ''}`} onClick={() => setTab('tiempos')}>
                        Tiempos de atención
                    </button>
                </div>

                {/* ── Tab: General ── */}
                {tab === 'general' && (
                    <div className="space-y-6">
                        {/* Fila 1: Departamentos + Distribución calificaciones */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <div className="bg-base-100 border-base-300 rounded-box border p-4 lg:col-span-2">
                                <h2 className="mb-4 text-base font-semibold">Tickets por departamento</h2>
                                {tickets_por_departamento.length === 0 ? (
                                    <EmptyChart />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={tickets_por_departamento} layout="vertical" margin={{ left: 8, right: 24 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} />
                                            <YAxis type="category" dataKey="departamento" width={140} tick={{ fontSize: 12 }} />
                                            <Tooltip formatter={(v) => [`${v} tickets`]} />
                                            <Bar dataKey="total" name="Tickets" radius={[0, 4, 4, 0]} cursor="pointer" onClick={(_, i) => goToTickets({ departamento_id: tickets_por_departamento[i].departamento_id })}>
                                                {tickets_por_departamento.map((_, i) => (
                                                    <Cell key={i} fill={Object.values(C)[i % Object.values(C).length]} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>

                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-4 text-base font-semibold">Distribución de calificaciones</h2>
                                {distribucion_calificaciones.length === 0 ? (
                                    <EmptyChart label="Aún no hay tickets calificados" />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={distribucion_calificaciones} margin={{ left: 0, right: 8 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                            <XAxis dataKey="label" tick={{ fontSize: 13 }} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip formatter={(v) => [`${v} tickets`]} />
                                            <Bar dataKey="total" name="Tickets" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(_, i) => goToTickets({ calificacion: distribucion_calificaciones[i].estrellas })}>
                                                {distribucion_calificaciones.map((d) => (
                                                    <Cell key={d.estrellas} fill={STAR_COLORS[d.estrellas] ?? C.azul} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        {/* Fila 2: Satisfacción por departamento + Tendencia mensual */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-4 text-base font-semibold">Satisfacción por departamento</h2>
                                {satisfaccion_por_departamento.length === 0 ? (
                                    <EmptyChart label="Aún no hay tickets calificados" />
                                ) : (
                                    <ResponsiveContainer width="100%" height={250}>
                                        <BarChart data={satisfaccion_por_departamento} layout="vertical" margin={{ left: 8, right: 32 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" domain={[0, 5]} ticks={[0, 1, 2, 3, 4, 5]} allowDecimals />
                                            <YAxis type="category" dataKey="departamento" width={140} tick={{ fontSize: 12 }} />
                                            <Tooltip formatter={(v, name) => [name === 'promedio' ? `${v} / 5` : `${v} tickets`]} />
                                            <Bar dataKey="promedio" name="Promedio" radius={[0, 4, 4, 0]} cursor="pointer" onClick={(_, i) => goToTickets({ departamento_id: satisfaccion_por_departamento[i].departamento_id })}>
                                                {satisfaccion_por_departamento.map((d, i) => (
                                                    <Cell key={i} fill={semaforo(d.promedio)} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>

                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-4 text-base font-semibold">Tendencia mensual (últimos 12 meses)</h2>
                                {tendencia_mensual.length === 0 ? (
                                    <EmptyChart />
                                ) : (
                                    <ResponsiveContainer width="100%" height={250}>
                                        <LineChart data={tendencia_mensual}>
                                            <CartesianGrid strokeDasharray="3 3" />
                                            <XAxis dataKey="periodo" tickFormatter={formatPeriodo} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip labelFormatter={formatPeriodo} formatter={(v) => [`${v} tickets`]} />
                                            <Line
                                                type="monotone"
                                                dataKey="total"
                                                name="Tickets"
                                                stroke={C.azul}
                                                strokeWidth={2}
                                                dot={{ r: 4 }}
                                                activeDot={{ r: 6, cursor: 'pointer', onClick: (_, payload: { index?: number }) => { if (payload.index !== undefined) goToTickets({ periodo: tendencia_mensual[payload.index].periodo }); } }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Tab: Por técnico ── */}
                {tab === 'tecnicos' && (
                    <div className="space-y-6">
                        {/* Gráficas lado a lado */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-4 text-base font-semibold">Atención por técnico</h2>
                                {tickets_por_tecnico.length === 0 ? (
                                    <EmptyChart />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={tickets_por_tecnico} layout="vertical" margin={{ left: 8, right: 16 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis type="number" allowDecimals={false} />
                                            <YAxis type="category" dataKey="tecnico" width={130} tick={{ fontSize: 12 }} />
                                            <Tooltip />
                                            <Legend />
                                            <Bar dataKey="activos" name="Activos" fill={C.azul} stackId="a" cursor="pointer" onClick={(_, i) => goToTickets({ tecnico_id: tickets_por_tecnico[i].tecnico_id })} />
                                            <Bar dataKey="completados" name="Completados" fill={C.verde} stackId="a" radius={[0, 4, 4, 0]} cursor="pointer" onClick={(_, i) => goToTickets({ tecnico_id: tickets_por_tecnico[i].tecnico_id })} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>

                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-4 text-base font-semibold">Satisfacción por técnico</h2>
                                {calificaciones_por_tecnico.length === 0 ? (
                                    <EmptyChart label="Aún no hay tickets calificados" />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={calificaciones_por_tecnico} margin={{ left: 0, right: 16 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} />
                                            <XAxis dataKey="tecnico" tick={{ fontSize: 11 }} />
                                            <YAxis domain={[0, 5]} ticks={[0, 1, 2, 3, 4, 5]} allowDecimals />
                                            <Tooltip
                                                formatter={(v, name) => [
                                                    name === 'promedio' ? `${v} / 5` : `${v} calificados`,
                                                    name === 'promedio' ? 'Promedio' : 'Tickets',
                                                ]}
                                            />
                                            <Bar dataKey="promedio" name="promedio" radius={[4, 4, 0, 0]} cursor="pointer" onClick={(_, i) => goToTickets({ tecnico_id: calificaciones_por_tecnico[i].tecnico_id })}>
                                                {calificaciones_por_tecnico.map((d, i) => (
                                                    <Cell key={i} fill={semaforo(d.promedio)} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        {/* Tabla resumen */}
                        <div className="bg-base-100 border-base-300 rounded-box border">
                            <div className="border-base-300 border-b px-4 py-3">
                                <h2 className="text-base font-semibold">Resumen por técnico</h2>
                            </div>
                            {resumenTecnicos.length === 0 ? (
                                <div className="text-base-content/50 py-8 text-center text-sm">Sin datos</div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="table">
                                        <thead>
                                            <tr>
                                                <th>Técnico</th>
                                                <th className="text-center">Total</th>
                                                <th className="text-center">Completados</th>
                                                <th className="text-center">Activos</th>
                                                <th className="text-center">% Resolución</th>
                                                <th className="text-center">Satisfacción</th>
                                                <th className="text-center">Tickets calif.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {resumenTecnicos.map((r) => (
                                                <tr key={r.tecnico}>
                                                    <td className="font-medium">{r.tecnico}</td>
                                                    <td className="text-center">{r.total}</td>
                                                    <td className="text-center text-success">{r.completados}</td>
                                                    <td className="text-center text-warning">{r.activos}</td>
                                                    <td className="text-center">
                                                        <span
                                                            className="badge"
                                                            style={{
                                                                backgroundColor: semaforo(r.tasa, 100),
                                                                color: '#fff',
                                                            }}
                                                        >
                                                            {r.tasa}%
                                                        </span>
                                                    </td>
                                                    <td className="text-center">
                                                        {r.promedio !== null ? (
                                                            <span className="flex items-center justify-center gap-1">
                                                                <span className="font-medium">{r.promedio}</span>
                                                                <StarRating value={r.promedio} />
                                                            </span>
                                                        ) : (
                                                            <span className="text-base-content/40 text-sm">—</span>
                                                        )}
                                                    </td>
                                                    <td className="text-base-content/60 text-center text-sm">{r.califTotal}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>
                )}
                {/* ── Tab: Tiempos de atención ── */}
                {tab === 'tiempos' && (
                    <div className="space-y-6">
                        {/* KPIs de tiempo */}
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <p className="text-base-content/60 text-xs uppercase tracking-wide">Tiempo total promedio</p>
                                <p className="mt-1 text-3xl font-bold">{formatHoras(tiempos.promedio_total)}</p>
                                <p className="text-base-content/40 mt-1 text-xs">Desde Pendiente hasta Completado</p>
                            </div>
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <p className="text-base-content/60 text-xs uppercase tracking-wide">Tiempo activo promedio</p>
                                <p className="text-success mt-1 text-3xl font-bold">{formatHoras(tiempos.promedio_activo)}</p>
                                <p className="text-base-content/40 mt-1 text-xs">Sin contar estados de espera</p>
                            </div>
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <p className="text-base-content/60 text-xs uppercase tracking-wide">Tiempo en espera promedio</p>
                                <p className="text-warning mt-1 text-3xl font-bold">{formatHoras(tiempos.promedio_detenido)}</p>
                                <p className="text-base-content/40 mt-1 text-xs">Estados que detienen el tiempo</p>
                            </div>
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <p className="text-base-content/60 text-xs uppercase tracking-wide">Tickets analizados</p>
                                <p className="mt-1 text-3xl font-bold">{tiempos.total_analizados}</p>
                                <p className="text-base-content/40 mt-1 text-xs">Completados con historial</p>
                            </div>
                        </div>

                        {/* Tiempos activo vs detenido por técnico */}
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-1 text-base font-semibold">Tiempo promedio por técnico</h2>
                                <p className="text-base-content/50 mb-4 text-xs">Horas activas vs en espera por ticket completado</p>
                                {tiempos.por_tecnico.length === 0 ? (
                                    <EmptyChart label="Sin tickets completados con historial" />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={tiempos.por_tecnico} layout="vertical" margin={{ left: 8, right: 48 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis
                                                type="number"
                                                tickFormatter={formatHoras}
                                                allowDecimals
                                            />
                                            <YAxis type="category" dataKey="tecnico" width={130} tick={{ fontSize: 12 }} />
                                            <Tooltip
                                                formatter={(v: number, name: string) => [
                                                    formatHoras(v),
                                                    name === 'promedio_activo' ? 'Tiempo activo' : 'Tiempo en espera',
                                                ]}
                                            />
                                            <Legend
                                                formatter={(value) =>
                                                    value === 'promedio_activo' ? 'Tiempo activo' : 'Tiempo en espera'
                                                }
                                            />
                                            <Bar dataKey="promedio_activo" name="promedio_activo" fill={C.verde} stackId="a" />
                                            <Bar dataKey="promedio_detenido" name="promedio_detenido" fill={C.amarillo} stackId="a" radius={[0, 4, 4, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>

                            {/* Estados que más detienen */}
                            <div className="bg-base-100 border-base-300 rounded-box border p-4">
                                <h2 className="mb-1 text-base font-semibold">Estados que detienen el tiempo</h2>
                                <p className="text-base-content/50 mb-4 text-xs">Promedio de horas por ocurrencia</p>
                                {tiempos.estados_detencion.length === 0 ? (
                                    <EmptyChart label="Sin datos de estados de espera" />
                                ) : (
                                    <ResponsiveContainer width="100%" height={280}>
                                        <BarChart data={tiempos.estados_detencion} layout="vertical" margin={{ left: 8, right: 48 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis
                                                type="number"
                                                tickFormatter={formatHoras}
                                                allowDecimals
                                            />
                                            <YAxis type="category" dataKey="estado" width={175} tick={{ fontSize: 11 }} />
                                            <Tooltip
                                                formatter={(v: number, _, props) => [
                                                    `${formatHoras(v)} (${props.payload?.ocurrencias ?? 0} veces)`,
                                                    'Promedio de espera',
                                                ]}
                                            />
                                            <Bar dataKey="promedio_h" name="Promedio espera" radius={[0, 4, 4, 0]}>
                                                {tiempos.estados_detencion.map((d, i) => (
                                                    <Cell key={i} fill={semaforo(1 / Math.max(d.promedio_h, 1), 1 / 8)} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                )}
                            </div>
                        </div>

                        {/* Tabla detalle por técnico */}
                        <div className="bg-base-100 border-base-300 rounded-box border">
                            <div className="border-base-300 border-b px-4 py-3">
                                <h2 className="text-base font-semibold">Detalle de tiempos por técnico</h2>
                                <p className="text-base-content/50 mt-0.5 text-xs">
                                    El tiempo detenido no es imputable al técnico — refleja esperas externas
                                </p>
                            </div>
                            {tiempos.por_tecnico.length === 0 ? (
                                <div className="text-base-content/50 py-8 text-center text-sm">Sin datos</div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="table">
                                        <thead>
                                            <tr>
                                                <th>Técnico</th>
                                                <th className="text-center">Tiempo total prom.</th>
                                                <th className="text-center">Tiempo activo prom.</th>
                                                <th className="text-center">Tiempo en espera prom.</th>
                                                <th className="text-center">% Espera / Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tiempos.por_tecnico.map((r) => {
                                                const pctEspera =
                                                    r.promedio_total > 0
                                                        ? Math.round((r.promedio_detenido / r.promedio_total) * 100)
                                                        : 0;
                                                return (
                                                    <tr key={r.tecnico}>
                                                        <td className="font-medium">{r.tecnico}</td>
                                                        <td className="text-center">{formatHoras(r.promedio_total)}</td>
                                                        <td className="text-center text-success">{formatHoras(r.promedio_activo)}</td>
                                                        <td className="text-center text-warning">{formatHoras(r.promedio_detenido)}</td>
                                                        <td className="text-center">
                                                            <span
                                                                className="badge"
                                                                style={{
                                                                    backgroundColor: pctEspera > 50 ? C.rojo : pctEspera > 25 ? C.amarillo : C.verde,
                                                                    color: '#fff',
                                                                }}
                                                            >
                                                                {pctEspera}%
                                                            </span>
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
                )}
            </div>
        </AppLayout>
    );
}
