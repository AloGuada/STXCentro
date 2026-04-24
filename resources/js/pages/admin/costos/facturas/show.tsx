import { ActivityTimeline } from '@/components/costos/activity-timeline';
import { CancelarModal } from '@/components/costos/cancelar-modal';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosFactura } from '@/types/models';
import { ENTREGA_TIPO_LABELS, FACTURA_ESTATUS_COLORS, FACTURA_ESTATUS_LABELS, PAGO_ESTATUS_COLORS, PAGO_ESTATUS_LABELS } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileIcon, Loader2Icon } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

type Props = {
    factura: CostosFactura;
};

export default function FacturasShow({ factura }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/facturas' },
        { title: 'Facturas', href: '/admin/costos/facturas' },
        { title: factura.folio, href: `/admin/costos/facturas/${factura.id}` },
    ];

    const { can } = useCan();
    const [showEntregaModal, setShowEntregaModal] = useState(false);
    const [showAprobarModal, setShowAprobarModal] = useState(false);
    const [showAceptarModal, setShowAceptarModal] = useState(false);
    const [showCerrarModal, setShowCerrarModal] = useState(false);
    const [aprobarProcessing, setAprobarProcessing] = useState(false);
    const [aceptarProcessing, setAceptarProcessing] = useState(false);
    const formatMoney = (n: number) => `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

    const fechaPago = useMemo(() => {
        const dias = factura.proveedor?.dias_credito_default;
        if (!dias || dias <= 0) return null;
        const base = new Date();
        base.setDate(base.getDate() + dias);
        const day = base.getDay();
        if (day === 6) base.setDate(base.getDate() + 6);
        else if (day === 0) base.setDate(base.getDate() + 5);
        else if (day < 5) base.setDate(base.getDate() + (5 - day));
        return base;
    }, [factura.proveedor?.dias_credito_default]);

    const { data, setData, post, processing, errors } = useForm({
        fecha_entrega: new Date().toISOString().split('T')[0],
        tipo: 'completa' as 'parcial' | 'completa',
        observaciones: '',
        archivo: null as File | null,
    });

    const handleEntregaSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/costos/facturas/${factura.id}/entregas`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setShowEntregaModal(false),
        });
    };

    const hasEntregas = (factura.entregas?.length ?? 0) > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={factura.folio} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
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
                                <Link href={`/admin/costos/ordenes-compra/${factura.orden_compra.id}`} className="link link-primary text-sm">
                                    OC: {factura.orden_compra.folio}
                                </Link>
                            )}
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {factura.estatus === 'pendiente_entrega' && can('costos.entregas.crear') && (
                            <Button onClick={() => setShowEntregaModal(true)}>Registrar Entrega</Button>
                        )}
                        {factura.estatus === 'pendiente_aprobacion' && !factura.aprobada_costos && can('costos.facturas.aprobar') && (
                            <Button onClick={() => setShowAprobarModal(true)}>
                                Aprobar Costos
                            </Button>
                        )}
                        {factura.estatus === 'pendiente_pago' && factura.aprobada_costos && !factura.aceptada_contabilidad && can('costos.facturas.aceptar-contabilidad') && (
                            <Button onClick={() => setShowAceptarModal(true)}>
                                Aceptar y Programar Pago
                            </Button>
                        )}
                        {factura.estatus !== 'pagada' && factura.estatus !== 'cancelada' && !factura.aceptada_contabilidad && can('costos.facturas.cancelar') && (
                            <Button variant="destructive" onClick={() => setShowCerrarModal(true)}>
                                Cerrar factura
                            </Button>
                        )}
                    </div>
                </div>

                {/* Factura info */}
                <div className="grid grid-cols-2 gap-6 mb-6">
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Proveedor</span>
                            <p className="font-medium">{factura.proveedor?.razon_social}</p>
                        </div>
                        <div>
                            <span className="text-sm text-base-content/60">Fecha Factura</span>
                            <p className="font-medium">{factura.fecha_factura ? new Date(factura.fecha_factura).toLocaleDateString() : '-'}</p>
                        </div>
                    </div>
                    <div className="space-y-3">
                        <div>
                            <span className="text-sm text-base-content/60">Total</span>
                            <p className="font-medium text-lg">{formatMoney(factura.total)}</p>
                        </div>
                        {factura.uuid_fiscal && (
                            <div>
                                <span className="text-sm text-base-content/60">UUID Fiscal</span>
                                <p className="font-mono text-sm">{factura.uuid_fiscal}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Entregas */}
                <div className="mb-6">
                    <h2 className="text-lg font-medium mb-3">Entregas</h2>
                    {!hasEntregas ? (
                        <p className="text-base-content/60">No hay entregas registradas.</p>
                    ) : (
                        <div className="space-y-3">
                            {factura.entregas!.map((e) => (
                                <div key={e.id} className="rounded-lg border border-base-300 p-4">
                                    <div className="flex justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">Entrega #{e.id}</span>
                                            <span className={`badge badge-sm ${e.tipo === 'completa' ? 'badge-success' : 'badge-warning'}`}>
                                                {ENTREGA_TIPO_LABELS[e.tipo]}
                                            </span>
                                        </div>
                                        <span className="text-sm text-base-content/60">
                                            {new Date(e.fecha_entrega).toLocaleDateString()} - {e.recibidor?.name}
                                        </span>
                                    </div>
                                    {e.observaciones && (
                                        <p className="text-sm text-base-content/60 mt-1">{e.observaciones}</p>
                                    )}
                                    {e.media?.path && (
                                        <a href={`/storage/${e.media.path}`} target="_blank" rel="noopener noreferrer" className="link link-primary text-sm inline-flex items-center gap-1 mt-1">
                                            <FileIcon className="size-3" /> Ver documento
                                        </a>
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
                                <Link href={`/admin/costos/pagos/${factura.pago.id}`} className="link link-primary font-medium">
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

                <div className="mt-8">
                    <h2 className="text-lg font-medium mb-3">Historial</h2>
                    <ActivityTimeline activities={factura.activities ?? []} />
                </div>

                {/* Aprobar Costos Modal */}
                {showAprobarModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Confirmar Aprobación de Costos</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Folio Factura</span>
                                    <span className="font-medium">{factura.folio}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Proveedor</span>
                                    <span className="font-medium">{factura.proveedor?.razon_social}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Total</span>
                                    <span className="font-medium">{formatMoney(factura.total)}</span>
                                </div>
                            </div>
                            <p className="mt-4 text-sm text-base-content/60">
                                Se aprobará la factura para su pago por contabilidad.
                            </p>
                            <div className="modal-action">
                                <Button variant="outline" onClick={() => setShowAprobarModal(false)} disabled={aprobarProcessing}>
                                    Cancelar
                                </Button>
                                <Button
                                    disabled={aprobarProcessing}
                                    onClick={() => {
                                        setAprobarProcessing(true);
                                        router.post(
                                            `/admin/costos/facturas/${factura.id}/aprobar-costos`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () => {
                                                    setAprobarProcessing(false);
                                                    setShowAprobarModal(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    {aprobarProcessing && <Loader2Icon className="size-4 animate-spin" />}
                                    Confirmar
                                </Button>
                            </div>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowAprobarModal(false)}></div>
                    </dialog>
                )}

                {/* Aceptar Contabilidad Modal */}
                {showAceptarModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Aceptar Factura y Programar Pago</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Folio Factura</span>
                                    <span className="font-medium">{factura.folio}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Proveedor</span>
                                    <span className="font-medium">{factura.proveedor?.razon_social}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Total</span>
                                    <span className="font-medium">{formatMoney(factura.total)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-base-content/60">Tipo de Pago</span>
                                    <span className="font-medium">
                                        {factura.proveedor?.maneja_credito
                                            ? `Crédito (${factura.proveedor.dias_credito_default} días)`
                                            : 'Contado'}
                                    </span>
                                </div>
                                {fechaPago && (
                                    <div className="flex justify-between">
                                        <span className="text-base-content/60">Fecha estimada de pago</span>
                                        <span className="font-medium">
                                            Viernes {fechaPago.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })}
                                        </span>
                                    </div>
                                )}
                            </div>
                            <p className="mt-4 text-sm text-base-content/60">
                                Se creará el pago programado al viernes más cercano y se notificará al proveedor por correo.
                            </p>
                            <div className="modal-action">
                                <Button variant="outline" onClick={() => setShowAceptarModal(false)} disabled={aceptarProcessing}>
                                    Cancelar
                                </Button>
                                <Button
                                    disabled={aceptarProcessing}
                                    onClick={() => {
                                        setAceptarProcessing(true);
                                        router.post(
                                            `/admin/costos/facturas/${factura.id}/aceptar-contabilidad`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () => {
                                                    setAceptarProcessing(false);
                                                    setShowAceptarModal(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    {aceptarProcessing && <Loader2Icon className="size-4 animate-spin" />}
                                    Aceptar y Programar Pago
                                </Button>
                            </div>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowAceptarModal(false)}></div>
                    </dialog>
                )}

                <CancelarModal
                    open={showCerrarModal}
                    onClose={() => setShowCerrarModal(false)}
                    url={`/admin/costos/facturas/${factura.id}/cancelar`}
                    title={`Cerrar factura ${factura.folio}`}
                    description="La factura quedará marcada como cancelada y no podrá continuar su flujo de aprobación o pago."
                    submitLabel="Cerrar factura"
                />

                {/* Entrega Modal */}
                {showEntregaModal && (
                    <dialog className="modal modal-open">
                        <div className="modal-box">
                            <h3 className="font-bold text-lg mb-4">Registrar Entrega</h3>
                            <form onSubmit={handleEntregaSubmit} className="space-y-4">
                                <FormField label="Fecha de Entrega" htmlFor="fecha_entrega" error={errors.fecha_entrega} required>
                                    <Input
                                        id="fecha_entrega"
                                        type="date"
                                        value={data.fecha_entrega}
                                        onChange={(e) => setData('fecha_entrega', e.target.value)}
                                    />
                                </FormField>
                                <FormField label="Tipo de Entrega" htmlFor="tipo" error={errors.tipo} required>
                                    <select
                                        id="tipo"
                                        className="select select-bordered w-full"
                                        value={data.tipo}
                                        onChange={(e) => setData('tipo', e.target.value as 'parcial' | 'completa')}
                                    >
                                        <option value="completa">Completa</option>
                                        <option value="parcial">Parcial</option>
                                    </select>
                                </FormField>
                                <FormField label="Observaciones" htmlFor="observaciones" error={errors.observaciones}>
                                    <textarea
                                        id="observaciones"
                                        className="textarea textarea-bordered w-full"
                                        value={data.observaciones}
                                        onChange={(e) => setData('observaciones', e.target.value)}
                                        rows={2}
                                    />
                                </FormField>
                                <FormField label="Documento" htmlFor="archivo" error={errors.archivo}>
                                    <input
                                        id="archivo"
                                        type="file"
                                        className="file-input file-input-bordered file-input-sm w-full"
                                        onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                                    />
                                </FormField>

                                <div className="modal-action">
                                    <Button type="button" variant="outline" onClick={() => setShowEntregaModal(false)}>Cancelar</Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                                        Confirmar Entrega
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div className="modal-backdrop" onClick={() => setShowEntregaModal(false)}></div>
                    </dialog>
                )}
            </div>
        </AppLayout>
    );
}
