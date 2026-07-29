import { FormField } from '@/components/form';
import { UbicacionesMultiselect } from '@/components/prod/ubicaciones-multiselect';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCategoriaEmpleado, ProdUbicacion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Grupos Trabajo', href: '/admin/prod/grupos-trabajo' },
    { title: 'Nuevo Grupo', href: '/admin/prod/grupos-trabajo/create' },
];

type EmpleadoForm = { nombre: string; no_empleado: string; categoria_empleado_id: string };

type Props = {
    ubicaciones: Pick<ProdUbicacion, 'id' | 'nombre'>[];
    categorias: Pick<ProdCategoriaEmpleado, 'id' | 'nombre' | 'valor'>[];
};

export default function GruposTrabajoCreate({ ubicaciones, categorias }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        descripcion: string;
        activo: boolean;
        ubicacion_ids: number[];
        empleados: EmpleadoForm[];
    }>({
        descripcion: '',
        activo: true,
        ubicacion_ids: [],
        empleados: [],
    });

    const addEmpleado = () => {
        setData('empleados', [
            ...data.empleados,
            { nombre: '', no_empleado: '', categoria_empleado_id: categorias[0] ? String(categorias[0].id) : '' },
        ]);
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
                                            <th className="w-48">Categoria</th>
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
                                                        <Select
                                                            value={emp.categoria_empleado_id}
                                                            onValueChange={(v) => updateEmpleado(index, 'categoria_empleado_id', v)}
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
