import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { calcularResumenProyecto } from '@/components/cob/calculos';
import { EstimacionesGantt } from '@/components/cob/estimaciones-gantt';
import { formatearMXN } from '@/components/cob/money-display';
import { SearchInput } from '@/components/data-table/search-input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Proyecto } from '@/types/models';

type TabKey = 'tabla' | 'gantt' | 'gantt-simple';
type EstatusFiltro = 'abierta' | 'cerrada' | 'todas';

type Fila = { proyecto: Proyecto; datos: ReturnType<typeof calcularResumenProyecto> };
type SortState = { key: string; dir: 'asc' | 'desc' };

const ratio = (valor: number, total: number): number => (total > 0 ? valor / total : 0);

const ACCESSORS: Record<string, (f: Fila) => number | string> = {
    cliente: ({ proyecto }) => proyecto.cliente?.nombre ?? '',
    no: ({ proyecto }) => proyecto.no,
    proyecto: ({ proyecto }) => proyecto.descripcion,
    obras: ({ proyecto }) => proyecto.obras?.length ?? 0,
    presupuesto: ({ datos }) => datos.presupuestoPartidas,
    ejecutar: ({ datos }) => datos.presupuestoEjecutar,
    comparativo: ({ datos }) =>
        datos.tieneComparativos
            ? datos.tipoContrato === 'precio_unitario'
                ? datos.ajustePresupuesto
                : datos.montoComparativo
            : 0,
    deductivas: ({ datos }) => datos.totalDeducciones,
    presFinal: ({ datos }) => datos.presupuestoFinal,
    cobrado: ({ datos }) => datos.totalCobrado,
    porCobrar: ({ datos }) => datos.porCobrar,
    pctCobrado: ({ datos }) => ratio(datos.totalCobrado, datos.presupuestoFinal),
    gen: ({ datos }) => datos.estimacionesGeneradas,
    pctGen: ({ datos }) => ratio(datos.estimacionesGeneradas, datos.presupuestoEjecutar),
    ing: ({ datos }) => datos.estimacionesIngresadas,
    pctIng: ({ datos }) => ratio(datos.estimacionesIngresadas, datos.presupuestoEjecutar),
    fact: ({ datos }) => datos.facturadasPorCobrar,
    pctFact: ({ datos }) => ratio(datos.facturadasPorCobrar, datos.presupuestoEjecutar),
    facturado: ({ datos }) => datos.totalFacturado,
};

const ESTATUS_FILTROS: { key: EstatusFiltro; label: string }[] = [
    { key: 'abierta', label: 'Abiertos' },
    { key: 'cerrada', label: 'Cerrados' },
    { key: 'todas', label: 'Todos' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Proyectos', href: '/admin/cob/proyectos' },
];

function pct(valor: number, total: number): string {
    if (total <= 0) return '0.00%';
    return ((valor / total) * 100).toFixed(2) + '%';
}

type Props = {
    proyectos: Proyecto[];
    filters: { search?: string; estatus: EstatusFiltro };
};

export default function ProyectosIndex({ proyectos, filters }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('tabla');
    const [selectedYear, setSelectedYear] = useState<number | undefined>(undefined);
    const [sort, setSort] = useState<SortState | null>(null);

    const toggleSort = (key: string) => {
        setSort((prev) => (prev?.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' }));
    };

    const cambiarEstatus = (estatus: EstatusFiltro) => {
        const params = Object.fromEntries(new URLSearchParams(window.location.search));
        router.get(window.location.pathname, { ...params, estatus }, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Gantt: una fila por obra (con sus propias estimaciones).
    const obrasGantt = useMemo<Obra[]>(
        () => proyectos.flatMap((p) => p.obras ?? []),
        [proyectos],
    );

    const availableYears = useMemo(() => {
        const years = new Set<number>();
        for (const o of obrasGantt) {
            for (const est of o.estimaciones ?? []) {
                for (const h of est.historial ?? []) {
                    years.add(new Date(h.fecha_cambio).getFullYear());
                }
            }
        }
        return [...years].sort((a, b) => b - a);
    }, [obrasGantt]);

    const datos = useMemo<Fila[]>(
        () => proyectos.map((proyecto) => ({ proyecto, datos: calcularResumenProyecto(proyecto) })),
        [proyectos],
    );

    const sortedDatos = useMemo(() => {
        if (!sort) return datos;
        const accessor = ACCESSORS[sort.key];
        if (!accessor) return datos;
        return [...datos].sort((a, b) => {
            const va = accessor(a);
            const vb = accessor(b);
            const cmp =
                typeof va === 'number' && typeof vb === 'number'
                    ? va - vb
                    : String(va).localeCompare(String(vb), 'es', { numeric: true });
            return sort.dir === 'asc' ? cmp : -cmp;
        });
    }, [datos, sort]);

    const sortTh = (id: string, label: string, align: 'left' | 'right' = 'right') => {
        const dir = sort?.key === id ? sort.dir : undefined;
        const Icon = dir === 'asc' ? ArrowUpIcon : dir === 'desc' ? ArrowDownIcon : ArrowUpDownIcon;
        return (
            <th className={`cursor-pointer select-none ${align === 'right' ? 'text-right' : ''}`} onClick={() => toggleSort(id)}>
                <span className="inline-flex items-center gap-1">
                    {label}
                    <Icon className={dir ? 'size-3' : 'size-3 opacity-30'} />
                </span>
            </th>
        );
    };

    const totales = useMemo(
        () =>
            datos.reduce(
                (acc, { datos: d }) => {
                    acc.presupuestoPartidas += d.presupuestoPartidas;
                    acc.presupuestoEjecutar += d.presupuestoEjecutar;
                    acc.totalDeducciones += d.totalDeducciones;
                    acc.presupuestoFinal += d.presupuestoFinal;
                    acc.totalCobrado += d.totalCobrado;
                    acc.porCobrar += d.porCobrar;
                    acc.estimacionesGeneradas += d.estimacionesGeneradas;
                    acc.estimacionesIngresadas += d.estimacionesIngresadas;
                    acc.facturadasPorCobrar += d.facturadasPorCobrar;
                    acc.totalFacturado += d.totalFacturado;
                    return acc;
                },
                {
                    presupuestoPartidas: 0,
                    presupuestoEjecutar: 0,
                    totalDeducciones: 0,
                    presupuestoFinal: 0,
                    totalCobrado: 0,
                    porCobrar: 0,
                    estimacionesGeneradas: 0,
                    estimacionesIngresadas: 0,
                    facturadasPorCobrar: 0,
                    totalFacturado: 0,
                },
            ),
        [datos],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proyectos - Cobranza" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <div role="tablist" className="tabs tabs-bordered">
                        <button role="tab" className={`tab ${activeTab === 'tabla' ? 'tab-active' : ''}`} onClick={() => setActiveTab('tabla')}>
                            Tabla
                        </button>
                        <button role="tab" className={`tab ${activeTab === 'gantt' ? 'tab-active' : ''}`} onClick={() => setActiveTab('gantt')}>
                            Gantt
                        </button>
                        <button role="tab" className={`tab ${activeTab === 'gantt-simple' ? 'tab-active' : ''}`} onClick={() => setActiveTab('gantt-simple')}>
                            Gantt simple
                        </button>
                    </div>

                    <div className="flex items-center gap-2">
                        <div role="tablist" className="tabs tabs-boxed tabs-sm">
                            {ESTATUS_FILTROS.map(({ key, label }) => (
                                <button
                                    key={key}
                                    role="tab"
                                    className={`tab ${filters.estatus === key ? 'tab-active' : ''}`}
                                    onClick={() => cambiarEstatus(key)}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {activeTab === 'tabla' && (
                            <>
                                <SearchInput placeholder="Buscar proyectos..." defaultValue={filters.search} className="max-w-xs" />
                                <Link href="/admin/cob/proyectos/create" className="btn btn-primary btn-sm">
                                    Nuevo proyecto
                                </Link>
                            </>
                        )}

                        {activeTab !== 'tabla' && availableYears.length > 0 && (
                            <select
                                className="select select-bordered select-sm"
                                value={selectedYear ?? ''}
                                onChange={(e) => setSelectedYear(e.target.value ? Number(e.target.value) : undefined)}
                            >
                                <option value="">Todos los años</option>
                                {availableYears.map((y) => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                {activeTab === 'tabla' ? (
                    <div className="max-h-[calc(100vh-12rem)] overflow-auto rounded-box border border-base-300">
                        <table className="table table-zebra whitespace-nowrap text-sm">
                            <thead className="sticky top-0 z-10 bg-base-100">
                                <tr>
                                    {sortTh('cliente', 'Cliente', 'left')}
                                    {sortTh('no', 'No', 'left')}
                                    {sortTh('proyecto', 'Proyecto', 'left')}
                                    {sortTh('obras', 'Obras')}
                                    {sortTh('presupuesto', 'Presupuesto')}
                                    {sortTh('ejecutar', 'Pres. a ejecutar')}
                                    {sortTh('comparativo', 'Comp. / Ajuste')}
                                    {sortTh('deductivas', 'Deductivas')}
                                    {sortTh('presFinal', 'Pres. final')}
                                    {sortTh('cobrado', 'Cobrado')}
                                    {sortTh('porCobrar', 'Por cobrar')}
                                    {sortTh('pctCobrado', '% Cobrado')}
                                    {sortTh('gen', 'Gen. por cobrar')}
                                    {sortTh('pctGen', '%')}
                                    {sortTh('ing', 'Ing. por cobrar')}
                                    {sortTh('pctIng', '%')}
                                    {sortTh('fact', 'Fact. por cobrar')}
                                    {sortTh('pctFact', '%')}
                                    {sortTh('facturado', 'Facturado')}
                                </tr>
                            </thead>

                            <tbody>
                                {datos.length === 0 ? (
                                    <tr>
                                        <td colSpan={19} className="text-base-content/60 py-8 text-center">
                                            No hay proyectos registrados
                                        </td>
                                    </tr>
                                ) : (
                                    sortedDatos.map(({ proyecto: p, datos: d }) => (
                                        <tr key={p.id} className="hover">
                                            <td>{p.cliente?.nombre ?? '-'}</td>
                                            <td>{p.no}</td>
                                            <td>
                                                <Link href={`/admin/cob/proyectos/${p.id}`} className="link link-primary font-medium">
                                                    {p.descripcion}
                                                </Link>
                                            </td>
                                            <td className="text-right">{p.obras?.length ?? 0}</td>
                                            <td className="text-right">{formatearMXN(d.presupuestoPartidas)}</td>
                                            <td className="text-right">{formatearMXN(d.presupuestoEjecutar)}</td>
                                            <td className="text-right">
                                                {d.tieneComparativos
                                                    ? d.tipoContrato === 'precio_unitario'
                                                        ? formatearMXN(d.ajustePresupuesto)
                                                        : formatearMXN(d.montoComparativo)
                                                    : '-'}
                                                {d.tieneComparativos && (
                                                    <div className="text-xs opacity-50">
                                                        {d.tipoContrato === 'precio_unitario' ? 'Ajuste' : 'Ref. comparativo'}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="text-right">{formatearMXN(d.totalDeducciones)}</td>
                                            <td className="text-right">{formatearMXN(d.presupuestoFinal)}</td>
                                            <td className="text-right">{formatearMXN(d.totalCobrado)}</td>
                                            <td className="text-right">{formatearMXN(d.porCobrar)}</td>
                                            <td className="text-right">{pct(d.totalCobrado, d.presupuestoFinal)}</td>
                                            <td className="text-right">{formatearMXN(d.estimacionesGeneradas)}</td>
                                            <td className="text-right">{pct(d.estimacionesGeneradas, d.presupuestoEjecutar)}</td>
                                            <td className="text-right">{formatearMXN(d.estimacionesIngresadas)}</td>
                                            <td className="text-right">{pct(d.estimacionesIngresadas, d.presupuestoEjecutar)}</td>
                                            <td className="text-right">{formatearMXN(d.facturadasPorCobrar)}</td>
                                            <td className="text-right">{pct(d.facturadasPorCobrar, d.presupuestoEjecutar)}</td>
                                            <td className="text-right">{formatearMXN(d.totalFacturado)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>

                            {datos.length > 0 && (
                                <tfoot className="sticky bottom-0 z-10 bg-base-300 font-bold">
                                    <tr>
                                        <td colSpan={4} className="text-right">TOTALES:</td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoPartidas)}</td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoEjecutar)}</td>
                                        <td className="text-right">-</td>
                                        <td className="text-right">{formatearMXN(totales.totalDeducciones)}</td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoFinal)}</td>
                                        <td className="text-right">{formatearMXN(totales.totalCobrado)}</td>
                                        <td className="text-right">{formatearMXN(totales.porCobrar)}</td>
                                        <td className="text-right">{pct(totales.totalCobrado, totales.presupuestoFinal)}</td>
                                        <td className="text-right">{formatearMXN(totales.estimacionesGeneradas)}</td>
                                        <td className="text-right">-</td>
                                        <td className="text-right">{formatearMXN(totales.estimacionesIngresadas)}</td>
                                        <td className="text-right">-</td>
                                        <td className="text-right">{formatearMXN(totales.facturadasPorCobrar)}</td>
                                        <td className="text-right">-</td>
                                        <td className="text-right">{formatearMXN(totales.totalFacturado)}</td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                ) : (
                    <EstimacionesGantt obras={obrasGantt} year={selectedYear} simple={activeTab === 'gantt-simple'} />
                )}
            </div>
        </AppLayout>
    );
}
