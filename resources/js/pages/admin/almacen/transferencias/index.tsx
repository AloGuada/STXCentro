import { BotonPdf } from '@/components/alm/boton-pdf';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { TRANSFERENCIAS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowRightIcon, PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Transferencias', href: '/admin/almacen/transferencias' },
];

export default function TransferenciasIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transferencias" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Transferencias</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Material que cambia de almacén. Cada una deja dos movimientos —salida en el origen y entrada
                            en el destino— y el total de la empresa no se mueve.
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

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Movimiento</th>
                                <th className="text-right">Renglones</th>
                                <th>Autorizó</th>
                                <th className="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {TRANSFERENCIAS_DEMO.map((t) => (
                                <tr key={t.id} className="hover">
                                    <td className="font-mono font-medium">{t.folio}</td>
                                    <td className="font-mono text-sm">{t.fecha}</td>
                                    <td>
                                        <span className="flex items-center gap-2">
                                            <span className="badge badge-sm badge-ghost font-mono">{t.origen}</span>
                                            <ArrowRightIcon className="text-base-content/40 size-4" />
                                            <span className="badge badge-sm badge-info font-mono">{t.destino}</span>
                                        </span>
                                    </td>
                                    <td className="text-right font-mono">{t.renglones}</td>
                                    <td className="text-sm">{t.autorizo}</td>
                                    <td>
                                        <BotonPdf folio={t.folio} etiqueta="PDF" />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
