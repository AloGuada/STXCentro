import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RegimenFiscal } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Regímenes Fiscales', href: '/admin/regimenes-fiscales' },
];

type Props = {
    regimenes: RegimenFiscal[];
};

export default function RegimenesFiscalesIndex({ regimenes }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Regímenes Fiscales" />

            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Regímenes Fiscales</h1>
                    <Button asChild>
                        <Link href="/admin/regimenes-fiscales/create">Nuevo Régimen</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Clave</th>
                                <th>Descripción</th>
                                <th>Persona</th>
                                <th>Activo</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {regimenes.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center text-base-content/60">No hay regímenes registrados.</td>
                                </tr>
                            ) : (
                                regimenes.map((r) => (
                                    <tr key={r.id}>
                                        <td className="font-mono">{r.clave}</td>
                                        <td>{r.descripcion}</td>
                                        <td className="text-sm">
                                            {[r.aplica_persona_fisica && 'Física', r.aplica_persona_moral && 'Moral'].filter(Boolean).join(' / ') || '-'}
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${r.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                {r.activo ? 'Sí' : 'No'}
                                            </span>
                                        </td>
                                        <td className="text-right">
                                            <Link className="link link-primary" href={`/admin/regimenes-fiscales/${r.id}/edit`}>Editar</Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
