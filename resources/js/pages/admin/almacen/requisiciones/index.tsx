import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ESTATUS_REQUISICION, REQUISICIONES_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Requisiciones', href: '/admin/almacen/requisiciones' },
];

export default function RequisicionesIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Requisiciones" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Requisiciones</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Lo que la obra le pide al almacén. Al aprobarse y surtirse genera la salida: es lo que
                            explica por qué se movió el material.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/requisiciones/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva requisición
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="alert alert-info mb-4">
                    <span>
                        No confundir con la <strong>requisición de compra</strong> de Costos, que le pide material a un
                        proveedor. Ésta le pide a un almacén lo que ya está en existencia.
                    </span>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Solicitante</th>
                                <th>Obra</th>
                                <th>Le pide a</th>
                                <th>Requerido para</th>
                                <th className="text-right">Renglones</th>
                                <th className="text-right">Por surtir</th>
                                <th>Estatus</th>
                            </tr>
                        </thead>
                        <tbody>
                            {REQUISICIONES_DEMO.map((r) => {
                                const estatus = ESTATUS_REQUISICION[r.estatus];

                                return (
                                    <tr key={r.id} className="hover">
                                        <td className="font-mono font-medium">{r.folio}</td>
                                        <td className="font-mono text-sm">{r.fecha}</td>
                                        <td className="text-sm">{r.solicitante}</td>
                                        <td>{r.obra}</td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{r.almacen}</span>
                                        </td>
                                        <td className="font-mono text-sm">{r.fecha_requerida}</td>
                                        <td className="text-right font-mono">{r.detalle.length}</td>
                                        <td className="text-right font-mono">
                                            {r.detalle.filter((d) => d.cantidad_surtida < d.cantidad_solicitada).length}
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${estatus.clase}`}>{estatus.etiqueta}</span>
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
