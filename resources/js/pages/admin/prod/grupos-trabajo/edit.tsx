import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdGrupoEmpleado, ProdGrupoTrabajo } from '@/types/models';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Props = {
    grupo: ProdGrupoTrabajo & { empleados: ProdGrupoEmpleado[] };
};

export default function GruposTrabajoEdit({ grupo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
        { title: grupo.descripcion, href: `/admin/prod/grupos-trabajo/${grupo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: grupo.descripcion,
        linea: String(grupo.linea),
        modulo: String(grupo.modulo),
        activo: grupo.activo,
    });

    const [newEmpleado, setNewEmpleado] = useState({ nombre: '', no_empleado: '', porcentaje: '100' });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupos-trabajo/${grupo.id}`);
    };

    const handleDelete = () => {
        if (confirm('Estas seguro de eliminar este grupo?')) {
            router.delete(`/admin/prod/grupos-trabajo/${grupo.id}`);
        }
    };

    const handleAddEmpleado = () => {
        if (!newEmpleado.nombre.trim()) return;
        router.post(`/admin/prod/grupos-trabajo/${grupo.id}/empleados`, newEmpleado, {
            preserveScroll: true,
            onSuccess: () => setNewEmpleado({ nombre: '', no_empleado: '', porcentaje: '100' }),
        });
    };

    const handleRemoveEmpleado = (empleadoId: number) => {
        router.delete(`/admin/prod/grupos-trabajo/${grupo.id}/empleados/${empleadoId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${grupo.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Grupo de Trabajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Linea" htmlFor="linea" error={errors.linea}>
                                <Input
                                    id="linea"
                                    type="number"
                                    min="0"
                                    value={data.linea}
                                    onChange={(e) => setData('linea', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Modulo" htmlFor="modulo" error={errors.modulo}>
                                <Input
                                    id="modulo"
                                    type="number"
                                    min="0"
                                    value={data.modulo}
                                    onChange={(e) => setData('modulo', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Estado" htmlFor="activo">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                <span className="text-sm">Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-between">
                            <Button type="button" variant="destructive" onClick={handleDelete}>
                                Eliminar
                            </Button>
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

                    <hr className="my-6" />

                    <div>
                        <h3 className="mb-4 text-lg font-medium">Empleados</h3>

                        <div className="mb-4 space-y-2">
                            {grupo.empleados.map((emp) => (
                                <div key={emp.id} className="flex items-center gap-2 rounded border p-2">
                                    <span className="flex-1">{emp.nombre}</span>
                                    <span className="w-24 text-sm text-gray-500">{emp.no_empleado || '-'}</span>
                                    <span className="w-20 font-mono text-sm">{Number(emp.porcentaje).toFixed(2)}%</span>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => handleRemoveEmpleado(emp.id)}>
                                        <TrashIcon className="size-4 text-red-500" />
                                    </Button>
                                </div>
                            ))}
                            {grupo.empleados.length === 0 && (
                                <p className="text-sm text-gray-500">No hay empleados en este grupo.</p>
                            )}
                        </div>

                        <div className="flex items-end gap-2">
                            <div className="flex-1">
                                <Input
                                    value={newEmpleado.nombre}
                                    onChange={(e) => setNewEmpleado({ ...newEmpleado, nombre: e.target.value })}
                                    placeholder="Nombre del empleado"
                                />
                            </div>
                            <div className="w-32">
                                <Input
                                    value={newEmpleado.no_empleado}
                                    onChange={(e) => setNewEmpleado({ ...newEmpleado, no_empleado: e.target.value })}
                                    placeholder="No. Emp."
                                />
                            </div>
                            <div className="w-24">
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    value={newEmpleado.porcentaje}
                                    onChange={(e) => setNewEmpleado({ ...newEmpleado, porcentaje: e.target.value })}
                                    placeholder="%"
                                />
                            </div>
                            <Button type="button" onClick={handleAddEmpleado}>
                                <PlusIcon className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
