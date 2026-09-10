import { Head, router, usePage } from '@inertiajs/react';
import { PowerIcon, PowerOffIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea, AlmArticulo, AlmOpcion, AlmOpcionClase } from '@/types/models';
import { ArticuloForm } from './articulo-form';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Props = {
    articulo: AlmArticulo;
    areas: AlmArea[];
    unidades: string[];
    tipos: AlmOpcion[];
    clases: AlmOpcionClase[];
    /** Folios de las órdenes de compra sin cerrar (ni pagadas ni canceladas) que lo llevan. */
    ordenes_abiertas: string[];
};

export default function ArticuloEdit({ articulo, areas, unidades, tipos, clases, ordenes_abiertas }: Props) {
    const { flash, errors } = usePage<{ flash: { success?: string }; errors: { activo?: string } }>().props;
    const { can } = useCan();
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);

    // La misma regla que DesactivadorArticulo: con saldo en cualquier almacén o
    // en una orden sin cerrar no se apaga. Aquí sólo evita el viaje; la que
    // manda es la del servidor.
    const conStock = articulo.existencia_total !== 0;
    const conOrdenes = ordenes_abiertas.length > 0;
    const bloqueado = articulo.activo && (conStock || conOrdenes);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Artículos', href: '/admin/almacen/articulos' },
        { title: articulo.codigo, href: `/admin/almacen/articulos/${articulo.id}` },
        { title: 'Editar', href: `/admin/almacen/articulos/${articulo.id}/edit` },
    ];

    const alternar = () => {
        setProcesando(true);
        router.patch(
            `/admin/almacen/articulos/${articulo.id}/toggle`,
            {},
            {
                preserveScroll: true,
                // Se cierra también si el servidor se niega (piezas prestadas):
                // el motivo sale en la alerta de la página, no dentro del modal.
                onFinish: () => {
                    setProcesando(false);
                    setConfirmando(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${articulo.codigo}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">{articulo.descripcion}</h1>
                            {!articulo.activo && (
                                <span className="badge badge-sm badge-error" title="Sigue en el kardex, pero ya no se compra, se cuenta ni se presta">
                                    Inactivo
                                </span>
                            )}
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Lo que se corrige aquí cambia cómo se comporta el artículo en todos los movimientos y en
                            Compras: es el mismo catálogo.
                        </p>
                    </div>

                    {/* Va aparte del formulario: no espera a «Guardar», se aplica al
                        confirmar y no toca lo que esté a medio capturar abajo. */}
                    {can('alm.articulos.desactivar') && (
                        <Dialog open={confirmando} onOpenChange={setConfirmando}>
                            <DialogTrigger className={`btn ${articulo.activo ? 'btn-error btn-outline' : 'btn-success'}`}>
                                {articulo.activo ? <PowerOffIcon className="size-4" /> : <PowerIcon className="size-4" />}
                                {articulo.activo ? 'Desactivar' : 'Reactivar'}
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {bloqueado
                                            ? `No se puede desactivar ${articulo.codigo}`
                                            : `${articulo.activo ? 'Desactivar' : 'Reactivar'} ${articulo.codigo}`}
                                    </DialogTitle>
                                    {bloqueado ? (
                                        <div className="space-y-2 py-4">
                                            {conStock && (
                                                <p>
                                                    Todavía hay{' '}
                                                    <strong className="font-mono">
                                                        {numero(articulo.existencia_total)} {articulo.unidad}
                                                    </strong>{' '}
                                                    en existencia: primero ajusta o retira el saldo en cada almacén.
                                                </p>
                                            )}
                                            {conOrdenes && (
                                                <p>
                                                    Está en órdenes de compra sin cerrar:{' '}
                                                    <strong className="font-mono">{ordenes_abiertas.join(', ')}</strong>.
                                                    Espera a que se paguen o se cancelen.
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <DialogDescription>
                                            {articulo.activo
                                                ? 'Deja de aparecer en compras, conteos y préstamos, aquí y en Compras. Su historial se conserva y se puede reactivar cuando haga falta.'
                                                : 'Vuelve a aparecer en compras, conteos y préstamos, aquí y en Compras.'}
                                        </DialogDescription>
                                    )}
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose className="btn btn-ghost">{bloqueado ? 'Entendido' : 'Cancelar'}</DialogClose>
                                    {!bloqueado && (
                                        <Button
                                            variant={articulo.activo ? 'error' : 'primary'}
                                            onClick={alternar}
                                            loading={procesando}
                                        >
                                            {articulo.activo ? 'Desactivar' : 'Reactivar'}
                                        </Button>
                                    )}
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {flash?.success && (
                    <div className="alert alert-success mb-4">
                        <span>{flash.success}</span>
                    </div>
                )}
                {errors?.activo && (
                    <div className="alert alert-error mb-4">
                        <span>{errors.activo}</span>
                    </div>
                )}

                {conStock && (
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
