import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ENTRADAS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Entradas', href: '/admin/almacen/entradas' },
];

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

export default function EntradasIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Entradas" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Entradas</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Material que llega al almacén. Cada entrada es definitiva: si algo se capturó mal se corrige
                            con un ajuste, no editando el documento.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/entradas/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva entrada
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
                                <th>Proveedor</th>
                                <th className="text-right">Renglones</th>
                                <th className="text-right">Importe</th>
                                <th>Recibió</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ENTRADAS_DEMO.map((e) => (
                                <tr key={e.id} className="hover">
                                    <td className="font-mono font-medium">{e.folio}</td>
                                    <td className="font-mono text-sm">{e.fecha}</td>
                                    <td>
                                        <span className="badge badge-sm badge-ghost font-mono">{e.almacen}</span>
                                    </td>
                                    <td>{e.proveedor ?? <span className="text-base-content/40">Sin proveedor</span>}</td>
                                    <td className="text-right font-mono">{e.renglones}</td>
                                    <td className="text-right font-mono">{moneda(e.importe)}</td>
                                    <td className="text-sm">{e.recibio}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
