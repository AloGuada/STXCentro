import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Usuario } from '@/types/models';
import { Head } from '@inertiajs/react';
import { AlmacenForm, type OpcionTipo } from './almacen-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/almacenes' },
    { title: 'Almacenes', href: '/admin/almacen/almacenes' },
    { title: 'Nuevo', href: '/admin/almacen/almacenes/create' },
];

type Props = {
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    usuarios: Pick<Usuario, 'id' | 'name'>[];
    tipos: OpcionTipo[];
};

export default function AlmacenCreate({ obras, usuarios, tipos }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo almacén" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Nuevo almacén</h1>
                    <p className="text-base-content/60 mb-6 mt-1 text-sm">
                        La clave sólo tiene que ser única dentro de su obra: dos obras pueden tener cada una su AG.
                    </p>

                    <AlmacenForm obras={obras} usuarios={usuarios} tipos={tipos} />
                </div>
            </div>
        </AppLayout>
    );
}
