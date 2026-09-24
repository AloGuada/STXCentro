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
import type { AlmAlmacen, Obra, Usuario } from '@/types/models';
import { AlmacenForm, type OpcionTipo } from './almacen-form';

/** Lo que impide apagarlo. La misma regla que DesactivadorAlmacen en el servidor. */
type Bloqueos = {
    articulos_con_saldo: number;
    prestamos_abiertos: string[];
    transferencias_en_transito: string[];
};

type Props = {
    almacen: AlmAlmacen;
    obras: Pick<Obra, 'id' | 'no' | 'descripcion'>[];
    usuarios: Pick<Usuario, 'id' | 'name'>[];
    tipos: OpcionTipo[];
    bloqueos: Bloqueos;
};

export default function AlmacenEdit({ almacen, obras, usuarios, tipos, bloqueos }: Props) {
    const { flash, errors } = usePage<{ flash: { success?: string }; errors: { activo?: string } }>().props;
    const { can } = useCan();
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);

    // Aquí sólo evita el viaje; la regla que manda es la del servidor.
    const conSaldo = bloqueos.articulos_con_saldo > 0;
    const conPrestamos = bloqueos.prestamos_abiertos.length > 0;
    const conTransito = bloqueos.transferencias_en_transito.length > 0;
    const bloqueado = almacen.activo && (conSaldo || conPrestamos || conTransito);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/almacenes' },
        { title: 'Almacenes', href: '/admin/almacen/almacenes' },
        { title: almacen.clave, href: `/admin/almacen/almacenes/${almacen.id}/edit` },
    ];

    const alternar = () => {
        setProcesando(true);
        router.patch(
            `/admin/almacen/almacenes/${almacen.id}/toggle`,
            {},
            {
                preserveScroll: true,
                // Se cierra también si el servidor se niega: el motivo sale en
                // la alerta de la página, no dentro del modal.
                onFinish: () => {
                    setProcesando(false);
                    setConfirmando(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Almacén ${almacen.clave}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <div className="mb-6 flex items-start justify-between gap-3">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold">
                                    {almacen.clave} — {almacen.nombre}
                                </h1>
                                {!almacen.activo && (
                                    <span className="badge badge-sm badge-error" title="Su kardex se conserva, pero ya no aparece para operar">
                                        Inactivo
                                    </span>
                                )}
                            </div>
                            <p className="text-base-content/60 mt-1 text-sm">
                                {almacen.obra
                                    ? `Obra ${almacen.obra.no} — ${almacen.obra.descripcion}`
                                    : 'Almacén central: surte a todas las obras'}
                            </p>
                        </div>

                        {/* Va aparte del formulario: no espera a «Guardar», se aplica
                            al confirmar y no toca lo que esté a medio capturar. */}
                        {can('alm.almacenes.editar') && (
                            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                                <DialogTrigger className={`btn ${almacen.activo ? 'btn-error btn-outline' : 'btn-success'}`}>
                                    {almacen.activo ? <PowerOffIcon className="size-4" /> : <PowerIcon className="size-4" />}
                                    {almacen.activo ? 'Desactivar' : 'Reactivar'}
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            {bloqueado
                                                ? `No se puede desactivar ${almacen.clave}`
                                                : `${almacen.activo ? 'Desactivar' : 'Reactivar'} ${almacen.clave} — ${almacen.nombre}`}
                                        </DialogTitle>
                                        {bloqueado ? (
                                            <div className="space-y-2 py-4">
                                                {conSaldo && (
                                                    <p>
                                                        Todavía hay{' '}
                                                        <strong className="font-mono">{bloqueos.articulos_con_saldo}</strong>{' '}
                                                        artículo(s) con saldo: transfiérelos o ajústalos primero.
                                                    </p>
                                                )}
                                                {conPrestamos && (
                                                    <p>
                                                        Hay resguardos abiertos:{' '}
                                                        <strong className="font-mono">{bloqueos.prestamos_abiertos.join(', ')}</strong>.
                                                        Recibe lo prestado primero.
                                                    </p>
                                                )}
                                                {conTransito && (
                                                    <p>
                                                        Hay transferencias en tránsito:{' '}
                                                        <strong className="font-mono">
                                                            {bloqueos.transferencias_en_transito.join(', ')}
                                                        </strong>
                                                        . Confírmalas primero.
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <DialogDescription>
                                                {almacen.activo
                                                    ? 'Deja de aparecer para operar y en el reporte de existencias. Su kardex se conserva y se puede reactivar cuando haga falta.'
                                                    : 'Vuelve a aparecer para operar y en el reporte de existencias.'}
                                            </DialogDescription>
                                        )}
                                    </DialogHeader>
                                    <DialogFooter>
                                        <DialogClose className="btn btn-ghost">{bloqueado ? 'Entendido' : 'Cancelar'}</DialogClose>
                                        {!bloqueado && (
                                            <Button
                                                variant={almacen.activo ? 'error' : 'primary'}
                                                onClick={alternar}
                                                loading={procesando}
                                            >
                                                {almacen.activo ? 'Desactivar' : 'Reactivar'}
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

                    <AlmacenForm almacen={almacen} obras={obras} usuarios={usuarios} tipos={tipos} />
                </div>
            </div>
        </AppLayout>
    );
}
