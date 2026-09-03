import { BotonFormato } from '@/components/alm/boton-formato';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmPedidoEstatus } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, NetworkIcon, PackageIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

const CLASE_ESTATUS: Record<AlmPedidoEstatus, string> = {
    borrador: 'badge-ghost',
    pendiente: 'badge-warning',
    aprobado: 'badge-info',
    surtido: 'badge-success',
    cancelado: 'badge-ghost',
    rechazado: 'badge-error',
};

type Props = {
    pedido: {
        id: number;
        folio: string | null;
        fecha: string | null;
        fecha_requerida: string | null;
        almacen: string | null;
        almacen_id: number;
        departamento: string | null;
        obra: string | null;
        recibe: string | null;
        solicitante: string | null;
        estatus: AlmPedidoEstatus;
        estatus_etiqueta: string;
        renglones: number;
        se_surte_con: 'salida' | 'transferencia';
        avance: { solicitado: number; surtido: number; renglones_pendientes: number };
        motivo: string | null;
        observaciones: string | null;
        grupo_trabajo: string | null;
        motivo_rechazo: string | null;
    };
    detalles: {
        id: number;
        producto_id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        cantidad_solicitada: number;
        cantidad_surtida: number;
        pendiente: number;
        observaciones: string | null;
    }[];
};

export default function PedidoShow({ pedido, detalles }: Props) {
    const [cancelando, setCancelando] = useState(false);
    const form = useForm({ motivo: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Pedidos', href: '/admin/almacen/pedidos' },
        { title: pedido.folio ?? String(pedido.id), href: `/admin/almacen/pedidos/${pedido.id}` },
    ];

    const puedeSurtirse = pedido.estatus === 'aprobado' && pedido.avance.renglones_pendientes > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pedido ${pedido.folio ?? pedido.id}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{pedido.folio}</h1>
                            <span className={`badge badge-sm ${CLASE_ESTATUS[pedido.estatus]}`}>
                                {pedido.estatus_etiqueta}
                            </span>
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {pedido.departamento} le pide a {pedido.almacen} · {pedido.fecha} · se necesita el{' '}
                            {pedido.fecha_requerida}
                        </p>
                        <p className="text-base-content/60 text-sm">
                            {pedido.obra ?? 'Consumo interno de planta'}
                            {pedido.recibe && ` · a nombre de ${pedido.recibe}`}
                            {pedido.grupo_trabajo && ` · ${pedido.grupo_trabajo}`}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/pedidos" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                            <BotonFormato href={`/admin/almacen/pedidos/${pedido.id}/pdf`} />

                        {puedeSurtirse &&
                            (pedido.se_surte_con === 'salida' ? (
                                <ButtonLink
                                    href={`/admin/almacen/salidas/create?pedido_id=${pedido.id}&almacen_id=${pedido.almacen_id}`}
                                    variant="primary"
                                >
                                    <PackageIcon className="size-4" />
                                    Surtir con salida
                                </ButtonLink>
                            ) : (
                                <ButtonLink
                                    href={`/admin/almacen/transferencias/create?pedido_id=${pedido.id}`}
                                    variant="primary"
                                >
                                    <NetworkIcon className="size-4" />
                                    Surtir con transferencia
                                </ButtonLink>
                            ))}

                        {!['surtido', 'cancelado', 'rechazado'].includes(pedido.estatus) && (
                            <button type="button" className="btn btn-outline" onClick={() => setCancelando(true)}>
                                Cancelar pedido
                            </button>
                        )}
                    </div>
                </div>

                <div className="alert mb-4">
                    {pedido.se_surte_con === 'transferencia' ? (
                        <span>
                            El material va al almacén de la obra: lo surte una transferencia, y la obra confirma cuando
                            lo recibe.
                        </span>
                    ) : (
                        <span>El material se queda en planta: lo surte una salida directa del almacén.</span>
                    )}
                </div>

                {pedido.motivo_rechazo && (
                    <div className="alert alert-warning mb-4">
                        <span>{pedido.motivo_rechazo}</span>
                    </div>
                )}

                {pedido.observaciones && (
                    <div className="rounded-box border-base-300 mb-4 border p-4">
                        <h2 className="mb-1 font-medium">Observaciones</h2>
                        <p className="text-base-content/80 text-sm">{pedido.observaciones}</p>
                    </div>
                )}

                <div className="rounded-box border-base-300 border">
                    <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <h2 className="font-medium">Qué se pidió</h2>
                        <p className="text-base-content/60 text-sm">
                            {numero(pedido.avance.surtido)} de {numero(pedido.avance.solicitado)} entregado
                        </p>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Artículo</th>
                                <th>Unidad</th>
                                <th className="text-right">Solicitado</th>
                                <th className="text-right">Surtido</th>
                                <th className="text-right">Falta</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map((d) => (
                                <tr key={d.id} className="hover">
                                    <td>
                                        <span className="font-mono text-xs">{d.codigo}</span>
                                        <span className="block">{d.descripcion}</span>
                                    </td>
                                    <td className="text-base-content/60 font-mono text-xs">{d.unidad}</td>
                                    <td className="text-right font-mono">{numero(d.cantidad_solicitada)}</td>
                                    <td className="text-right font-mono">{numero(d.cantidad_surtida)}</td>
                                    <td className="text-right font-mono">
                                        {d.pendiente === 0 ? (
                                            <span className="text-success">Completo</span>
                                        ) : (
                                            <span className="text-warning font-medium">{numero(d.pendiente)}</span>
                                        )}
                                    </td>
                                    <td className="text-base-content/60 text-sm">{d.observaciones}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {cancelando && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="text-lg font-semibold">Cancelar {pedido.folio}</h3>
                        <p className="text-base-content/60 mb-4 text-sm">
                            Deja de aparecer entre lo que el almacén debe. Lo que ya se entregó no se devuelve: eso
                            regresa con una transferencia de sobrante.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.patch(`/admin/almacen/pedidos/${pedido.id}/cancelar`, {
                                    preserveScroll: true,
                                    onSuccess: () => setCancelando(false),
                                });
                            }}
                        >
                            <label className="form-control">
                                <span className="label label-text">¿Por qué se cancela?</span>
                                <Input
                                    value={form.data.motivo}
                                    onChange={(e) => form.setData('motivo', e.target.value)}
                                    placeholder="Ya no se necesita, se pidió de más..."
                                    autoFocus
                                />
                                {form.errors.motivo && (
                                    <span className="text-error text-xs">{form.errors.motivo}</span>
                                )}
                            </label>

                            <div className="modal-action">
                                <button type="button" className="btn btn-ghost" onClick={() => setCancelando(false)}>
                                    Volver
                                </button>
                                <button type="submit" className="btn btn-error" disabled={form.processing}>
                                    Cancelar pedido
                                </button>
                            </div>
                        </form>
                    </div>
                    <div className="modal-backdrop" onClick={() => setCancelando(false)} />
                </dialog>
            )}
        </AppLayout>
    );
}
