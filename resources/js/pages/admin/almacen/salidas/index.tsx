import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { SALIDAS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Salidas', href: '/admin/almacen/salidas' },
];

export default function SalidasIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Salidas" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Salidas</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            El material deja el almacén: quién lo pidió, a qué obra va y quién lo recibió. De cada una
                            se imprime el vale que firma quien se lo lleva.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/salidas/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva salida
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
                                <th>Almacén</th>
                                <th>Obra destino</th>
                                <th>Solicitó</th>
                                <th>Recibió</th>
                                <th className="text-right">Renglones</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {SALIDAS_DEMO.map((s) => (
                                <tr key={s.id} className="hover">
                                    <td className="font-mono font-medium">{s.folio}</td>
                                    <td className="font-mono text-sm">{s.fecha}</td>
                                    <td>
                                        <span className="badge badge-sm badge-ghost font-mono">{s.almacen}</span>
                                    </td>
                                    <td>
                                        {s.obra_destino ?? <span className="text-base-content/40">Consumo interno</span>}
                                    </td>
                                    <td className="text-sm">{s.solicitante}</td>
                                    <td className="text-sm">{s.recibe}</td>
                                    <td className="text-right font-mono">{s.renglones}</td>
                                    <td className="text-base-content/70 text-sm">{s.motivo}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
