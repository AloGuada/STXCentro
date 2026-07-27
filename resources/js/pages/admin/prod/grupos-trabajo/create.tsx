import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
    { title: 'Nuevo Grupo', href: '/admin/prod/grupos-trabajo/create' },
];

type EmpleadoForm = { nombre: string; no_empleado: string; porcentaje: string };

export default function GruposTrabajoCreate() {
    const { data, setData, post, processing, errors } = useForm<{
        descripcion: string;
        linea: string;
        modulo: string;
        activo: boolean;
        empleados: EmpleadoForm[];
    }>({
        descripcion: '',
        linea: '0',
        modulo: '0',
        activo: true,
        empleados: [],
    });

    const addEmpleado = () => {
        setData('empleados', [...data.empleados, { nombre: '', no_empleado: '', porcentaje: '100' }]);
    };

    const removeEmpleado = (index: number) => {
        setData('empleados', data.empleados.filter((_, i) => i !== index));
    };

    const updateEmpleado = (index: number, field: keyof EmpleadoForm, value: string) => {
        const updated = [...data.empleados];
        updated[index] = { ...updated[index], [field]: value };
        setData('empleados', updated);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/grupos-trabajo');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Grupo de Trabajo" />

            <div className="p-6">
                <div className="w-full max-w-3xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo grupo de trabajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                                placeholder="Nombre del grupo"
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
                                    error={!!errors.linea}
                                />
                            </FormField>

                            <FormField label="Modulo" htmlFor="modulo" error={errors.modulo}>
                                <Input
                                    id="modulo"
                                    type="number"
                                    min="0"
                                    value={data.modulo}
                                    onChange={(e) => setData('modulo', e.target.value)}
                                    error={!!errors.modulo}
                                />
                            </FormField>
                        </div>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                            />
                            <span className="text-sm">Activo</span>
                        </label>

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <h2 className="text-lg font-semibold">Empleados</h2>
                                <Button type="button" variant="outline" size="sm" onClick={addEmpleado}>
                                    <PlusIcon className="size-4" /> Agregar
                                </Button>
                            </div>

                            <div className="rounded-box border border-base-300 overflow-hidden">
                                <table className="table table-sm">
                                    <thead className="bg-base-200">
                                        <tr>
                                            <th>Nombre</th>
                                            <th className="w-36">No. Empleado</th>
                                            <th className="w-28 text-right">%</th>
                                            <th className="w-12"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.empleados.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="text-center text-base-content/50 py-6">
                                                    Sin empleados. Usa "Agregar" para añadir integrantes.
                                                </td>
                                            </tr>
                                        ) : (
                                            data.empleados.map((emp, index) => (
                                                <tr key={index} className="hover">
                                                    <td>
                                                        <Input
                                                            value={emp.nombre}
                                                            onChange={(e) => updateEmpleado(index, 'nombre', e.target.value)}
                                                            placeholder="Nombre"
                                                            className="input-sm"
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            value={emp.no_empleado}
                                                            onChange={(e) => updateEmpleado(index, 'no_empleado', e.target.value)}
                                                            placeholder="No. Emp."
                                                            className="input-sm"
                                                        />
                                                    </td>
                                                    <td>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="100"
                                                            value={emp.porcentaje}
                                                            onChange={(e) => updateEmpleado(index, 'porcentaje', e.target.value)}
                                                            placeholder="%"
                                                            className="input-sm text-right font-mono"
                                                        />
                                                    </td>
                                                    <td>
                                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeEmpleado(index)}>
                                                            <TrashIcon className="size-4 text-error" />
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/grupos-trabajo">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
