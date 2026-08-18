import { BotonPdf } from '@/components/alm/boton-pdf';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ESTATUS_TRANSFERENCIA, TRANSFERENCIAS_DEMO, resumenTransferencia } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRightIcon, PlusIcon, TriangleAlertIcon, TruckIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

export default function TransferenciasIndex() {
    const enTransito = TRANSFERENCIAS_DEMO.filter((t) => t.estatus === 'en_transito');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transferencias" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Transferencias</h1>
                        <p className="text-base-content/60 mt-1 max-w-3xl text-sm">
                            Material que cambia de almacén. Un solo folio en dos tiempos: el origen registra el envío y
                            el material queda <strong>en tránsito</strong>; el destino confirma lo que de verdad llegó.
                            Hasta esa segunda firma no es existencia de nadie.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/transferencias/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva transferencia
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                {enTransito.length > 0 && (
                    <div className="alert alert-info mb-4">
                        <TruckIcon className="size-5" />
                        <span>
                            <strong>{enTransito.length}</strong>{' '}
                            {enTransito.length === 1 ? 'transferencia va' : 'transferencias van'} en el camino sin
                            confirmar. Ese saldo no está disponible en ningún almacén.
                        </span>
                    </div>
                )}

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Envío</th>
                                <th>Recepción</th>
                                <th>Movimiento</th>
                                <th className="text-right">Renglones</th>
                                <th>Estatus</th>
                                <th>Autorizó</th>
                                <th className="w-32"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {TRANSFERENCIAS_DEMO.map((t) => {
                                const resumen = resumenTransferencia(t);
                                const estatus = ESTATUS_TRANSFERENCIA[t.estatus];

                                return (
                                    <tr key={t.id} className="hover">
                                        <td>
                                            <Link
                                                href={`/admin/almacen/transferencias/${t.id}`}
                                                className="link link-hover font-mono font-medium"
                                            >
                                                {t.folio}
                                            </Link>
                                            {t.pedido_folio && (
                                                <p className="text-base-content/50 mt-0.5 font-mono text-xs">
                                                    surte {t.pedido_folio}
                                                </p>
                                            )}
                                        </td>
                                        <td className="font-mono text-sm">{t.fecha_envio}</td>
                                        <td className="font-mono text-sm">
                                            {t.fecha_recepcion ?? <span className="text-base-content/30">—</span>}
                                        </td>
                                        <td>
                                            <span className="flex items-center gap-2">
                                                <span className="badge badge-sm badge-ghost font-mono">{t.origen}</span>
                                                <ArrowRightIcon className="text-base-content/40 size-4" />
                                                <span className="badge badge-sm badge-info font-mono">{t.destino}</span>
                                            </span>
                                        </td>
                                        <td className="text-right font-mono">{t.renglones.length}</td>
                                        <td>
                                            <span className={`badge badge-sm ${estatus.clase}`}>
                                                {estatus.etiqueta}
                                            </span>
                                            {resumen.faltante > 0 && (
                                                <p className="text-error mt-0.5 text-xs">
                                                    <TriangleAlertIcon className="mr-1 inline size-3" />
                                                    faltan {numero(resumen.faltante)}
                                                </p>
                                            )}
                                        </td>
                                        <td className="text-sm">{t.autorizo}</td>
                                        <td>
                                            <div className="flex items-center gap-1">
                                                {t.estatus === 'en_transito' && (
                                                    <ButtonLink
                                                        href={`/admin/almacen/transferencias/${t.id}`}
                                                        variant="outline"
                                                        className="btn-xs"
                                                    >
                                                        Recibir
                                                    </ButtonLink>
                                                )}
                                                <BotonPdf folio={t.folio} etiqueta="PDF" />
                                            </div>
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
