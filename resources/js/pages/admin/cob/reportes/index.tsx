import { Head, Link, router } from '@inertiajs/react';
import { FileTextIcon } from 'lucide-react';
import { formatearMXN } from '@/components/cob/money-display';
import AppLayout from '@/layouts/app-layout';
import { formatFecha } from '@/lib/fechas';
import type { BreadcrumbItem } from '@/types';
import type { CobReporteFila } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'Reporte semanal', href: '/admin/cob/reportes' },
];

type Props = {
    anio: number;
    anios: number[];
    filas: CobReporteFila[];
};

const FMT_DIA_MES: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short' };

export default function ReportesIndex({ anio, anios, filas }: Props) {
    const cambiarAnio = (nuevo: number) => {
        router.get('/admin/cob/reportes', { anio: nuevo }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reporte semanal de cobranza" />

            <div className="flex flex-col gap-4 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Reporte semanal de cobranza</h1>
                        <p className="text-base-content/60 mt-1 text-sm">Saldo de cartera semana a semana — {anio}</p>
                    </div>
                    <select
                        className="select select-bordered select-sm"
                        value={anio}
                        onChange={(e) => cambiarAnio(Number(e.target.value))}
                    >
                        {anios.map((a) => (
                            <option key={a} value={a}>{a}</option>
                        ))}
                    </select>
                </div>

                <div className="rounded-box border border-base-300 max-h-[calc(100vh-12rem)] overflow-auto">
                    <table className="table table-sm table-zebra">
                        <thead className="bg-base-100 sticky top-0 z-10">
                            <tr>
                                <th>Semana</th>
                                <th>Periodo</th>
                                <th className="text-right">Saldo anterior</th>
                                <th className="text-right">Detonaciones</th>
                                <th className="text-right">Cobrado</th>
                                <th className="text-right">Nuevo saldo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((f) => (
                                <tr key={f.semana} className="hover">
                                    <td className="font-medium">
                                        S{f.semana}
                                        {f.tiene_notas && (
                                            <span className="tooltip ml-1" data-tip="Tiene notas">
                                                <FileTextIcon className="text-info inline size-3" />
                                            </span>
                                        )}
                                    </td>
                                    <td className="whitespace-nowrap">{formatFecha(f.fecha_inicio, FMT_DIA_MES)} – {formatFecha(f.fecha_fin, FMT_DIA_MES)}</td>
                                    <td className="text-right">{formatearMXN(Number(f.saldo_anterior_sin_iva))}</td>
                                    <td className="text-success text-right">{f.total_detonaciones_sin_iva > 0 ? `+${formatearMXN(Number(f.total_detonaciones_sin_iva))}` : '—'}</td>
                                    <td className="text-error text-right">{f.total_cobrado_sin_iva > 0 ? `−${formatearMXN(Number(f.total_cobrado_sin_iva))}` : '—'}</td>
                                    <td className="text-right font-semibold">{formatearMXN(Number(f.saldo_nuevo_sin_iva))}</td>
                                    <td className="text-right">
                                        <div className="join">
                                            <Link href={`/admin/cob/reportes/${f.anio}/${f.semana}`} className="btn btn-outline btn-xs join-item">Ver</Link>
                                            <a href={`/admin/cob/reportes/${f.anio}/${f.semana}/pdf`} className="btn btn-outline btn-xs join-item" target="_blank" rel="noopener noreferrer">PDF</a>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {filas.length === 0 && (
                                <tr><td colSpan={7} className="py-8 text-center opacity-50">Sin semanas para {anio}.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
