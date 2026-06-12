import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Columna = {
    columna_id: number;
    nombre: string;
    kg: number;
    m2_pintura: number;
    importe_materiales: number;
};

type Props = {
    obra: { id: number; nombre: string; op: string | null };
    columnas: Columna[];
    obraTotales: { kg_total: number; m2_montaje_total: number };
    importeTotalVenta: number;
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const fmtMoney = (n: number) => money.format(Number(n ?? 0));
const fmtNum = (n: number, d = 2) =>
    Number(n ?? 0).toLocaleString('es-MX', {
        maximumFractionDigits: d,
        minimumFractionDigits: 0,
    });

export default function CaratulaIndex({
    obra,
    columnas,
    obraTotales,
    importeTotalVenta,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cotización', href: '/admin/cotiz/obras' },
        { title: 'Obras', href: '/admin/cotiz/obras' },
        {
            title: obra.nombre,
            href: `/admin/cotiz/obras/${obra.id}/generadoras`,
        },
        { title: 'Carátula', href: `/admin/cotiz/obras/${obra.id}/caratula` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Carátula — ${obra.nombre}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Carátula de Cotización
                        </h1>
                        <p className="text-sm text-base-content/60">
                            {obra.nombre}
                            {obra.op ? ` · OP ${obra.op}` : ''}
                        </p>
                    </div>
                    <div className="ml-auto flex items-center gap-2">
                        <span className="badge badge-lg badge-primary">
                            Importe total de venta:{' '}
                            {fmtMoney(importeTotalVenta)}
                        </span>
                        <a
                            href={`/admin/cotiz/obras/${obra.id}/caratula/pdf`}
                            className="btn btn-outline btn-sm"
                        >
                            Descargar PDF
                        </a>
                    </div>
                </div>

                <div className="card border border-base-300 bg-base-100 p-4">
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tarjeta / Nave</th>
                                    <th className="text-right">Kg reales</th>
                                    <th className="text-right">m² pintura</th>
                                    <th className="text-right">
                                        Importe materiales
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {columnas.map((c) => (
                                    <tr key={c.columna_id}>
                                        <td className="font-medium">
                                            {c.nombre}
                                        </td>
                                        <td className="text-right">
                                            {fmtNum(c.kg, 2)}
                                        </td>
                                        <td className="text-right">
                                            {fmtNum(c.m2_pintura, 2)}
                                        </td>
                                        <td className="text-right">
                                            {fmtMoney(c.importe_materiales)}
                                        </td>
                                    </tr>
                                ))}
                                {columnas.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="py-6 text-center text-sm italic opacity-50"
                                        >
                                            Sin tarjetas en la obra.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            <tfoot>
                                <tr className="border-t-2 font-semibold">
                                    <td>TOTALES</td>
                                    <td className="text-right">
                                        {fmtNum(obraTotales.kg_total, 2)} kg
                                    </td>
                                    <td className="text-right">
                                        {fmtNum(
                                            obraTotales.m2_montaje_total,
                                            2,
                                        )}{' '}
                                        m²
                                    </td>
                                    <td className="text-right">
                                        {fmtMoney(importeTotalVenta)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p className="text-xs opacity-60">
                    Usa «Descargar PDF» para exportar la carátula. (Export a
                    XLSX pendiente.)
                </p>
            </div>
        </AppLayout>
    );
}
