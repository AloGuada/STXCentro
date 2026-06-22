import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { Fragment, type FormEvent, useMemo } from 'react';
import { formatearMXN } from '@/components/cob/money-display';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobReporteCobro, CobReporteSemana } from '@/types/models';

type Props = {
    reporte: CobReporteSemana;
};

function fmtFecha(fecha: string): string {
    return new Date(fecha + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' });
}

export default function ReporteShow({ reporte }: Props) {
    const { can } = useCan();
    const puedeEditar = can('cob.reportes.gestionar');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/dashboard' },
        { title: 'Reporte semanal', href: '/admin/cob/reportes' },
        { title: `${reporte.anio} · S${reporte.semana}`, href: `/admin/cob/reportes/${reporte.anio}/${reporte.semana}` },
    ];

    const cobrosPorObra = useMemo(() => {
        const grupos = new Map<string, { obra_no: string; cobros: CobReporteCobro[]; sin: number; con: number }>();
        for (const c of reporte.cobros) {
            const g = grupos.get(c.obra_no) ?? { obra_no: c.obra_no, cobros: [], sin: 0, con: 0 };
            g.cobros.push(c);
            g.sin += Number(c.monto_sin_iva);
            g.con += Number(c.monto_con_iva);
            grupos.set(c.obra_no, g);
        }
        return [...grupos.values()];
    }, [reporte.cobros]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Reporte ${reporte.anio} S${reporte.semana}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold">Reporte semanal — {reporte.anio} · Semana {reporte.semana}</h1>
                        <p className="text-base-content/60 text-sm">{fmtFecha(reporte.fecha_inicio)} – {fmtFecha(reporte.fecha_fin)}</p>
                    </div>
                    <a href={`/admin/cob/reportes/${reporte.anio}/${reporte.semana}/pdf`} className="btn btn-primary btn-sm" target="_blank" rel="noopener noreferrer">
                        Imprimir PDF
                    </a>
                </div>

                <SaldosCard reporte={reporte} />

                <section className="rounded-box border border-base-300">
                    <div className="border-b border-base-300 px-4 py-2 font-semibold">Detonaciones de obras (suman al saldo)</div>
                    <table className="table table-sm">
                        <thead><tr><th>Obra</th><th>Descripción</th><th className="text-right">Sin IVA</th><th className="text-right">Con IVA</th></tr></thead>
                        <tbody>
                            {reporte.detonaciones.map((d) => (
                                <tr key={d.obra_id}>
                                    <td>{d.obra_no}</td>
                                    <td>{d.descripcion ?? '-'}</td>
                                    <td className="text-right">{formatearMXN(Number(d.monto_sin_iva))}</td>
                                    <td className="text-right">{formatearMXN(Number(d.monto_con_iva))}</td>
                                </tr>
                            ))}
                            {reporte.detonaciones.length === 0 && (
                                <tr><td colSpan={4} className="py-4 text-center opacity-50">Sin obras nuevas en la semana</td></tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="font-semibold">
                                <td colSpan={2} className="text-right">Total detonaciones</td>
                                <td className="text-success text-right">+{formatearMXN(Number(reporte.total_detonaciones_sin_iva))}</td>
                                <td className="text-success text-right">+{formatearMXN(Number(reporte.total_detonaciones_con_iva))}</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <section className="rounded-box border border-base-300">
                    <div className="border-b border-base-300 px-4 py-2 font-semibold">Estimaciones cobradas por obra (restan al saldo)</div>
                    <table className="table table-sm">
                        <thead><tr><th>Obra</th><th>Estimación</th><th className="text-right">Sin IVA</th><th className="text-right">Con IVA</th></tr></thead>
                        <tbody>
                            {cobrosPorObra.map((g) => (
                                <Fragment key={g.obra_no}>
                                    {g.cobros.map((c, i) => (
                                        <tr key={c.estimacion_id}>
                                            {i === 0 && <td rowSpan={g.cobros.length} className="align-top font-medium">{g.obra_no}</td>}
                                            <td>Est. #{c.numero_estimacion}</td>
                                            <td className="text-right">{formatearMXN(Number(c.monto_sin_iva))}</td>
                                            <td className="text-right">{formatearMXN(Number(c.monto_con_iva))}</td>
                                        </tr>
                                    ))}
                                    <tr className="bg-base-200 text-sm">
                                        <td className="text-right opacity-70" colSpan={2}>Subtotal {g.obra_no}</td>
                                        <td className="text-right">{formatearMXN(g.sin)}</td>
                                        <td className="text-right">{formatearMXN(g.con)}</td>
                                    </tr>
                                </Fragment>
                            ))}
                            {cobrosPorObra.length === 0 && (
                                <tr><td colSpan={4} className="py-4 text-center opacity-50">Sin estimaciones cobradas en la semana</td></tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="font-semibold">
                                <td colSpan={2} className="text-right">Total cobrado</td>
                                <td className="text-error text-right">−{formatearMXN(Number(reporte.total_cobrado_sin_iva))}</td>
                                <td className="text-error text-right">−{formatearMXN(Number(reporte.total_cobrado_con_iva))}</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <NotasSection reporte={reporte} editable={puedeEditar} />
            </div>
        </AppLayout>
    );
}

function SaldoCol({ label, sin, con, fuerte }: { label: string; sin: number; con: number; fuerte?: boolean }) {
    return (
        <div className="rounded-box border border-base-300 p-4">
            <div className="text-base-content/60 text-xs">{label}</div>
            <div className={`text-lg ${fuerte ? 'font-bold' : 'font-semibold'}`}>{formatearMXN(sin)} <span className="text-base-content/40 text-xs">sin IVA</span></div>
            <div className="text-base-content/70 text-sm">{formatearMXN(con)} <span className="text-base-content/40 text-xs">con IVA</span></div>
        </div>
    );
}

function SaldosCard({ reporte }: { reporte: CobReporteSemana }) {
    return (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <SaldoCol label="Saldo anterior" sin={Number(reporte.saldo_anterior_sin_iva)} con={Number(reporte.saldo_anterior_con_iva)} />
            <SaldoCol label="+ Detonaciones" sin={Number(reporte.total_detonaciones_sin_iva)} con={Number(reporte.total_detonaciones_con_iva)} />
            <SaldoCol label="− Cobrado" sin={Number(reporte.total_cobrado_sin_iva)} con={Number(reporte.total_cobrado_con_iva)} />
            <SaldoCol label="= Nuevo saldo" sin={Number(reporte.saldo_nuevo_sin_iva)} con={Number(reporte.saldo_nuevo_con_iva)} fuerte />
        </div>
    );
}

function NotasSection({ reporte, editable }: { reporte: CobReporteSemana; editable: boolean }) {
    const form = useForm({ notas: reporte.notas ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/cob/reportes/${reporte.anio}/${reporte.semana}/notas`, { preserveScroll: true });
    };

    if (!editable) {
        return (
            <section className="card bg-base-100 border p-6">
                <h2 className="mb-2 text-lg font-semibold">Notas</h2>
                <p className="whitespace-pre-wrap text-sm">{reporte.notas?.trim() ? reporte.notas : 'Sin notas.'}</p>
            </section>
        );
    }

    return (
        <section className="card bg-base-100 border p-6">
            <h2 className="mb-4 text-lg font-semibold">Notas / comentarios</h2>
            <form onSubmit={submit} className="space-y-4">
                <FormField label="Describe cualquier edición a los totales que no provenga de un cobro o una detonación" htmlFor="notas" error={form.errors.notas}>
                    <textarea className="textarea textarea-bordered w-full" rows={4} value={form.data.notas} onChange={(e) => form.setData('notas', e.target.value)} />
                </FormField>
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                        Guardar notas
                    </Button>
                </div>
            </form>
        </section>
    );
}
