import { calcularDatosProyecto, type DatosProyecto } from '@/components/cob/calculos';
import { formatearMXN } from '@/components/cob/money-display';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Dashboard', href: '/admin/cob/dashboard' },
];

const C = {
    verde: '#36d399',
    amarillo: '#fbbd23',
    rojo: '#f87272',
    azul: '#3abff8',
    morado: '#a78bfa',
    naranja: '#fb923c',
    gris: '#9ca3af',
};

const PIE_COLORS = [C.azul, C.verde, C.naranja, C.morado, C.amarillo, C.gris];

function EmptyChart({ label = 'Sin datos disponibles' }: { label?: string }) {
    return (
        <div className="flex h-48 items-center justify-center text-base-content/50">
            <p className="text-sm">{label}</p>
        </div>
    );
}

function semaforoDesfase(desfase: number): string {
    if (desfase > 15) return C.rojo;
    if (desfase > 5) return C.amarillo;
    return C.verde;
}

type DsoPorObra = { obra_id: number; obra_no: string; dias_promedio: number };
type RetencionPorTipo = { tipo: string; monto: number };
type DisputaItem = {
    id: number;
    obra_no: string;
    obra_descripcion: string;
    descripcion: string;
    fecha_inicio: string | null;
    fecha_resolucion: string | null;
    estado: string;
    dias_abierta: number | null;
};

type Props = {
    obras: Obra[];
    dsoPorObra: DsoPorObra[];
    retencionesPorTipo: RetencionPorTipo[];
    disputas: DisputaItem[];
};

export default function CobDashboardIndex({ obras, dsoPorObra, retencionesPorTipo, disputas }: Props) {
    const proyectos = useMemo(() => obras.map((obra) => calcularDatosProyecto(obra)), [obras]);

    const totales = useMemo(() => {
        const contratado = proyectos.reduce((s, p) => s + p.presupuestoEjecutar, 0);
        const facturado = proyectos.reduce((s, p) => s + p.totalFacturado, 0);
        const cobrado = proyectos.reduce((s, p) => s + p.totalCobrado, 0);
        const porFacturar = proyectos.reduce((s, p) => s + Math.max(p.porFacturar, 0), 0);
        return { contratado, facturado, cobrado, porFacturar };
    }, [proyectos]);

    const disputasActivas = disputas.filter((d) => d.estado === 'en_proceso');

    // Dona global: Cobrado / Facturado sin cobrar / Por facturar
    const donaCobranza = useMemo(
        () => [
            { nombre: 'Cobrado', monto: totales.cobrado, color: C.verde },
            { nombre: 'Facturado sin cobrar', monto: Math.max(totales.facturado - totales.cobrado, 0), color: C.amarillo },
            { nombre: 'Por facturar', monto: totales.porFacturar, color: C.gris },
        ].filter((d) => d.monto > 0),
        [totales],
    );

    // Concentracion de cartera
    const cartera = useMemo(() => {
        const porCliente: Record<string, number> = {};
        proyectos.forEach((p) => {
            const nombre = p.obra.cliente?.nombre ?? 'Sin cliente';
            porCliente[nombre] = (porCliente[nombre] ?? 0) + p.totalFacturado;
        });
        const sorted = Object.entries(porCliente)
            .map(([nombre, monto]) => ({ nombre, monto }))
            .sort((a, b) => b.monto - a.monto);
        if (sorted.length <= 5) return sorted;
        const top5 = sorted.slice(0, 5);
        const otros = sorted.slice(5).reduce((s, c) => s + c.monto, 0);
        return [...top5, { nombre: 'Otros', monto: otros }];
    }, [proyectos]);

    // Anticipos globales
    const anticiposData = useMemo(() => {
        const totalContractual = proyectos.reduce((s, p) => s + Number(p.obra.anticipo ?? 0), 0);
        const totalCobrado = proyectos.reduce((s, p) => s + p.totalAnticiposCobrados, 0);
        const pendiente = Math.max(totalContractual - totalCobrado, 0);
        return { totalContractual, totalCobrado, pendiente };
    }, [proyectos]);

    // Desfase avance vs cobranza
    const desfaseData = useMemo(
        () =>
            proyectos
                .filter((p) => Number(p.obra.porcentaje_obra ?? 0) > 0)
                .map((p) => {
                    const avance = Number(p.obra.porcentaje_obra ?? 0);
                    const cobranza = p.porcentajeCobrado;
                    return {
                        obra: p.obra,
                        avance,
                        cobranza: Math.round(cobranza * 10) / 10,
                        desfase: Math.round((avance - cobranza) * 10) / 10,
                    };
                })
                .sort((a, b) => b.desfase - a.desfase),
        [proyectos],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Cobranza" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Dashboard — Cobranza</h1>
                    <p className="mt-1 text-sm text-base-content/50">Indicadores de facturacion, cobranza y riesgo</p>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
                    <KpiCard label="Total Contratado" valor={formatearMXN(totales.contratado)} />
                    <KpiCard label="Facturado" valor={formatearMXN(totales.facturado)} color="text-info" />
                    <KpiCard label="Cobrado" valor={formatearMXN(totales.cobrado)} color="text-success" />
                    <KpiCard label="Por Facturar" valor={formatearMXN(totales.porFacturar)} color="text-warning" />
                    <KpiCard
                        label="DSO Promedio"
                        valor={dsoPorObra.length > 0 ? `${Math.round(dsoPorObra.reduce((s, d) => s + Number(d.dias_promedio), 0) / dsoPorObra.length)} dias` : '—'}
                        color={
                            dsoPorObra.length > 0
                                ? Math.round(dsoPorObra.reduce((s, d) => s + Number(d.dias_promedio), 0) / dsoPorObra.length) > 60
                                    ? 'text-error'
                                    : Math.round(dsoPorObra.reduce((s, d) => s + Number(d.dias_promedio), 0) / dsoPorObra.length) > 30
                                        ? 'text-warning'
                                        : 'text-success'
                                : undefined
                        }
                    />
                    <KpiCard
                        label="Disputas Activas"
                        valor={String(disputasActivas.length)}
                        color={disputasActivas.length > 0 ? 'text-error' : 'text-success'}
                    />
                </div>

                {/* Fila 2: Graficas principales */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Dona: Cobrado / Facturado sin cobrar / Por facturar */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Estado de Cobranza Global
                        </h3>
                        {donaCobranza.length === 0 ? (
                            <EmptyChart />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <PieChart>
                                    <Pie
                                        data={donaCobranza}
                                        dataKey="monto"
                                        nameKey="nombre"
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={60}
                                        outerRadius={110}
                                        label={({ nombre, percent }: { nombre: string; percent: number }) =>
                                            `${nombre} ${(percent * 100).toFixed(0)}%`
                                        }
                                    >
                                        {donaCobranza.map((entry, i) => (
                                            <Cell key={i} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value: number) => formatearMXN(value)} />
                                    <Legend />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Concentracion de cartera */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Concentracion de Cartera
                        </h3>
                        {cartera.length === 0 ? (
                            <EmptyChart />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <PieChart>
                                    <Pie
                                        data={cartera}
                                        dataKey="monto"
                                        nameKey="nombre"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={90}
                                        label={({ nombre, percent }: { nombre: string; percent: number }) =>
                                            `${nombre.substring(0, 15)} ${(percent * 100).toFixed(0)}%`
                                        }
                                    >
                                        {cartera.map((_, i) => (
                                            <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value: number) => formatearMXN(value)} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </div>


                    {/* Anticipos */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Anticipos
                        </h3>
                        {anticiposData.totalContractual === 0 ? (
                            <EmptyChart label="Sin anticipos registrados" />
                        ) : (
                            <div className="flex flex-col items-center gap-4 md:flex-row md:justify-around">
                                <ResponsiveContainer width="100%" height={250} maxHeight={250}>
                                    <PieChart>
                                        <Pie
                                            data={[
                                                { nombre: 'Cobrado', monto: anticiposData.totalCobrado },
                                                { nombre: 'Pendiente', monto: anticiposData.pendiente },
                                            ].filter((d) => d.monto > 0)}
                                            dataKey="monto"
                                            nameKey="nombre"
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={50}
                                            outerRadius={90}
                                            label={({ nombre, percent }: { nombre: string; percent: number }) =>
                                                `${nombre} ${(percent * 100).toFixed(0)}%`
                                            }
                                        >
                                            <Cell fill={C.verde} />
                                            <Cell fill={C.amarillo} />
                                        </Pie>
                                        <Tooltip formatter={(value: number) => formatearMXN(value)} />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                                <div className="space-y-2 text-sm">
                                    <p>
                                        <span className="text-base-content/60">Contractual:</span>{' '}
                                        <span className="font-semibold">{formatearMXN(anticiposData.totalContractual)}</span>
                                    </p>
                                    <p>
                                        <span className="text-base-content/60">Cobrado:</span>{' '}
                                        <span className="font-semibold text-success">{formatearMXN(anticiposData.totalCobrado)}</span>
                                    </p>
                                    <p>
                                        <span className="text-base-content/60">Pendiente:</span>{' '}
                                        <span className="font-semibold text-warning">{formatearMXN(anticiposData.pendiente)}</span>
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>


                {/* Fila 4: Tablas */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Tabla desfase avance vs cobranza */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Avance de Obra vs Cobranza
                        </h3>
                        {desfaseData.length === 0 ? (
                            <EmptyChart label="Sin datos de avance registrados" />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Obra</th>
                                            <th className="text-right">% Avance</th>
                                            <th className="text-right">% Cobrado</th>
                                            <th className="text-right">Desfase</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {desfaseData.map((d) => (
                                            <tr key={d.obra.id}>
                                                <td>
                                                    {d.obra.no} — {d.obra.descripcion}
                                                </td>
                                                <td className="text-right">{d.avance}%</td>
                                                <td className="text-right">{d.cobranza}%</td>
                                                <td className="text-right font-semibold">{d.desfase}%</td>
                                                <td>
                                                    <span
                                                        className="inline-block h-3 w-3 rounded-full"
                                                        style={{ backgroundColor: semaforoDesfase(d.desfase) }}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Tabla disputas */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Disputas
                        </h3>
                        {disputas.length === 0 ? (
                            <EmptyChart label="Sin disputas registradas" />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Obra</th>
                                            <th>Descripcion</th>
                                            <th className="text-right">Dias</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {disputas.map((d) => (
                                            <tr key={d.id}>
                                                <td className="whitespace-nowrap">{d.obra_no}</td>
                                                <td className="max-w-xs truncate">{d.descripcion}</td>
                                                <td className="text-right">{d.dias_abierta ?? '—'}</td>
                                                <td>
                                                    <span
                                                        className={`badge badge-sm ${d.estado === 'en_proceso' ? 'badge-warning' : d.estado === 'resuelto' ? 'badge-success' : 'badge-ghost'}`}
                                                    >
                                                        {d.estado === 'en_proceso'
                                                            ? 'En proceso'
                                                            : d.estado === 'resuelto'
                                                                ? 'Resuelto'
                                                                : d.estado}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Retenciones por tipo */}
                    <div className="rounded-box border border-base-300 bg-base-100 p-4">
                        <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                            Retenciones por Tipo
                        </h3>
                        {retencionesPorTipo.length === 0 ? (
                            <EmptyChart label="Sin retenciones registradas" />
                        ) : (
                            <ResponsiveContainer width="100%" height={300}>
                                <PieChart>
                                    <Pie
                                        data={retencionesPorTipo}
                                        dataKey="monto"
                                        nameKey="tipo"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={90}
                                        label={({ tipo, percent }: { tipo: string; percent: number }) =>
                                            `${tipo.substring(0, 15)} ${(percent * 100).toFixed(0)}%`
                                        }
                                    >
                                        {retencionesPorTipo.map((_, i) => (
                                            <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip formatter={(value: number) => formatearMXN(value)} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </div>

                {/* Tabla portafolio */}
                <div className="rounded-box border border-base-300 bg-base-100 p-4">
                    <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-base-content/60">
                        Portafolio de Obras
                    </h3>
                    <PortafolioTable proyectos={proyectos} />
                </div>
            </div>
        </AppLayout>
    );
}

function KpiCard({ label, valor, color }: { label: string; valor: string; color?: string }) {
    return (
        <div className="rounded-box border border-base-300 bg-base-100 p-4">
            <p className="text-xs tracking-wide text-base-content/60 uppercase">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${color ?? ''}`}>{valor}</p>
        </div>
    );
}

function PortafolioTable({ proyectos }: { proyectos: DatosProyecto[] }) {
    if (proyectos.length === 0) {
        return <EmptyChart label="No hay obras registradas" />;
    }

    return (
        <div className="max-h-96 overflow-auto">
            <table className="table table-sm">
                <thead className="sticky top-0 z-10 bg-base-100">
                    <tr>
                        <th>No Obra</th>
                        <th>Descripcion</th>
                        <th>Cliente</th>
                        <th className="text-right">Presupuesto</th>
                        <th className="text-right">Facturado</th>
                        <th className="text-right">Cobrado</th>
                        <th className="text-right">Por Cobrar</th>
                        <th>% Cobrado</th>
                    </tr>
                </thead>
                <tbody>
                    {proyectos.map((p) => (
                        <tr key={p.obra.id}>
                            <td>{p.obra.no}</td>
                            <td>{p.obra.descripcion}</td>
                            <td>{p.obra.cliente?.nombre ?? '—'}</td>
                            <td className="text-right">{formatearMXN(p.presupuestoEjecutar)}</td>
                            <td className="text-right">{formatearMXN(p.totalFacturado)}</td>
                            <td className="text-right">{formatearMXN(p.totalCobrado)}</td>
                            <td className="text-right">{formatearMXN(p.porCobrar)}</td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <progress
                                        className="progress progress-primary w-20"
                                        value={Math.min(p.porcentajeCobrado, 100)}
                                        max="100"
                                    />
                                    <span className="text-sm">{p.porcentajeCobrado.toFixed(1)}%</span>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
