import { BotonPdf } from '@/components/alm/boton-pdf';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { comoSeSurte, ESTATUS_PEDIDO, PEDIDOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Pedidos', href: '/admin/almacen/pedidos' },
];

export default function PedidosIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pedidos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Pedidos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Lo que la obra le pide al almacén. Al aprobarse y surtirse genera la salida o la
                            transferencia: es lo que explica por qué se movió el material.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/pedidos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo pedido
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="alert alert-info mb-4">
                    <span>
                        Se llama <strong>pedido</strong> y no requisición para no confundirlo con la{' '}
                        <strong>requisición de compra</strong> de Costos, que le pide material a un proveedor. Éste le
                        pide a un almacén lo que ya está en existencia. Se puede pedir para una obra o para{' '}
                        <strong>consumo interno de planta</strong>; el destino decide si lo surte una transferencia o
                        una salida.
                    </span>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Solicitante</th>
                                <th>Área</th>
                                <th>Destino</th>
                                <th>Le pide a</th>
                                <th>Se surte con</th>
                                <th>Requerido para</th>
                                <th className="text-right">Renglones</th>
                                <th className="text-right">Por surtir</th>
                                <th>Estatus</th>
                                <th className="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {PEDIDOS_DEMO.map((p) => {
                                const estatus = ESTATUS_PEDIDO[p.estatus];
                                const surtido = comoSeSurte(p.obra);

                                return (
                                    <tr key={p.id} className="hover">
                                        <td className="font-mono font-medium">{p.folio}</td>
                                        <td className="font-mono text-sm">{p.fecha}</td>
                                        <td className="text-sm">{p.solicitante}</td>
                                        <td className="text-sm">{p.departamento}</td>
                                        <td>
                                            {p.obra ?? (
                                                <span className="badge badge-sm badge-ghost">Consumo interno</span>
                                            )}
                                        </td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{p.almacen}</span>
                                        </td>
                                        <td className="text-base-content/70 text-sm" title={surtido.explicacion}>
                                            {surtido.documento}
                                        </td>
                                        <td className="font-mono text-sm">{p.fecha_requerida}</td>
                                        <td className="text-right font-mono">{p.detalle.length}</td>
                                        <td className="text-right font-mono">
                                            {p.detalle.filter((d) => d.cantidad_surtida < d.cantidad_solicitada).length}
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${estatus.clase}`}>{estatus.etiqueta}</span>
                                        </td>
                                        <td>
                                            <BotonPdf folio={p.folio} etiqueta="PDF" />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
