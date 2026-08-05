import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ProcesoForm } from './proceso-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Procesos', href: '/admin/prod/procesos' },
    { title: 'Nuevo proceso', href: '/admin/prod/procesos/create' },
];

export default function ProcesosCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo proceso" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo proceso</h1>
                    <ProcesoForm />
                </div>
            </div>
        </AppLayout>
    );
}
