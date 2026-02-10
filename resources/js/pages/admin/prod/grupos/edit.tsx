import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdEmpleadoGrupo, ProdGrupo } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon, UsersIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    grupo: ProdGrupo & {
        empleados: ProdEmpleadoGrupo[];
    };
};

export default function GruposEdit({ grupo }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'empleados'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupos', href: '/admin/prod/grupos' },
        { title: grupo.descripcion, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: grupo.descripcion,
    });

    const empForm = useForm({
        nombre: '',
        no_empleado: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupos/${grupo.id}`);
    };

    const handleAddEmpleado = (e: FormEvent) => {
        e.preventDefault();
        empForm.post(`/admin/prod/grupos/${grupo.id}/empleados`, {
            preserveScroll: true,
            onSuccess: () => empForm.reset(),
        });
    };

    const handleRemoveEmpleado = (empleadoId: number) => {
        router.delete(`/admin/prod/grupos/${grupo.id}/empleados/${empleadoId}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Grupo: ${grupo.descripcion}`} />

            <div className="space-y-6 p-6">
                <div className="tabs tabs-boxed w-3/4">
                    <button type="button" className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>
                        Datos del Grupo
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'empleados' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('empleados')}
                    >
                        <UsersIcon className="mr-1 size-4" />
                        Empleados ({grupo.empleados?.length ?? 0})
                    </button>
                </div>

                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Grupo</h1>
                            <DeleteDialog
                                title="Eliminar grupo"
                                description={`Eliminar el grupo "${grupo.descripcion}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/prod/grupos/${grupo.id}`}
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre del grupo"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/grupos">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {activeTab === 'empleados' && (
                    <div className="w-3/4 space-y-6">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <UsersIcon className="size-5" />
                            Empleados del Grupo
                        </h2>

                        {grupo.empleados && grupo.empleados.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>No. Empleado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {grupo.empleados.map((emp) => (
                                            <tr key={emp.id}>
                                                <td>{emp.nombre}</td>
                                                <td className="font-mono text-sm">{emp.no_empleado ?? '-'}</td>
                                                <td>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleRemoveEmpleado(emp.id)}>
                                                        <TrashIcon className="size-4 text-error" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay empleados en este grupo.</p>
                        )}

                        <div className="divider" />
                        <h2 className="text-lg font-semibold">Agregar Empleado</h2>
                        <form onSubmit={handleAddEmpleado} className="flex items-end gap-4">
                            <FormField label="Nombre" htmlFor="emp_nombre" error={empForm.errors.nombre} required>
                                <Input
                                    id="emp_nombre"
                                    value={empForm.data.nombre}
                                    onChange={(e) => empForm.setData('nombre', e.target.value)}
                                    placeholder="Nombre del empleado"
                                />
                            </FormField>
                            <FormField label="No. Empleado" htmlFor="emp_no">
                                <Input
                                    id="emp_no"
                                    value={empForm.data.no_empleado}
                                    onChange={(e) => empForm.setData('no_empleado', e.target.value)}
                                    placeholder="Opcional"
                                    className="w-32"
                                />
                            </FormField>
                            <Button type="submit" disabled={empForm.processing || !empForm.data.nombre}>
                                {empForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                                Agregar
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
