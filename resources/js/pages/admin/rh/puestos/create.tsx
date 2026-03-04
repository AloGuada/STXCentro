import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/skills' },
    { title: 'Puestos', href: '/admin/rh/puestos' },
    { title: 'Nuevo Puesto', href: '/admin/rh/puestos/create' },
];

type Props = {
    departamentos: Departamento[];
    puestos: { id: number; nombre: string }[];
};

export default function PuestoCreate({ departamentos, puestos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        departamento_id: '',
        descripcion: '',
        codigo: '',
        ubicacion: '',
        hora_entrada: '',
        hora_salida: '',
        puesto_jefe_id: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/rh/puestos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Puesto" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Puesto</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre del puesto" />
                        </FormField>

                        <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                            <Select value={data.departamento_id} onValueChange={(v) => setData('departamento_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar departamento" />
                                </SelectTrigger>
                                <SelectContent>
                                    {departamentos.map((dep) => (
                                        <SelectItem key={dep.id} value={String(dep.id)}>
                                            {dep.descripcion}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion}>
                            <textarea id="descripcion" className="textarea textarea-bordered w-full" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} placeholder="Descripcion del puesto" />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Codigo" htmlFor="codigo" error={errors.codigo}>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Codigo del puesto" />
                            </FormField>

                            <FormField label="Ubicacion" htmlFor="ubicacion" error={errors.ubicacion}>
                                <Input id="ubicacion" value={data.ubicacion} onChange={(e) => setData('ubicacion', e.target.value)} placeholder="Ubicacion" />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Hora de Entrada" htmlFor="hora_entrada" error={errors.hora_entrada}>
                                <Input id="hora_entrada" type="time" value={data.hora_entrada} onChange={(e) => setData('hora_entrada', e.target.value)} />
                            </FormField>

                            <FormField label="Hora de Salida" htmlFor="hora_salida" error={errors.hora_salida}>
                                <Input id="hora_salida" type="time" value={data.hora_salida} onChange={(e) => setData('hora_salida', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Puesto Jefe" htmlFor="puesto_jefe_id" error={errors.puesto_jefe_id}>
                            <Select value={data.puesto_jefe_id} onValueChange={(v) => setData('puesto_jefe_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar puesto jefe (opcional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    {puestos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/puestos">Cancelar</Link>
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
