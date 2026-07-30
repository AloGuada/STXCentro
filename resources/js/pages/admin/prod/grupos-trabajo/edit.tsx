import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { UbicacionesMultiselect } from '@/components/prod/ubicaciones-multiselect';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCategoriaEmpleado, ProdGrupoEmpleado, ProdGrupoTrabajo, ProdUbicacion } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Props = {
    grupo: ProdGrupoTrabajo & { empleados: ProdGrupoEmpleado[]; ubicaciones: ProdUbicacion[] };
    ubicaciones: Pick<ProdUbicacion, 'id' | 'nombre'>[];
    categorias: Pick<ProdCategoriaEmpleado, 'id' | 'nombre' | 'valor'>[];
};

export default function GruposTrabajoEdit({ grupo, ubicaciones, categorias }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
        { title: grupo.descripcion, href: `/admin/prod/grupos-trabajo/${grupo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: grupo.descripcion,
        activo: grupo.activo,
        ubicacion_ids: (grupo.ubicaciones ?? []).map((u) => u.id),
    });

    const empleadoVacio = {
        nombre: '',
        no_empleado: '',
        categoria_empleado_id: categorias[0] ? String(categorias[0].id) : '',
    };
    const [newEmpleado, setNewEmpleado] = useState(empleadoVacio);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupos-trabajo/${grupo.id}`);
    };

    const handleAddEmpleado = () => {
        if (!newEmpleado.nombre.trim()) return;
        router.post(`/admin/prod/grupos-trabajo/${grupo.id}/empleados`, newEmpleado, {
            preserveScroll: true,
            onSuccess: () => setNewEmpleado(empleadoVacio),
        });
    };

    const handleRemoveEmpleado = (empleadoId: number) => {
        router.delete(`/admin/prod/grupos-trabajo/${grupo.id}/empleados/${empleadoId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${grupo.descripcion}`} />

            <div className="p-6">
                <div className="w-full max-w-3xl">
                    <h1 className="mb-6 text-2xl font-semibold">Editar grupo de trabajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                            />
                        </FormField>

                        <FormField
                            label="Ubicaciones"
                            htmlFor="ubicacion_ids"
                            error={errors.ubicacion_ids}
                            description="Dónde trabaja el grupo. Puede ser más de una."
                        >
                            <UbicacionesMultiselect
                                ubicaciones={ubicaciones}
                                seleccionadas={data.ubicacion_ids}
                                onChange={(ids) => setData('ubicacion_ids', ids)}
                            />
                        </FormField>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                            />
                            <span className="text-sm">Activo</span>
                        </label>

                        <div className="flex items-center justify-between">
                            <DeleteDialog
                                title="Eliminar grupo"
                                description={`¿Eliminar el grupo "${grupo.descripcion}"? Esta acción no se puede deshacer.`}
                                deleteUrl={`/admin/prod/grupos-trabajo/${grupo.id}`}
                            />
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/grupos-trabajo">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>

                    <div className="mt-8">
                        <h2 className="mb-2 text-lg font-semibold">Empleados</h2>

                        <div className="rounded-box border border-base-300 overflow-hidden">
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Nombre</th>
                                        <th className="w-36">No. Empleado</th>
                                        <th className="w-48">Categoria</th>
                                        <th className="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {grupo.empleados.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="text-center text-base-content/50 py-6">
                                                No hay empleados en este grupo.
                                            </td>
                                        </tr>
                                    ) : (
                                        grupo.empleados.map((emp) => (
                                            <tr key={emp.id} className="hover">
                                                <td className="font-medium">{emp.nombre}</td>
                                                <td>{emp.no_empleado || '-'}</td>
                                                <td>
                                                    {emp.categoria ? (
                                                        <span className="badge badge-sm badge-ghost">
                                                            {emp.categoria.nombre} ({emp.categoria.valor})
                                                        </span>
                                                    ) : (
                                                        <span className="text-warning text-xs">Sin categoria</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <Button type="button" variant="ghost" size="icon" onClick={() => handleRemoveEmpleado(emp.id)}>
                                                        <TrashIcon className="size-4 text-error" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                <tfoot>
                                    <tr className="bg-base-100">
                                        <td>
                                            <Input
                                                value={newEmpleado.nombre}
                                                onChange={(e) => setNewEmpleado({ ...newEmpleado, nombre: e.target.value })}
                                                placeholder="Nombre del empleado"
                                                className="input-sm"
                                            />
                                        </td>
                                        <td>
                                            <Input
                                                value={newEmpleado.no_empleado}
                                                onChange={(e) => setNewEmpleado({ ...newEmpleado, no_empleado: e.target.value })}
                                                placeholder="No. Emp."
                                                className="input-sm"
                                            />
                                        </td>
                                        <td>
                                            <Select
                                                value={newEmpleado.categoria_empleado_id}
                                                onValueChange={(v) =>
                                                    setNewEmpleado({ ...newEmpleado, categoria_empleado_id: v })
                                                }
                                                className="select-sm"
                                                placeholder="Sin categoria"
                                            >
                                                {categorias.map((c) => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.nombre} ({c.valor})
                                                    </SelectItem>
                                                ))}
                                            </Select>
                                        </td>
                                        <td>
                                            <Button type="button" size="icon" onClick={handleAddEmpleado} disabled={!newEmpleado.nombre.trim()}>
                                                <PlusIcon className="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
