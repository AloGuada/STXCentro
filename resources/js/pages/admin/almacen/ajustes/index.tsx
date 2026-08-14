import { ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { AJUSTES_DEMO, MOTIVOS_AJUSTE } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Ajustes', href: '/admin/almacen/ajustes' },
];

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

export default function AjustesIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajustes" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Ajustes</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            La única forma de corregir una existencia. Los documentos no se editan ni se borran: lo que
                            no cuadra se explica aquí, con motivo y con quién lo autorizó.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/ajustes/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo ajuste
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
                                <th>Motivo</th>
                                <th className="text-right">Renglones</th>
                                <th className="text-right">Diferencia neta</th>
                                <th>Autorizó</th>
                            </tr>
                        </thead>
                        <tbody>
                            {AJUSTES_DEMO.map((a) => (
                                <tr key={a.id} className="hover">
                                    <td className="font-mono font-medium">{a.folio}</td>
                                    <td className="font-mono text-sm">{a.fecha}</td>
                                    <td>
                                        <span className="badge badge-sm badge-ghost font-mono">{a.almacen}</span>
                                    </td>
                                    <td className="text-sm">{MOTIVOS_AJUSTE[a.motivo]}</td>
                                    <td className="text-right font-mono">{a.renglones}</td>
                                    <td className="text-right font-mono">
                                        <span
                                            className={
                                                a.diferencia_neta < 0
                                                    ? 'text-error font-semibold'
                                                    : 'text-success font-semibold'
                                            }
                                        >
                                            {a.diferencia_neta > 0 ? '+' : ''}
                                            {numero(a.diferencia_neta)}
                                        </span>
                                    </td>
                                    <td className="text-sm">{a.autorizo}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
