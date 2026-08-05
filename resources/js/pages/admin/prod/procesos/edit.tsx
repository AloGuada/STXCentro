import { DeleteDialog } from '@/components/delete-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdProceso } from '@/types/models';
import { Head } from '@inertiajs/react';
import { ProcesoForm } from './proceso-form';

type Props = {
    proceso: ProdProceso;
};

export default function ProcesosEdit({ proceso }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Procesos', href: '/admin/prod/procesos' },
        { title: proceso.nombre, href: `/admin/prod/procesos/${proceso.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${proceso.nombre}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl space-y-8">
                    <div>
                        <h1 className="mb-6 text-2xl font-semibold">Editar proceso</h1>
                        <ProcesoForm proceso={proceso} />
                    </div>

                    <div className="border-base-300 border-t pt-6">
                        <DeleteDialog
                            deleteUrl={`/admin/prod/procesos/${proceso.id}`}
                            title="Eliminar proceso"
                            description={`¿Eliminar el proceso "${proceso.nombre}"? Si ya tiene producción capturada, desactívalo en vez de borrarlo.`}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
