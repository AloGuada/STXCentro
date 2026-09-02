import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmArticulo, AlmOpcion, AlmOpcionClase } from '@/types/models';
import { Head } from '@inertiajs/react';
import { ArticuloForm } from './articulo-form';

type Props = {
    articulo: AlmArticulo;
    areas: AlmArea[];
    unidades: string[];
    tipos: AlmOpcion[];
    clases: AlmOpcionClase[];
};

export default function ArticuloEdit({ articulo, areas, unidades, tipos, clases }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Artículos', href: '/admin/almacen/articulos' },
        { title: articulo.codigo, href: `/admin/almacen/articulos/${articulo.id}` },
        { title: 'Editar', href: `/admin/almacen/articulos/${articulo.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${articulo.codigo}`} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{articulo.descripcion}</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Lo que se corrige aquí cambia cómo se comporta el artículo en todos los movimientos y en
                        Compras: es el mismo catálogo.
                    </p>
                </div>

                {articulo.existencia_total !== 0 && (
                    <div className="alert alert-warning mb-4">
                        <span>
                            Este artículo ya tiene existencia. Quitarle el kardex o cambiarlo a control por pieza deja
                            un saldo que ya nadie mantiene: primero vacíalo con un ajuste.
                        </span>
                    </div>
                )}

                <ArticuloForm
                    articulo={articulo}
                    areas={areas}
                    unidades={unidades}
                    tipos={tipos}
                    clases={clases}
                />
            </div>
        </AppLayout>
    );
}
