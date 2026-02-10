import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import { type FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupos', href: '/admin/prod/grupos' },
    { title: 'Nuevo Grupo', href: '/admin/prod/grupos/create' },
];

type EmpleadoLocal = {
    nombre: string;
    no_empleado: string;
};

export default function GruposCreate() {
    const { data, setData, post, processing, errors } = useForm<{
        descripcion: string;
        empleados: EmpleadoLocal[];
    }>({
        descripcion: '',
        empleados: [],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/grupos');
    };

    const addEmpleado = () => {
        setData('empleados', [...data.empleados, { nombre: '', no_empleado: '' }]);
    };

    const removeEmpleado = (index: number) => {
        setData(
            'empleados',
            data.empleados.filter((_, i) => i !== index),
        );
    };

    const updateEmpleado = (index: number, field: keyof EmpleadoLocal, value: string) => {
        const updated = [...data.empleados];
        updated[index] = { ...updated[index], [field]: value };
        setData('empleados', updated);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Grupo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Grupo de Trabajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre del grupo"
                            />
                        </FormField>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-sm font-medium">Empleados</label>
                                <Button type="button" variant="outline" size="sm" onClick={addEmpleado}>
                                    <PlusIcon className="mr-1 size-4" />
                                    Agregar
                                </Button>
                            </div>

                            {data.empleados.map((emp, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <Input
                                        value={emp.nombre}
                                        onChange={(e) => updateEmpleado(index, 'nombre', e.target.value)}
                                        placeholder="Nombre del empleado"
                                        className="flex-1"
                                    />
                                    <Input
                                        value={emp.no_empleado}
                                        onChange={(e) => updateEmpleado(index, 'no_empleado', e.target.value)}
                                        placeholder="No. Empleado"
                                        className="w-32"
                                    />
                                    <Button type="button" variant="ghost" size="sm" onClick={() => removeEmpleado(index)}>
                                        <TrashIcon className="size-4 text-error" />
                                    </Button>
                                </div>
                            ))}

                            {data.empleados.length === 0 && <p className="text-sm text-gray-500">Sin empleados. Puedes agregarlos despues.</p>}
                        </div>

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
            </div>
        </AppLayout>
    );
}
