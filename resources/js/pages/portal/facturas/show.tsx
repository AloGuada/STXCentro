import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { formatMoney as fmtMonto } from '@/components/costos/monto';
import { type FormEvent, useState } from 'react';
import { PortalRegistrarNotaCreditoModal } from '@/components/portal/registrar-nota-credito-modal';
import { Button } from '@/components/ui/button';
import PortalLayout from '@/layouts/portal/portal-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura } from '@/types/models';
import {
    FACTURA_ESTATUS_COLORS,
    FACTURA_ESTATUS_LABELS,
    NOTA_CREDITO_ESTATUS_COLORS,
    NOTA_CREDITO_ESTATUS_LABELS,
    PAGO_ESTATUS_COLORS,
    PAGO_ESTATUS_LABELS,
} from '@/types/models';

type Comprobante = {
    permitido_hoy: boolean;
    dia: number | null;
    subido: boolean;
};

type Props = {
    factura: CostosFactura;
    comprobante: Comprobante;
};

const DIAS_LABEL: Record<number, string> = {
    0: 'domingos',
    1: 'lunes',
    2: 'martes',
    3: 'miércoles',
    4: 'jueves',
    5: 'viernes',
    6: 'sábados',
};

export default function PortalFacturaShow({ factura, comprobante }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/portal' },
        { title: 'Facturas', href: '/portal/facturas' },
        { title: factura.folio, href: `/portal/facturas/${factura.id}` },
    ];

    const [showNotaModal, setShowNotaModal] = useState(false);

    const comprobanteForm = useForm<{ comprobante: File | null }>({ comprobante: null });
    const submitComprobante = (e: FormEvent) => {
        e.preventDefault();
        comprobanteForm.post(`/portal/facturas/${factura.id}/comprobante`, {
            forceFormData: true,
            onSuccess: () => comprobanteForm.reset('comprobante'),
        });
    };

    const formatMoney = (n: number) => fmtMonto(n, factura.moneda);

    const notas = factura.notas_credito ?? [];
    const totalNotas = Number(factura.monto_notas_credito ?? 0);
    const saldoFacturado = Number(factura.saldo_facturado ?? factura.total);

    const puedeSubirNota = factura.estatus !== 'cancelada' && saldoFacturado > 0;

    return (
        <PortalLayout breadcrumbs={breadcrumbs}>
            <Head title={factura.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{factura.folio}</h1>
                        <div className="flex items-center gap-2 mt-1">
                            <span className={`badge ${FACTURA_ESTATUS_COLORS[factura.estatus]}`}>
                                {FACTURA_ESTATUS_LABELS[factura.estatus]}
                            </span>
                            {factura.aprobada_costos && (
                                <span className="badge badge-success">Aprobada Costos</span>
                            )}
                            {factura.aceptada_contabilidad && (
                                <span className="badge badge-info">Aceptada Contabilidad</span>
                            )}
                            {factura.orden_compra && (
                                <Link href={`/portal/ordenes-compra/${factura.orden_compra.id}`} className="link link-primary text-sm">
                                    OC: {factura.orden_compra.folio}
                                </Link>
                            )}
                        </div>
                    </div>

                    {puedeSubirNota && (
                        <Button onClick={() => setShowNotaModal(true)}>
                            Subir nota de crédito
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Factura</span>
                            <p className="font-medium">
                                {factura.fecha_factura ? new Date(factura.fecha_factura).toLocaleDateString() : '-'}
                            </p>
                        </div>
                        {factura.uuid_fiscal && (
                            <div>
                                <span className="text-sm text-base-content/60">UUID Fiscal</span>
                                <p className="font-mono text-sm">{factura.uuid_fiscal}</p>
                            </div>
                        )}
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Subtotal</span>
                            <p className="font-medium">{formatMoney(factura.subtotal)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">IVA</span>
                            <p className="font-medium">{formatMoney(factura.iva)}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Total</span>
                            <p className="font-medium text-lg">{formatMoney(factura.total)}</p>
                        </div>
                    </div>
                </div>

                {/* Comprobante de recepción */}
                {(factura.estatus === 'pendiente_recepcion' || comprobante.subido) && (
                    <div className="mb-6">
                        <h2 className="text-lg font-medium mb-3">Comprobante de recepción</h2>
                        <div className="rounded-lg border border-base-300 p-4 space-y-3">
                            <div className="flex flex-wrap gap-2 text-sm">
                                <span className={`badge ${factura.completamente_entregada ? 'badge-success' : 'badge-ghost'}`}>
                                    {factura.completamente_entregada ? 'Entregada por almacén' : 'Pendiente de entrega'}
                                </span>
                                <span className={`badge ${comprobante.subido ? 'badge-success' : 'badge-ghost'}`}>
                                    {comprobante.subido ? 'Comprobante recibido' : 'Comprobante pendiente'}
                                </span>
                            </div>

                            {factura.estatus === 'pendiente_recepcion' ? (
                                comprobante.permitido_hoy ? (
                                    <form onSubmit={submitComprobante} className="space-y-2">
                                        <input
                                            type="file"
                                            accept=".pdf,image/*"
                                            className="file-input file-input-bordered w-full max-w-md"
                                            onChange={(e) => comprobanteForm.setData('comprobante', e.target.files?.[0] ?? null)}
                                        />
                                        {comprobanteForm.errors.comprobante && (
                                            <p className="text-sm text-error">{comprobanteForm.errors.comprobante}</p>
                                        )}
                                        <p className="text-xs text-base-content/60">
                                            Adjunta el acuse/remisión sellado por almacén (PDF o imagen). Cuando la factura esté entregada
                                            y con comprobante, pasará a revisión de Costos y Contabilidad.
                                        </p>
                                        <Button type="submit" disabled={comprobanteForm.processing || !comprobanteForm.data.comprobante}>
                                            {comprobanteForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                            {comprobante.subido ? 'Reemplazar comprobante' : 'Subir comprobante'}
                                        </Button>
                                    </form>
                                ) : (
                                    <p className="text-sm text-warning">
                                        El comprobante de recepción solo puede subirse los{' '}
                                        {comprobante.dia !== null ? DIAS_LABEL[comprobante.dia] : 'cualquier día'}.
                                    </p>
                                )
                            ) : (
                                <p className="text-sm text-base-content/60">
                                    Comprobante recibido. La factura avanzó a revisión de Costos y Contabilidad.
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {/* Saldo y notas de crédito */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Notas de crédito</h2>
                    <div className="rounded-lg border border-base-300 p-4">
                        <div className="mb-3 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <div className="text-xs text-base-content/60">Total facturado</div>
                                <div className="font-semibold">{formatMoney(factura.total)}</div>
                            </div>
                            <div>
                                <div className="text-xs text-base-content/60">Notas vigentes</div>
                                <div className="font-semibold">{formatMoney(totalNotas)}</div>
                            </div>
                            <div>
                                <div className="text-xs text-base-content/60">Saldo facturado</div>
                                <div className="font-semibold text-success">{formatMoney(saldoFacturado)}</div>
                            </div>
                        </div>

                        {notas.length === 0 ? (
                            <p className="text-sm text-base-content/60">
                                No hay notas de crédito sobre esta factura.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th>Concepto</th>
                                            <th className="text-right">Monto</th>
                                            <th>Estatus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {notas.map((n) => (
                                            <tr key={n.id}>
                                                <td className="font-mono text-xs">{n.folio}</td>
                                                <td className="text-xs">{n.fecha_emision}</td>
                                                <td className="text-xs">{n.concepto}</td>
                                                <td className="text-right font-medium">{formatMoney(n.monto)}</td>
                                                <td>
                                                    <span className={`badge badge-sm ${NOTA_CREDITO_ESTATUS_COLORS[n.estatus]}`}>
                                                        {NOTA_CREDITO_ESTATUS_LABELS[n.estatus]}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

                {/* Entregas */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Entregas</h2>
                    {!factura.entregas || factura.entregas.length === 0 ? (
                        <p className="text-base-content/60">No hay entregas registradas.</p>
                    ) : (
                        <div className="space-y-3">
                            {factura.entregas.map((e) => (
                                <div key={e.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="flex justify-between">
                                        <span className="font-medium">Entrega #{e.id}</span>
                                        <span className="text-sm text-base-content/60">
                                            {new Date(e.fecha_entrega).toLocaleDateString()}
                                        </span>
                                    </div>
                                    {e.observaciones && (
                                        <p className="text-sm text-base-content/60 mt-1">{e.observaciones}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pago */}
                {factura.pago && (
                    <div>
                        <h2 className="text-lg font-medium mb-3">Pago</h2>
                        <div className="rounded-lg border border-base-300 p-4">
                            <div className="flex items-center gap-3">
                                <Link href={`/portal/pagos/${factura.pago.id}`} className="link link-primary font-medium">
                                    {factura.pago.folio}
                                </Link>
                                <span className={`badge ${PAGO_ESTATUS_COLORS[factura.pago.estatus]}`}>
                                    {PAGO_ESTATUS_LABELS[factura.pago.estatus]}
                                </span>
                                <span>{formatMoney(factura.pago.monto_pago)}</span>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <PortalRegistrarNotaCreditoModal
                facturaId={factura.id}
                saldoFacturado={saldoFacturado}
                open={showNotaModal}
                onClose={() => setShowNotaModal(false)}
            />
        </PortalLayout>
    );
}
