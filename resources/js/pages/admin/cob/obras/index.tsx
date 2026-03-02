import { calcularDatosProyecto } from '@/components/cob/calculos';
import { EstimacionesGantt } from '@/components/cob/estimaciones-gantt';
import { formatearMXN } from '@/components/cob/money-display';
import { SearchInput } from '@/components/data-table/search-input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type TabKey = 'tabla' | 'gantt';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Obras', href: '/admin/cob/obras' },
];

function pct(valor: number, total: number): string {
    if (total <= 0) return '0.00%';
    return ((valor / total) * 100).toFixed(2) + '%';
}

type Props = {
    obras: Obra[];
    filters: { search?: string };
};

export default function ObrasIndex({ obras, filters }: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('tabla');
    const [selectedYear, setSelectedYear] = useState<number | undefined>(undefined);

    const availableYears = useMemo(() => {
        const years = new Set<number>();
        for (const obra of obras) {
            for (const est of obra.estimaciones ?? []) {
                for (const h of est.historial ?? []) {
                    years.add(new Date(h.fecha_cambio).getFullYear());
                }
            }
        }
        return [...years].sort((a, b) => b - a);
    }, [obras]);

    const datosObras = useMemo(
        () => obras.map((obra) => ({ obra, datos: calcularDatosProyecto(obra) })),
        [obras],
    );

    const totales = useMemo(
        () =>
            datosObras.reduce(
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
        [datosObras],
    );

    const pctCobradoGlobal = pct(totales.totalCobrado, totales.presupuestoFinal);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Obras - Cobranza" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <div role="tablist" className="tabs tabs-bordered">
                        <button
                            role="tab"
                            className={`tab ${activeTab === 'tabla' ? 'tab-active' : ''}`}
                            onClick={() => setActiveTab('tabla')}
                        >
                            Tabla
                        </button>
                        <button
                            role="tab"
                            className={`tab ${activeTab === 'gantt' ? 'tab-active' : ''}`}
                            onClick={() => setActiveTab('gantt')}
                        >
                            Gantt
                        </button>
                    </div>

                    {activeTab === 'tabla' && (
                        <SearchInput
                            placeholder="Buscar obras..."
                            defaultValue={filters.search}
                            className="max-w-xs"
                        />
                    )}

                    {activeTab === 'gantt' && availableYears.length > 0 && (
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

                {activeTab === 'tabla' ? (
                    <div className="overflow-x-auto rounded-box border border-base-300">
                        <table className="table table-zebra whitespace-nowrap text-sm">
                            <thead className="sticky top-0 z-10 bg-base-100">
                                <tr>
                                    <th>Cliente</th>
                                    <th>No</th>
                                    <th>Obra</th>
                                    <th className="text-right">Presupuesto</th>
                                    <th className="text-right">Pres. a ejecutar</th>
                                    <th className="text-right">Ajuste</th>
                                    <th className="text-right">Deductivas</th>
                                    <th className="text-right">Pres. final</th>
                                    <th className="text-right">Cobrado</th>
                                    <th className="text-right">Por cobrar</th>
                                    <th className="text-right">% Cobrado</th>
                                    <th className="text-right">Gen. por cobrar</th>
                                    <th className="text-right">%</th>
                                    <th className="text-right">Ing. por cobrar</th>
                                    <th className="text-right">%</th>
                                    <th className="text-right">Fact. por cobrar</th>
                                    <th className="text-right">%</th>
                                    <th className="text-right">Facturado</th>
                                </tr>
                            </thead>

                            <tbody>
                                {datosObras.length === 0 ? (
                                    <tr>
                                        <td colSpan={18} className="text-base-content/60 py-8 text-center">
                                            No hay obras registradas
                                        </td>
                                    </tr>
                                ) : (
                                    datosObras.map(({ obra, datos: d }) => (
                                        <tr key={obra.id} className="hover">
                                            <td>{obra.cliente?.nombre ?? '-'}</td>
                                            <td>{obra.no}</td>
                                            <td>
                                                <Link
                                                    href={`/admin/cob/obras/${obra.id}`}
                                                    className="link link-primary font-medium"
                                                >
                                                    {obra.descripcion}
                                                </Link>
                                            </td>
                                            <td className="text-right">{formatearMXN(d.presupuestoPartidas)}</td>
                                            <td className="text-right">{formatearMXN(d.presupuestoEjecutar)}</td>
                                            <td className="text-right">
                                                {d.tieneComparativos ? formatearMXN(d.ajustePresupuesto) : '-'}
                                            </td>
                                            <td className="text-right">{formatearMXN(d.totalDeducciones)}</td>
                                            <td className="text-right">{formatearMXN(d.presupuestoFinal)}</td>
                                            <td className="text-right">{formatearMXN(d.totalCobrado)}</td>
                                            <td className="text-right">{formatearMXN(d.porCobrar)}</td>
                                            <td className="text-right">{pct(d.totalCobrado, d.presupuestoFinal)}</td>
                                            <td className="text-right">{formatearMXN(d.estimacionesGeneradas)}</td>
                                            <td className="text-right">
                                                {pct(d.estimacionesGeneradas, d.presupuestoEjecutar)}
                                            </td>
                                            <td className="text-right">{formatearMXN(d.estimacionesIngresadas)}</td>
                                            <td className="text-right">
                                                {pct(d.estimacionesIngresadas, d.presupuestoEjecutar)}
                                            </td>
                                            <td className="text-right">{formatearMXN(d.facturadasPorCobrar)}</td>
                                            <td className="text-right">
                                                {pct(d.facturadasPorCobrar, d.presupuestoEjecutar)}
                                            </td>
                                            <td className="text-right">{formatearMXN(d.totalFacturado)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>

                            {datosObras.length > 0 && (
                                <tfoot className="sticky bottom-0 z-10 bg-base-300 font-bold">
                                    <tr>
                                        <td colSpan={3} className="text-right">
                                            TOTALES:
                                        </td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoPartidas)}</td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoEjecutar)}</td>
                                        <td className="text-right">-</td>
                                        <td className="text-right">{formatearMXN(totales.totalDeducciones)}</td>
                                        <td className="text-right">{formatearMXN(totales.presupuestoFinal)}</td>
                                        <td className="text-right">{formatearMXN(totales.totalCobrado)}</td>
                                        <td className="text-right">{formatearMXN(totales.porCobrar)}</td>
                                        <td className="text-right">{pctCobradoGlobal}</td>
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
                    <EstimacionesGantt obras={obras} year={selectedYear} />
                )}
            </div>
        </AppLayout>
    );
}
