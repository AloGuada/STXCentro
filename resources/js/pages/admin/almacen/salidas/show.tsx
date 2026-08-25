import { BotonFormato } from '@/components/alm/boton-formato';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, LockIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });
const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type Props = {
    salida: {
        id: number;
        folio: string | null;
        fecha: string | null;
        almacen: string | null;
        almacen_nombre: string | null;
        obra_destino: string | null;
        departamento: string | null;
        grupo_trabajo: string | null;
        pedido_id: number | null;
        pedido_folio: string | null;
        solicitante: string | null;
        entrego: string | null;
        recibe: string | null;
        motivo: string | null;
        observaciones: string | null;
        cancelada: boolean;
        motivo_cancelacion: string | null;
        cancelada_at: string | null;
    };
    detalles: {
        id: number;
        codigo: string | null;
        descripcion: string | null;
        unidad: string | null;
        cantidad: number;
        costo_unitario: number | null;
        importe: number;
        observaciones: string | null;
    }[];
};

export default function SalidaShow({ salida, detalles }: Props) {
    const [cancelando, setCancelando] = useState(false);
    const form = useForm({ motivo: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Salidas', href: '/admin/almacen/salidas' },
        { title: salida.folio ?? String(salida.id), href: `/admin/almacen/salidas/${salida.id}` },
    ];

    const total = detalles.reduce((suma, d) => suma + d.importe, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Salida ${salida.folio ?? salida.id}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className={`font-mono text-2xl font-semibold ${salida.cancelada ? 'line-through' : ''}`}>
                                {salida.folio}
                            </h1>
                            {salida.cancelada && <span className="badge badge-ghost">Cancelada</span>}
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {salida.almacen} · {salida.almacen_nombre} · {salida.fecha}
                        </p>
                        <p className="text-base-content/60 text-sm">
                            Recibe {salida.recibe}
                            {salida.departamento && ` · ${salida.departamento}`}
                            {salida.grupo_trabajo && ` · ${salida.grupo_trabajo}`}
                            {salida.obra_destino && ` · ${salida.obra_destino}`}
                        </p>
                        {salida.pedido_folio && (
                            <p className="text-sm">
                                Surte{' '}
                                <Link
                                    href={`/admin/almacen/pedidos/${salida.pedido_id}`}
                                    className="link link-hover font-mono"
                                >
                                    {salida.pedido_folio}
                                </Link>
                            </p>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/salidas" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <BotonFormato href={`/admin/almacen/salidas/${salida.id}/pdf`} />
                        {!salida.cancelada && (
                            <button type="button" className="btn btn-outline" onClick={() => setCancelando(true)}>
                                Cancelar salida
                            </button>
                        )}
                    </div>
                </div>

                {salida.cancelada ? (
                    <div className="alert alert-warning mb-4">
                        <span>
                            Cancelada el {salida.cancelada_at}: {salida.motivo_cancelacion}. El material volvió al
                            kardex con un movimiento espejo, y el pedido volvió a deber lo que esta salida decía haber
                            entregado.
                        </span>
                    </div>
                ) : (
                    <div className="alert mb-4">
                        <LockIcon className="size-4" />
                        <span>
                            Este documento no se edita. Si algo quedó mal, se cancela —lo que devuelve el material al
                            kardex— y se captura la salida correcta.
                        </span>
                    </div>
                )}

                {salida.observaciones && (
                    <div className="rounded-box border-base-300 mb-4 border p-4">
                        <h2 className="mb-1 font-medium">Observaciones</h2>
                        <p className="text-base-content/80 text-sm">{salida.observaciones}</p>
                    </div>
                )}

                <div className="rounded-box border-base-300 border">
                    <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <h2 className="font-medium">Qué se entregó</h2>
                        {/* Al costo con el que salió, no al de hoy: es lo que hace
                            que el vale reimpreso siga diciendo lo mismo. */}
                        <p className="text-base-content/60 text-sm">
                            Valor de la salida: <span className="font-mono">{moneda(total)}</span>
                        </p>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Artículo</th>
                                <th>Unidad</th>
                                <th className="text-right">Cantidad</th>
                                <th className="text-right">Costo unitario</th>
                                <th className="text-right">Importe</th>
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
                                    <td className="text-right font-mono">{numero(d.cantidad)}</td>
                                    <td className="text-base-content/60 text-right font-mono">
                                        {d.costo_unitario === null ? '—' : moneda(d.costo_unitario)}
                                    </td>
                                    <td className="text-right font-mono">{moneda(d.importe)}</td>
                                    <td className="text-base-content/60 text-sm">{d.observaciones}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    La salida no afecta presupuesto: el gasto se reconoció en la compra, y Almacén sólo controla
                    artículos.
                </p>
            </div>

            {cancelando && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="text-lg font-semibold">Cancelar {salida.folio}</h3>
                        <p className="text-base-content/60 mb-4 text-sm">
                            El material vuelve al kardex con un movimiento espejo. El folio y los dos asientos quedan:
                            es lo que explica después por qué el saldo bajó y subió el mismo día.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.patch(`/admin/almacen/salidas/${salida.id}/cancelar`, {
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
                                    placeholder="Se capturó el almacén equivocado, no se entregó..."
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
                                    Cancelar salida
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
