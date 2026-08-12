import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AlmArea } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckIcon, PencilIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/almacenes' },
    { title: 'Áreas', href: '/admin/almacen/areas' },
];

type Props = {
    areas: AlmArea[];
};

export default function AreasIndex({ areas }: Props) {
    const { can } = useCan();
    const puedeCrear = can('alm.areas.crear');
    const puedeEditar = can('alm.areas.editar');

    const alta = useForm({ descripcion: '' });

    // Se edita en la misma fila: son listas de un campo, y mandar a otra
    // pantalla para cambiar una palabra es más viaje que trabajo.
    const [editando, setEditando] = useState<number | null>(null);
    const edicion = useForm({ descripcion: '' });

    const abrirEdicion = (area: AlmArea) => {
        setEditando(area.id);
        edicion.setData('descripcion', area.descripcion);
    };

    const guardarEdicion = (area: AlmArea) =>
        edicion.put(`/admin/almacen/areas/${area.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditando(null),
        });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Áreas de almacén" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Áreas</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Clasifican el artículo por la parte de la operación a la que pertenece. No es dónde está
                        guardado —eso es la ubicación, que cuelga de un almacén—: el área viaja con el artículo.
                    </p>
                </div>

                {puedeCrear && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            alta.post('/admin/almacen/areas', {
                                preserveScroll: true,
                                onSuccess: () => alta.reset('descripcion'),
                            });
                        }}
                        className="rounded-box border-base-300 mb-6 border p-4"
                    >
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <div className="flex-1">
                                <Input
                                    id="descripcion"
                                    value={alta.data.descripcion}
                                    onChange={(e) => alta.setData('descripcion', e.target.value)}
                                    maxLength={255}
                                    placeholder="Pintura"
                                    aria-label="Descripción del área"
                                />
                                {alta.errors.descripcion && (
                                    <p className="text-error mt-1 text-sm">{alta.errors.descripcion}</p>
                                )}
                            </div>
                            <Button type="submit" disabled={alta.processing || alta.data.descripcion.trim() === ''}>
                                Agregar área
                            </Button>
                        </div>
                    </form>
                )}

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Descripción</th>
                                <th className="w-32 text-center">Estado</th>
                                <th className="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {areas.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="text-base-content/60 text-center">
                                        Todavía no hay áreas dadas de alta.
                                    </td>
                                </tr>
                            )}

                            {areas.map((area) => (
                                <tr key={area.id} className={area.activo ? '' : 'opacity-60'}>
                                    <td>
                                        {editando === area.id ? (
                                            <div>
                                                <Input
                                                    value={edicion.data.descripcion}
                                                    onChange={(e) => edicion.setData('descripcion', e.target.value)}
                                                    maxLength={255}
                                                    aria-label="Nueva descripción"
                                                    autoFocus
                                                />
                                                {edicion.errors.descripcion && (
                                                    <p className="text-error mt-1 text-sm">
                                                        {edicion.errors.descripcion}
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="font-medium">{area.descripcion}</span>
                                        )}
                                    </td>
                                    <td className="text-center">
                                        <span className={`badge badge-sm ${area.activo ? 'badge-success' : 'badge-ghost'}`}>
                                            {area.activo ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </td>
                                    <td>
                                        {puedeEditar && (
                                            <div className="flex justify-end gap-1">
                                                {editando === area.id ? (
                                                    <>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            onClick={() => guardarEdicion(area)}
                                                            disabled={edicion.processing}
                                                            aria-label="Guardar"
                                                        >
                                                            <CheckIcon className="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            onClick={() => {
                                                                setEditando(null);
                                                                edicion.clearErrors();
                                                            }}
                                                            aria-label="Cancelar"
                                                        >
                                                            <XIcon className="size-4" />
                                                        </button>
                                                    </>
                                                ) : (
                                                    <>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            onClick={() => abrirEdicion(area)}
                                                            aria-label={`Editar ${area.descripcion}`}
                                                        >
                                                            <PencilIcon className="size-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            onClick={() =>
                                                                router.patch(
                                                                    `/admin/almacen/areas/${area.id}/toggle`,
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {area.activo ? 'Desactivar' : 'Reactivar'}
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Un área no se borra, se desactiva: dejaría artículos apuntando a algo que ya no existe. Desactivada
                    deja de ofrecerse al dar de alta un artículo, pero los que ya la tienen la conservan.
                </p>
            </div>
        </AppLayout>
    );
}
