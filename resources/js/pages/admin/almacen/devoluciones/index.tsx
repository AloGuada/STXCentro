import { BotonPdf } from '@/components/alm/boton-pdf';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { DEVOLUCIONES_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Insumos', href: '/admin/almacen/existencias' },
    { title: 'Devoluciones', href: '/admin/almacen/devoluciones' },
];

export default function DevolucionesIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Devoluciones" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Devoluciones</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Material que regresa de la obra al almacén. Entra igual que una entrada, pero se registra
                            aparte para saber cuánto de lo que salió no se usó.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/devoluciones/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nueva devolución
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="alert alert-info mb-4">
                    <span>
                        Esto es material que <strong>vuelve de obra</strong>. La devolución a proveedor es otra cosa y
                        vive en Costos, dentro de la orden de compra.
                    </span>
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Almacén que recibe</th>
                                <th>Obra de origen</th>
                                <th>Devolvió</th>
                                <th className="text-right">Renglones</th>
                                <th>Motivo</th>
                                <th className="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {DEVOLUCIONES_DEMO.map((d) => (
                                <tr key={d.id} className="hover">
                                    <td className="font-mono font-medium">{d.folio}</td>
                                    <td className="font-mono text-sm">{d.fecha}</td>
                                    <td>
                                        <span className="badge badge-sm badge-ghost font-mono">{d.almacen}</span>
                                    </td>
                                    <td>{d.obra_origen}</td>
                                    <td className="text-sm">{d.devolvio}</td>
                                    <td className="text-right font-mono">{d.renglones}</td>
                                    <td className="text-base-content/70 text-sm">{d.motivo}</td>
                                    <td>
                                        <BotonPdf folio={d.folio} etiqueta="PDF" />
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
