import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmOpcion, AlmOpcionClase } from '@/types/models';
import { Head } from '@inertiajs/react';
import { ArticuloForm } from './articulo-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Artículos', href: '/admin/almacen/articulos' },
    { title: 'Nuevo', href: '/admin/almacen/articulos/create' },
];

type Props = {
    /** El que le tocaría si se guardara ahora. Se muestra para que quien da de
     *  alta sepa con qué va a quedar etiquetado; el definitivo se aparta al
     *  guardar, dentro de la transacción. */
    codigoSugerido: string;
    areas: AlmArea[];
    unidades: string[];
    tipos: AlmOpcion[];
    clases: AlmOpcionClase[];
};

export default function ArticuloCreate({ codigoSugerido, areas, unidades, tipos, clases }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo artículo" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nuevo artículo</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Se da de alta en el catálogo que comparten Compras y Almacén: el mismo código sirve para
                        cotizar y para el kardex.
                    </p>
                </div>

                <ArticuloForm
                    codigoSugerido={codigoSugerido}
                    areas={areas}
                    unidades={unidades}
                    tipos={tipos}
                    clases={clases}
                />
            </div>
        </AppLayout>
    );
}
