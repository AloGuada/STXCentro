import { CancelarModal } from '@/components/costos/cancelar-modal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosNotaCredito } from '@/types/models';
import { NOTA_CREDITO_ESTATUS_COLORS, NOTA_CREDITO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link } from '@inertiajs/react';
import { FileIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    nota: CostosNotaCredito;
};

const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

export default function NotaCreditoShow({ nota }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/notas-credito' },
        { title: 'Notas de crédito', href: '/admin/costos/notas-credito' },
        { title: nota.folio, href: `/admin/costos/notas-credito/${nota.id}` },
    ];

    const { can } = useCan();
    const [cancelando, setCancelando] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={nota.folio} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{nota.folio}</h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <span className={`badge ${NOTA_CREDITO_ESTATUS_COLORS[nota.estatus]}`}>
                                {NOTA_CREDITO_ESTATUS_LABELS[nota.estatus]}
                            </span>
                            <span className="text-sm text-base-content/60">{nota.fecha_emision}</span>
                            {nota.factura && (
                                <Link
                                    href={`/admin/costos/facturas/${nota.factura.id}`}
                                    className="link link-primary text-sm"
                                >
                                    Factura: {nota.factura.folio}
                                </Link>
                            )}
                        </div>
                    </div>

                    {nota.estatus === 'vigente' && can('costos.notas-credito.cancelar') && (
                        <Button variant="outline" className="text-error" onClick={() => setCancelando(true)}>
                            Cancelar nota
                        </Button>
                    )}
                </div>

                <div className="mb-6 rounded-lg border border-base-300 p-4">
                    <h2 className="mb-3 font-medium">Datos fiscales</h2>
                    <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <dt className="text-base-content/60">Subtotal</dt>
                            <dd className="font-medium">{formatMoney(nota.subtotal)}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">IVA trasladado</dt>
                            <dd className="font-medium">{formatMoney(nota.iva_trasladado)}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Monto total</dt>
                            <dd className="font-medium text-lg">{formatMoney(nota.monto)}</dd>
                        </div>
                        <div>
                            <dt className="text-base-content/60">Folio fiscal</dt>
                            <dd>{nota.folio_fiscal ?? '-'}</dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-base-content/60">UUID fiscal</dt>
                            <dd className="font-mono text-xs break-all">{nota.uuid_fiscal ?? '-'}</dd>
                        </div>
                        <div className="md:col-span-2">
                            <dt className="text-base-content/60">Concepto</dt>
                            <dd className="whitespace-pre-line">{nota.concepto}</dd>
                        </div>
                    </dl>

                    {nota.impuestos_detalle && (
                        <details className="mt-4">
                            <summary className="cursor-pointer text-sm text-base-content/70">
                                Ver detalle por concepto (CFDI)
                            </summary>
                            <div className="mt-2 space-y-3">
                                {nota.impuestos_detalle.traslados.length > 0 && (
                                    <div>
                                        <div className="text-xs font-medium mb-1">Traslados</div>
                                        <table className="table table-xs">
                                            <thead>
                                                <tr>
                                                    <th>Impuesto</th>
                                                    <th>Tasa</th>
                                                    <th className="text-right">Base</th>
                                                    <th className="text-right">Importe</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {nota.impuestos_detalle.traslados.map((t, i) => (
                                                    <tr key={i}>
                                                        <td>{t.impuesto}</td>
                                                        <td>{t.tasa}</td>
                                                        <td className="text-right">{formatMoney(t.base)}</td>
                                                        <td className="text-right">{formatMoney(t.importe)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </details>
                    )}
                </div>

                {(nota.media_xml || nota.media_pdf) && (
                    <div className="mb-6 rounded-lg border border-base-300 p-4">
                        <h2 className="mb-3 font-medium">Documentos</h2>
                        <div className="flex gap-3">
                            {nota.media_xml && (
                                <a href={`/storage/${nota.media_xml.path}`} target="_blank" rel="noopener noreferrer"
                                    className="link link-primary text-sm inline-flex items-center gap-1">
                                    <FileIcon className="size-3.5" /> XML
                                </a>
                            )}
                            {nota.media_pdf && (
                                <a href={`/storage/${nota.media_pdf.path}`} target="_blank" rel="noopener noreferrer"
                                    className="link link-primary text-sm inline-flex items-center gap-1">
                                    <FileIcon className="size-3.5" /> PDF
                                </a>
                            )}
                        </div>
                    </div>
                )}

                {nota.motivo_cancelacion && (
                    <div className="alert alert-error mb-4">
                        <span><strong>Motivo de cancelación:</strong> {nota.motivo_cancelacion}</span>
                    </div>
                )}

                <CancelarModal
                    open={cancelando}
                    onClose={() => setCancelando(false)}
                    url={`/admin/costos/notas-credito/${nota.id}/cancelar`}
                    title="Cancelar nota de crédito"
                    description="La nota dejará de afectar el saldo de la factura."
                />
            </div>
        </AppLayout>
    );
}
