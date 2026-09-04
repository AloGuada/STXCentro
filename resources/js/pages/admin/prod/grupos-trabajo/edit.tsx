import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { PersonaPicker, type PersonaOption, type PersonaSeleccion } from '@/components/prod/persona-picker';
import { UbicacionesMultiselect } from '@/components/prod/ubicaciones-multiselect';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCategoriaEmpleado, ProdGrupoEmpleado, ProdGrupoTrabajo, ProdUbicacion } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Props = {
    grupo: ProdGrupoTrabajo & { empleados: ProdGrupoEmpleado[]; ubicaciones: ProdUbicacion[] };
    ubicaciones: Pick<ProdUbicacion, 'id' | 'nombre'>[];
    categorias: Pick<ProdCategoriaEmpleado, 'id' | 'nombre' | 'valor'>[];
    personas: PersonaOption[];
};

export default function GruposTrabajoEdit({ grupo, ubicaciones, categorias, personas }: Props) {
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

    const [categoriaNueva, setCategoriaNueva] = useState(categorias[0] ? String(categorias[0].id) : '');

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupos-trabajo/${grupo.id}`);
    };

    const handleAddEmpleado = (seleccion: PersonaSeleccion) => {
        router.post(
            `/admin/prod/grupos-trabajo/${grupo.id}/empleados`,
            {
                persona_id: seleccion.persona_id,
                persona_nueva: seleccion.persona_nueva,
                categoria_empleado_id: categoriaNueva || null,
            },
            { preserveScroll: true },
        );
    };

    // La pantalla edita un dato a la vez: se manda solo el que cambio y el
    // backend deja el resto como esta.
    const handleEditEmpleado = (
        empleadoId: number,
        cambios: Record<string, string | number | null | { nombre: string; apellido: string }>,
    ) => {
        router.patch(`/admin/prod/grupos-trabajo/${grupo.id}/empleados/${empleadoId}`, cambios, {
            preserveScroll: true,
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
                        <h2 className="text-lg font-semibold">Empleados</h2>
                        <p className="mb-2 text-sm text-base-content/60">
                            La categoría y el enlace con RH se guardan solos al cambiarlos; el botón Guardar de arriba
                            es para el grupo.
                        </p>

                        <div className="rounded-box border border-base-300 overflow-hidden">
                            <table className="table table-sm">
                                <thead className="bg-base-200">
                                    <tr>
                                        <th>Nombre</th>
                                        <th className="w-36">No. Empleado</th>
                                        <th className="w-56">RH</th>
                                        <th className="w-48">Categoria</th>
                                        <th className="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {grupo.empleados.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center text-base-content/50 py-6">
                                                No hay empleados en este grupo.
                                            </td>
                                        </tr>
                                    ) : (
                                        grupo.empleados.map((emp) => (
                                            <tr key={emp.id} className="hover">
                                                <td className="font-medium">{emp.nombre}</td>
                                                <td>{emp.no_empleado || '-'}</td>
                                                <td>
                                                    {!emp.persona_id ? (
                                                        <div className="space-y-1">
                                                            <span className="badge badge-sm badge-error badge-outline">
                                                                Sin vincular
                                                            </span>
                                                            <PersonaPicker
                                                            personas={personas}
                                                            excluir={grupo.empleados.flatMap((e) =>
                                                                e.persona_id ? [e.persona_id] : [],
                                                            )}
                                                            className="select-sm"
                                                            onSelect={(seleccion) =>
                                                                handleEditEmpleado(emp.id, {
                                                                    persona_id: seleccion.persona_id,
                                                                    persona_nueva: seleccion.persona_nueva,
                                                                })
                                                            }
                                                            />
                                                        </div>
                                                    ) : emp.persona?.periodo_vigente ? (
                                                        <span className="badge badge-sm badge-success badge-outline">Contratado</span>
                                                    ) : (
                                                        <span className="badge badge-sm badge-warning badge-outline">Sin contrato</span>
                                                    )}
                                                </td>
                                                <td>
                                                    <Select
                                                        value={
                                                            emp.categoria_empleado_id
                                                                ? String(emp.categoria_empleado_id)
                                                                : ''
                                                        }
                                                        onValueChange={(v) =>
                                                            handleEditEmpleado(emp.id, {
                                                                categoria_empleado_id: v || null,
                                                            })
                                                        }
                                                        className={`select-sm ${emp.categoria_empleado_id ? '' : 'select-warning'}`}
                                                    >
                                                        <SelectItem value="">Sin categoria</SelectItem>
                                                        {categorias.map((c) => (
                                                            <SelectItem key={c.id} value={String(c.id)}>
                                                                {c.nombre} ({c.valor})
                                                            </SelectItem>
                                                        ))}
                                                    </Select>
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
                                        <td colSpan={3}>
                                            <PersonaPicker
                                                personas={personas}
                                                excluir={grupo.empleados.flatMap((e) => (e.persona_id ? [e.persona_id] : []))}
                                                onSelect={handleAddEmpleado}
                                            />
                                        </td>
                                        <td colSpan={2}>
                                            <Select
                                                value={categoriaNueva}
                                                onValueChange={setCategoriaNueva}
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
