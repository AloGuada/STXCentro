import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosAnticipo } from '@/types/models';
import { ANTICIPO_ESTATUS_COLORS, ANTICIPO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Props = {
    anticipo: CostosAnticipo;
};

const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

export default function AnticipoShow({ anticipo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/anticipos' },
        { title: 'Anticipos', href: '/admin/costos/anticipos' },
        { title: anticipo.folio, href: `/admin/costos/anticipos/${anticipo.id}` },
    ];

    const { can } = useCan();
    const [cancelando, setCancelando] = useState(false);

    const aplicaciones = anticipo.aplicaciones ?? [];
    const totalAplicado = aplicaciones.reduce((sum, a) => sum + Number(a.monto), 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={anticipo.folio} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{anticipo.folio}</h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span className={`badge ${ANTICIPO_ESTATUS_COLORS[anticipo.estatus]}`}>
                                {ANTICIPO_ESTATUS_LABELS[anticipo.estatus]}
                            </span>
                            <span className="text-sm text-base-content/60">
                                {anticipo.proveedor?.razon_social} · {anticipo.fecha}
                            </span>
                        </div>
                    </div>

                    {anticipo.estatus === 'vigente' && aplicaciones.length === 0 && can('costos.anticipos.cancelar') && (
                        <Button variant="outline" className="text-error" onClick={() => setCancelando(true)}>
                            Cancelar anticipo
                        </Button>
                    )}
                </div>

                <div className="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="rounded-lg border border-base-300 p-4">
                        <div className="text-xs text-base-content/60">Monto entregado</div>
                        <div className="text-xl font-semibold">{formatMoney(anticipo.monto)} {anticipo.moneda.toUpperCase()}</div>
                    </div>
                    <div className="rounded-lg border border-base-300 p-4">
                        <div className="text-xs text-base-content/60">Aplicado a facturas</div>
                        <div className="text-xl font-semibold">{formatMoney(totalAplicado)}</div>
                    </div>
                    <div className="rounded-lg border border-base-300 p-4">
                        <div className="text-xs text-base-content/60">Saldo disponible</div>
                        <div className="text-xl font-semibold text-success">{formatMoney(anticipo.saldo_disponible)}</div>
                    </div>
                </div>

                {(anticipo.referencia || anticipo.obra || anticipo.notas) && (
                    <div className="mb-6 rounded-lg border border-base-300 p-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        {anticipo.referencia && (
                            <div>
                                <div className="text-xs text-base-content/60">Referencia</div>
                                <div>{anticipo.referencia}</div>
                            </div>
                        )}
                        {anticipo.obra && (
                            <div>
                                <div className="text-xs text-base-content/60">Obra</div>
                                <div>{anticipo.obra.descripcion}</div>
                            </div>
                        )}
                        {anticipo.notas && (
                            <div className="md:col-span-3">
                                <div className="text-xs text-base-content/60">Notas</div>
                                <div className="whitespace-pre-line">{anticipo.notas}</div>
                            </div>
                        )}
                    </div>
                )}

                <div className="rounded-lg border border-base-300 p-4">
                    <h2 className="mb-3 text-lg font-medium">Aplicaciones a facturas</h2>
                    {aplicaciones.length === 0 ? (
                        <p className="text-sm text-base-content/60">Aún no se ha aplicado este anticipo a ninguna factura.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Factura</th>
                                        <th>Fecha</th>
                                        <th className="text-right">Monto aplicado</th>
                                        <th>Aplicó</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {aplicaciones.map((a) => (
                                        <tr key={a.id}>
                                            <td>
                                                <Link
                                                    href={`/admin/costos/facturas/${a.factura_id}`}
                                                    className="link link-primary font-mono text-xs"
                                                >
                                                    {a.factura?.folio ?? `#${a.factura_id}`}
                                                </Link>
                                            </td>
                                            <td className="text-xs text-base-content/60">{a.fecha}</td>
                                            <td className="text-right font-medium">{formatMoney(a.monto)}</td>
                                            <td className="text-xs text-base-content/60">{a.usuario?.name ?? '-'}</td>
                                            <td className="text-xs text-base-content/60">{a.notas ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <CancelarModal
                    open={cancelando}
                    onClose={() => setCancelando(false)}
                    url={`/admin/costos/anticipos/${anticipo.id}/cancelar`}
                    title="Cancelar anticipo"
                    description="Solo se puede cancelar si no tiene aplicaciones a facturas."
                />
            </div>
        </AppLayout>
    );
}
