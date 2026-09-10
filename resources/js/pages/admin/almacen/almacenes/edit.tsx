import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacen, Obra, Usuario } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { Trash2Icon } from 'lucide-react';
import { AlmacenForm, type OpcionTipo } from './almacen-form';

type Props = {
    almacen: AlmAlmacen;
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    usuarios: Pick<Usuario, 'id' | 'name'>[];
    tipos: OpcionTipo[];
};

export default function AlmacenEdit({ almacen, obras, usuarios, tipos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/almacenes' },
        { title: 'Almacenes', href: '/admin/almacen/almacenes' },
        { title: almacen.clave, href: `/admin/almacen/almacenes/${almacen.id}/edit` },
    ];

    const eliminar = () => {
        if (confirm(`¿Eliminar el almacén ${almacen.clave} — ${almacen.nombre}?`)) {
            router.delete(`/admin/almacen/almacenes/${almacen.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Almacén ${almacen.clave}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <div className="mb-6 flex items-start justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {almacen.clave} — {almacen.nombre}
                            </h1>
                            <p className="text-base-content/60 mt-1 text-sm">
                                {almacen.obra
                                    ? `Obra ${almacen.obra.no} — ${almacen.obra.descripcion}`
                                    : 'Almacén central: surte a todas las obras'}
                            </p>
                        </div>
                        <Button variant="outline" onClick={eliminar}>
                            <Trash2Icon className="size-4" />
                            Eliminar
                        </Button>
                    </div>

                    <AlmacenForm almacen={almacen} obras={obras} usuarios={usuarios} tipos={tipos} />
                </div>
            </div>
        </AppLayout>
    );
}
