import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmAlmacenOpcion, AlmOpcion, AlmUbicacionFila, AlmUbicacionTipo } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { CornerDownRightIcon, PlusIcon, RotateCcwIcon, TrashIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Ubicaciones', href: '/admin/almacen/ubicaciones' },
];

type Props = {
    almacenes: AlmAlmacenOpcion[];
    almacenSeleccionado: number | null;
    /** Ya viene aplanado en el orden en que se recorre, con su profundidad. */
    ubicaciones: AlmUbicacionFila[];
    sinAcomodar: number;
    tipos: AlmOpcion[];
};

/**
 * El tercer nivel del inventario: obra → almacén → ubicación.
 *
 * Hasta ahora el lugar dentro del almacén era un texto libre que servía para
 * anotar pero no para filtrar. Con el catálogo se puede preguntar qué hay en un
 * rack, y el inventario cíclico tiene una ruta que recorrer en vez de una lista
 * suelta de artículos.
 */
export default function UbicacionesIndex({ almacenes, almacenSeleccionado, ubicaciones, sinAcomodar, tipos }: Props) {
    const [mostrarInactivas, setMostrarInactivas] = useState(false);

    const form = useForm({
        almacen_id: almacenSeleccionado ?? 0,
        padre_id: '' as string,
        codigo: '',
        nombre: '',
        tipo: 'rack' as AlmUbicacionTipo,
    });

    // El árbol lo arma el servidor: rehacerlo aquí obligaría a traer todas las
    // ubicaciones del almacén sólo para volver a colgarlas.
    const filas = ubicaciones.filter((u) => mostrarInactivas || u.activa);

    const cambiarAlmacen = (id: string) =>
        router.get('/admin/almacen/ubicaciones', { almacen_id: id }, { preserveState: false });

    const agregar = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/almacen/ubicaciones', {
            preserveScroll: true,
            onSuccess: () => form.reset('codigo', 'nombre', 'padre_id'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ubicaciones" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Ubicaciones</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Dónde está el material dentro de cada almacén: pasillos, racks, niveles y contenedores. El
                        almacén dice en qué bodega; esto dice en qué anaquel.
                    </p>
                </div>

                {almacenes.length === 0 ? (
                    <div className="alert alert-warning">
                        <span>No tienes ningún almacén asignado, así que no hay dónde dar de alta ubicaciones.</span>
                    </div>
                ) : (
                    <>
                        <div className="mb-4 flex flex-wrap items-end gap-3">
                            <div className="w-72">
                                <label className="label label-text text-xs">Almacén</label>
                                <Select value={String(almacenSeleccionado ?? '')} onValueChange={cambiarAlmacen}>
                                    {almacenes.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>
                                            {etiquetaDeAlmacen(a)}
                                        </SelectItem>
                                    ))}
                                </Select>
                            </div>

                            <label className="mb-2 flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox checkbox-sm"
                                    checked={mostrarInactivas}
                                    onChange={(e) => setMostrarInactivas(e.target.checked)}
                                />
                                <span className="text-sm">Mostrar dadas de baja</span>
                            </label>
                        </div>

                        {sinAcomodar > 0 && (
                            <div className="alert alert-info mb-4">
                                <span>
                                    {sinAcomodar} artículo(s) de este almacén no tienen lugar asignado. Se pueden
                                    acomodar desde la ficha del artículo o desde Existencias.
                                </span>
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div className="rounded-box border-base-300 overflow-hidden border lg:col-span-2">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th>Lugar</th>
                                            <th>Código</th>
                                            <th>Tipo</th>
                                            <th className="text-right">Artículos</th>
                                            <th className="text-center">Estado</th>
                                            <th className="w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filas.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="text-base-content/50 py-6 text-center">
                                                    Este almacén todavía no tiene ubicaciones. Mientras no las tenga, su
                                                    material aparece como «sin acomodar».
                                                </td>
                                            </tr>
                                        ) : (
                                            filas.map((u) => (
                                                <tr key={u.id} className={u.activa ? 'hover' : 'hover opacity-50'}>
                                                    <td>
                                                        <span
                                                            className="flex items-center gap-1"
                                                            style={{ paddingLeft: `${u.nivel * 1.25}rem` }}
                                                        >
                                                            {u.nivel > 0 && (
                                                                <CornerDownRightIcon className="text-base-content/30 size-3" />
                                                            )}
                                                            {u.nombre}
                                                        </span>
                                                    </td>
                                                    <td className="font-mono text-xs">{u.codigo}</td>
                                                    <td className="text-base-content/60 text-sm">
                                                        {tipos.find((t) => t.value === u.tipo)?.label ?? u.tipo}
                                                    </td>
                                                    <td className="text-right font-mono">
                                                        {u.articulos || <span className="text-base-content/30">—</span>}
                                                    </td>
                                                    <td className="text-center">
                                                        {u.activa ? (
                                                            <span className="badge badge-sm badge-success">Activa</span>
                                                        ) : (
                                                            <span
                                                                className="badge badge-sm badge-ghost"
                                                                title="No se borra: hay movimientos históricos que la mencionan"
                                                            >
                                                                De baja
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            className="btn btn-ghost btn-xs"
                                                            title={u.activa ? 'Dar de baja' : 'Reactivar'}
                                                            onClick={() =>
                                                                router.patch(
                                                                    `/admin/almacen/ubicaciones/${u.id}/toggle`,
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {u.activa ? (
                                                                <TrashIcon className="size-3.5" />
                                                            ) : (
                                                                <RotateCcwIcon className="size-3.5" />
                                                            )}
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <form onSubmit={agregar} className="rounded-box border-base-300 border p-4">
                                <h2 className="mb-4 font-medium">Nueva ubicación</h2>

                                <div className="space-y-4">
                                    <FormField
                                        label="Cuelga de"
                                        htmlFor="padre_id"
                                        error={form.errors.padre_id}
                                        description="Vacío la deja al nivel del almacén. Un nivel cuelga de un rack, y el rack de un pasillo."
                                    >
                                        <Select
                                            id="padre_id"
                                            value={form.data.padre_id}
                                            onValueChange={(v) => form.setData('padre_id', v)}
                                        >
                                            <SelectItem value="">Nada — va al nivel del almacén</SelectItem>
                                            {ubicaciones
                                                .filter((u) => u.activa)
                                                .map((u) => (
                                                    <SelectItem key={u.id} value={String(u.id)}>
                                                        {u.nombre} ({u.codigo})
                                                    </SelectItem>
                                                ))}
                                        </Select>
                                    </FormField>

                                    <FormField
                                        label="Código"
                                        htmlFor="codigo"
                                        error={form.errors.codigo}
                                        description="Es lo que se rotula en el anaquel. Único dentro del almacén."
                                        required
                                    >
                                        <Input
                                            id="codigo"
                                            value={form.data.codigo}
                                            onChange={(e) => form.setData('codigo', e.target.value.toUpperCase())}
                                            placeholder="A-1-3"
                                            className="font-mono"
                                        />
                                    </FormField>

                                    <FormField label="Nombre" htmlFor="nombre" error={form.errors.nombre} required>
                                        <Input
                                            id="nombre"
                                            value={form.data.nombre}
                                            onChange={(e) => form.setData('nombre', e.target.value)}
                                            placeholder="Nivel 3"
                                        />
                                    </FormField>

                                    <FormField label="Tipo" htmlFor="tipo" error={form.errors.tipo} required>
                                        <Select
                                            id="tipo"
                                            value={form.data.tipo}
                                            onValueChange={(v) => form.setData('tipo', v as AlmUbicacionTipo)}
                                        >
                                            {tipos.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </Select>
                                    </FormField>

                                    <Button type="submit" className="w-full" disabled={form.processing}>
                                        <PlusIcon className="size-4" />
                                        Agregar ubicación
                                    </Button>
                                </div>

                                <p className="text-base-content/60 mt-4 text-sm">
                                    Una ubicación con material no se borra: se da de baja. El kardex viejo la sigue
                                    mencionando, y borrarla dejaría movimientos apuntando a un lugar que ya no existe.
                                </p>
                            </form>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
