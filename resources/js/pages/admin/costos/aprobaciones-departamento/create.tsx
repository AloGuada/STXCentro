import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, Usuario } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/aprobaciones-departamento' },
    { title: 'Aprobadores', href: '/admin/costos/aprobaciones-departamento' },
    { title: 'Nuevo', href: '/admin/costos/aprobaciones-departamento/create' },
];

type Props = {
    departamentos: Departamento[];
    usuarios: Usuario[];
};

export default function AprobacionesDepartamentoCreate({ departamentos, usuarios }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        departamento_id: '',
        nivel: 1,
        nombre_nivel: '',
        aprobador_id: '',
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/costos/aprobaciones-departamento');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Aprobador" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Aprobador</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                            <select
                                id="departamento_id"
                                className="select select-bordered w-full"
                                value={data.departamento_id}
                                onChange={(e) => setData('departamento_id', e.target.value)}
                            >
                                <option value="">Seleccionar departamento</option>
                                {departamentos.map((d) => (
                                    <option key={d.id} value={d.id}>{d.descripcion}</option>
                                ))}
                            </select>
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nivel" htmlFor="nivel" error={errors.nivel} required>
                                <Input
                                    id="nivel"
                                    type="number"
                                    min={1}
                                    value={data.nivel}
                                    onChange={(e) => setData('nivel', parseInt(e.target.value) || 1)}
                                />
                            </FormField>

                            <FormField label="Nombre del Nivel" htmlFor="nombre_nivel" error={errors.nombre_nivel} required>
                                <Input
                                    id="nombre_nivel"
                                    value={data.nombre_nivel}
                                    onChange={(e) => setData('nombre_nivel', e.target.value)}
                                    placeholder="Ej: Jefe Depto, Gerente..."
                                />
                            </FormField>
                        </div>

                        <FormField label="Aprobador" htmlFor="aprobador_id" error={errors.aprobador_id}>
                            <select
                                id="aprobador_id"
                                className="select select-bordered w-full"
                                value={data.aprobador_id}
                                onChange={(e) => setData('aprobador_id', e.target.value)}
                            >
                                <option value="">Sin asignar</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name}</option>
                                ))}
                            </select>
                        </FormField>

                        <FormField label="Estado" htmlFor="activo" error={errors.activo}>
                            <label className="label cursor-pointer justify-start gap-2">
                                <input
                                    type="checkbox"
                                    className="checkbox"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                />
                                <span>Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/aprobaciones-departamento">Cancelar</Link>
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
