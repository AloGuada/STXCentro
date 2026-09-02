import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmTransferenciaEstatus } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, ArrowRightIcon, TriangleAlertIcon, TruckIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Detalle = {
    id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    cantidad_enviada: number;
    /** `null` es «todavía no se confirma», que no es lo mismo que «llegó cero». */
    cantidad_recibida: number | null;
    faltante: number;
    costo_unitario: number | null;
    observaciones: string | null;
};

type Props = {
    transferencia: {
        id: number;
        folio: string | null;
        fecha_envio: string | null;
        fecha_recepcion: string | null;
        origen: string | null;
        origen_nombre: string | null;
        destino: string | null;
        destino_nombre: string | null;
        estatus: AlmTransferenciaEstatus;
        estatus_etiqueta: string;
        pedido_id: number | null;
        pedido_folio: string | null;
        envio: string | null;
        recibio: string | null;
        faltante_responsable: string | null;
        renglones: number;
        cancelada: boolean;
        observaciones: string | null;
        motivo_cancelacion: string | null;
        resumen: { enviado: number; recibido: number; faltante: number; renglones_con_faltante: number };
        puede_recibir: boolean;
    };
    detalles: Detalle[];
    usuarios: { id: string; name: string }[];
};

/**
 * El segundo tiempo del documento: la recepción.
 *
 * No es una ficha de consulta. Aquí el almacén destino captura qué bajó del
 * camión, y si llegó de menos, quién responde por la diferencia.
 */
export default function TransferenciaShow({ transferencia, detalles, usuarios }: Props) {
    const [cancelando, setCancelando] = useState(false);

    const recepcion = useForm({
        recibido: Object.fromEntries(detalles.map((d) => [d.id, String(d.cantidad_enviada)])) as Record<string, string>,
        faltante_responsable_id: '',
    });

    const cancelacion = useForm({ motivo: '' });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Transferencias', href: '/admin/almacen/transferencias' },
        { title: transferencia.folio ?? String(transferencia.id), href: `/admin/almacen/transferencias/${transferencia.id}` },
    ];

    // El faltante se calcula mientras se captura, para que el responsable
    // aparezca justo cuando hace falta y no antes.
    const faltantePendiente = detalles.reduce(
        (suma, d) => suma + Math.max(0, d.cantidad_enviada - Number(recepcion.data.recibido[d.id] || 0)),
        0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transferencia ${transferencia.folio ?? transferencia.id}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1
                                className={`font-mono text-2xl font-semibold ${transferencia.cancelada ? 'line-through' : ''}`}
                            >
                                {transferencia.folio}
                            </h1>
                            {transferencia.cancelada ? (
                                <span className="badge badge-ghost">Cancelada</span>
                            ) : (
                                <span
                                    className={`badge badge-sm ${
                                        transferencia.estatus === 'recibida' ? 'badge-success' : 'badge-warning'
                                    }`}
                                >
                                    {transferencia.estatus_etiqueta}
                                </span>
                            )}
                        </div>
                        <p className="text-base-content/60 mt-1 inline-flex items-center gap-1 text-sm">
                            <span className="font-mono">{transferencia.origen}</span> {transferencia.origen_nombre}
                            <ArrowRightIcon className="size-3" />
                            <span className="font-mono">{transferencia.destino}</span> {transferencia.destino_nombre}
                        </p>
                        <p className="text-base-content/60 text-sm">
                            Salió el {transferencia.fecha_envio} con {transferencia.envio}
                            {transferencia.fecha_recepcion &&
                                ` · llegó el ${transferencia.fecha_recepcion}, recibió ${transferencia.recibio}`}
                        </p>
                        {transferencia.pedido_folio && (
                            <p className="text-sm">
                                Surte{' '}
                                <Link
                                    href={`/admin/almacen/pedidos/${transferencia.pedido_id}`}
                                    className="link link-hover font-mono"
                                >
                                    {transferencia.pedido_folio}
                                </Link>
                            </p>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/transferencias" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        {transferencia.estatus === 'en_transito' && !transferencia.cancelada && (
                            <button type="button" className="btn btn-outline" onClick={() => setCancelando(true)}>
                                Cancelar envío
                            </button>
                        )}
                    </div>
                </div>

                {transferencia.cancelada ? (
                    <div className="alert alert-warning mb-4">
                        <span>Cancelada: {transferencia.motivo_cancelacion}. El material volvió al origen.</span>
                    </div>
                ) : transferencia.estatus === 'en_transito' ? (
                    <div className="alert alert-warning mb-4">
                        <TruckIcon className="size-4" />
                        <span>
                            Este material ya salió de {transferencia.origen} y todavía no es existencia de{' '}
                            {transferencia.destino}: va en el camino. El saldo total no cuadra hasta que se confirme.
                        </span>
                    </div>
                ) : transferencia.resumen.faltante > 0 ? (
                    <div className="alert alert-error mb-4">
                        <TriangleAlertIcon className="size-4" />
                        <span>
                            Llegó menos de lo que salió: faltaron {numero(transferencia.resumen.faltante)} en{' '}
                            {transferencia.resumen.renglones_con_faltante} renglón(es). Responde{' '}
                            {transferencia.faltante_responsable}.
                        </span>
                    </div>
                ) : (
                    <div className="alert alert-success mb-4">
                        <span>Llegó completo lo que salió.</span>
                    </div>
                )}

                {transferencia.observaciones && (
                    <div className="rounded-box border-base-300 mb-4 border p-4">
                        <h2 className="mb-1 font-medium">Observaciones del envío</h2>
                        <p className="text-base-content/80 text-sm">{transferencia.observaciones}</p>
                    </div>
                )}

                {transferencia.puede_recibir ? (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            recepcion.patch(`/admin/almacen/transferencias/${transferencia.id}/recibir`, {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="rounded-box border-base-300 border">
                            <div className="border-base-300 border-b px-4 py-3">
                                <h2 className="font-medium">Confirmar lo que llegó</h2>
                                <p className="text-base-content/60 text-sm">
                                    Captura lo que de verdad bajó del camión. No se puede confirmar más de lo que salió:
                                    el sobrante se corrige con un ajuste en este almacén.
                                </p>
                            </div>
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Artículo</th>
                                        <th>Unidad</th>
                                        <th className="text-right">Salió</th>
                                        <th className="w-40 text-right">Llegó</th>
                                        <th className="text-right">Falta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalles.map((d) => {
                                        const recibido = Number(recepcion.data.recibido[d.id] || 0);
                                        const falta = Math.max(0, d.cantidad_enviada - recibido);

                                        return (
                                            <tr key={d.id} className={falta > 0 ? 'bg-error/10' : 'hover'}>
                                                <td>
                                                    <span className="font-mono text-xs">{d.codigo}</span>
                                                    <span className="block">{d.descripcion}</span>
                                                </td>
                                                <td className="text-base-content/60 font-mono text-xs">{d.unidad}</td>
                                                <td className="text-right font-mono">{numero(d.cantidad_enviada)}</td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={d.cantidad_enviada}
                                                        step="0.001"
                                                        className="input-sm text-right"
                                                        value={recepcion.data.recibido[d.id] ?? ''}
                                                        onChange={(e) =>
                                                            recepcion.setData('recibido', {
                                                                ...recepcion.data.recibido,
                                                                [d.id]: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="text-right font-mono">
                                                    {falta > 0 ? (
                                                        <span className="text-error font-semibold">
                                                            {numero(falta)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-base-content/40">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            <div className="border-base-300 space-y-3 border-t p-4">
                                {recepcion.errors.recibido && (
                                    <p className="text-error text-sm">{recepcion.errors.recibido}</p>
                                )}

                                {faltantePendiente > 0 && (
                                    <div className="max-w-md">
                                        <label className="label label-text">
                                            ¿Quién responde por los {numero(faltantePendiente)} que faltan?
                                        </label>
                                        <Select
                                            value={recepcion.data.faltante_responsable_id}
                                            onValueChange={(v) => recepcion.setData('faltante_responsable_id', v)}
                                            placeholder="Elige a quién se le carga"
                                        >
                                            {usuarios.map((u) => (
                                                <SelectItem key={u.id} value={u.id}>
                                                    {u.name}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                        {recepcion.errors.faltante_responsable_id && (
                                            <p className="text-error text-sm">
                                                {recepcion.errors.faltante_responsable_id}
                                            </p>
                                        )}
                                        <p className="text-base-content/60 mt-1 text-sm">
                                            El faltante ya salió del origen. Para eso son los dos tiempos: para que la
                                            diferencia tenga dueño y fecha en vez de ser un descuadre anónimo.
                                        </p>
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <button type="submit" className="btn btn-primary" disabled={recepcion.processing}>
                                        Confirmar recepción
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                ) : (
                    <div className="rounded-box border-base-300 border">
                        <div className="border-base-300 border-b px-4 py-3">
                            <h2 className="font-medium">Qué se mandó</h2>
                        </div>
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th>Artículo</th>
                                    <th>Unidad</th>
                                    <th className="text-right">Salió</th>
                                    <th className="text-right">Llegó</th>
                                    <th className="text-right">Faltó</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((d) => (
                                    <tr key={d.id} className={d.faltante > 0 ? 'bg-error/10' : 'hover'}>
                                        <td>
                                            <span className="font-mono text-xs">{d.codigo}</span>
                                            <span className="block">{d.descripcion}</span>
                                        </td>
                                        <td className="text-base-content/60 font-mono text-xs">{d.unidad}</td>
                                        <td className="text-right font-mono">{numero(d.cantidad_enviada)}</td>
                                        <td className="text-right font-mono">
                                            {d.cantidad_recibida === null ? (
                                                <span className="text-warning text-xs">Va en camino</span>
                                            ) : (
                                                numero(d.cantidad_recibida)
                                            )}
                                        </td>
                                        <td className="text-right font-mono">
                                            {d.faltante > 0 ? (
                                                <span className="text-error font-semibold">{numero(d.faltante)}</span>
                                            ) : (
                                                <span className="text-base-content/40">—</span>
                                            )}
                                        </td>
                                        <td className="text-base-content/60 text-sm">{d.observaciones}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {cancelando && (
                <dialog className="modal modal-open">
                    <div className="modal-box">
                        <h3 className="text-lg font-semibold">Cancelar {transferencia.folio}</h3>
                        <p className="text-base-content/60 mb-4 text-sm">
                            El material vuelve al origen con un movimiento espejo. Sólo se puede mientras va en el
                            camino: una vez que el destino confirmó, devolverlo es otra transferencia.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                cancelacion.patch(`/admin/almacen/transferencias/${transferencia.id}/cancelar`, {
                                    preserveScroll: true,
                                    onSuccess: () => setCancelando(false),
                                });
                            }}
                        >
                            <label className="form-control">
                                <span className="label label-text">¿Por qué se cancela?</span>
                                <Input
                                    value={cancelacion.data.motivo}
                                    onChange={(e) => cancelacion.setData('motivo', e.target.value)}
                                    placeholder="No salió el camión, se capturó mal el destino..."
                                    autoFocus
                                />
                                {cancelacion.errors.motivo && (
                                    <span className="text-error text-xs">{cancelacion.errors.motivo}</span>
                                )}
                            </label>

                            <div className="modal-action">
                                <button type="button" className="btn btn-ghost" onClick={() => setCancelando(false)}>
                                    Volver
                                </button>
                                <button type="submit" className="btn btn-error" disabled={cancelacion.processing}>
                                    Cancelar envío
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
