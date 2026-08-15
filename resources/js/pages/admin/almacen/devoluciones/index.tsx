import { BotonPdf } from '@/components/alm/boton-pdf';
import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { DEVOLUCIONES_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
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
                            Piezas que vuelven al almacén y cierran su resguardo. No mueven existencia: la pieza
                            siempre fue del almacén, lo que cambia es que deja de estar en custodia de alguien.
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
                        Sólo vuelve por aquí lo que tiene <strong>número de serie</strong>. El material por cantidad
                        que sobró en una obra regresa con una <strong>transferencia</strong> al almacén general — así
                        se mueve el saldo entre almacenes. La devolución a proveedor es otra cosa y vive en Costos,
                        dentro de la orden de compra.
                    </span>
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Devolvió</th>
                                <th>Recibió</th>
                                <th>Almacén</th>
                                <th className="text-right">Piezas</th>
                                <th>Cómo volvieron</th>
                                <th className="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {DEVOLUCIONES_DEMO.map((d) => (
                                <tr key={d.id} className="hover">
                                    <td className="font-mono font-medium">{d.folio}</td>
                                    <td className="font-mono text-sm">{d.fecha}</td>
                                    <td className="text-sm">{d.devolvio}</td>
                                    <td className="text-sm">{d.recibio}</td>
                                    <td className="flex flex-wrap gap-1">
                                        {d.almacenes.map((clave) => (
                                            <span key={clave} className="badge badge-sm badge-ghost font-mono">
                                                {clave}
                                            </span>
                                        ))}
                                    </td>
                                    <td className="text-right font-mono">{d.piezas}</td>
                                    <td className="text-sm">
                                        {/*
                                         * Lo que importa de una devolución es si algo volvió
                                         * peor de como salió: eso es lo que se reclama.
                                         */}
                                        {d.con_dano === 0 ? (
                                            <span className="text-base-content/60">Sin novedad</span>
                                        ) : (
                                            <span className="badge badge-sm badge-warning">
                                                {d.con_dano} con daño
                                            </span>
                                        )}
                                    </td>
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
