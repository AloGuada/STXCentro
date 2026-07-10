import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Banco } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bancos', href: '/admin/bancos' },
];

type Props = {
    bancos: Banco[];
};

export default function BancosIndex({ bancos }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bancos" />

            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Bancos</h1>
                    <Button asChild>
                        <Link href="/admin/bancos/create">Nuevo Banco</Link>
                    </Button>
                </div>

                <p className="mb-4 text-sm text-base-content/60">
                    El <strong>banco pagador</strong> captura la cuenta por número (dígitos configurables); el resto por CLABE de 18 dígitos.
                </p>

                <div className="overflow-x-auto rounded-lg border border-base-300">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Banco</th>
                                <th>Dígitos de cuenta</th>
                                <th>Pagador</th>
                                <th>Activo</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {bancos.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center text-base-content/60">No hay bancos registrados.</td>
                                </tr>
                            ) : (
                                bancos.map((b) => (
                                    <tr key={b.id}>
                                        <td className="font-medium">{b.nombre}</td>
                                        <td>{b.es_pagador ? b.digitos_cuenta : '— (CLABE)'}</td>
                                        <td>
                                            {b.es_pagador && <span className="badge badge-sm badge-primary">Pagador</span>}
                                        </td>
                                        <td>
                                            <span className={`badge badge-sm ${b.activo ? 'badge-success' : 'badge-ghost'}`}>
                                                {b.activo ? 'Sí' : 'No'}
                                            </span>
                                        </td>
                                        <td className="text-right">
                                            <Link className="link link-primary" href={`/admin/bancos/${b.id}/edit`}>Editar</Link>
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
