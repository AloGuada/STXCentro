import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiEquipo, StiTecnico } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
    { title: 'Nuevo Mantenimiento', href: '/admin/sti/mantenimientos/create' },
];

type Props = {
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
    tecnicos: Pick<StiTecnico, 'id' | 'descripcion'>[];
};

export default function MantenimientosCreate({ equipos, tecnicos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        equipo_id: '',
        fecha_programada: '',
        descripcion: '',
        tecnico_id: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/mantenimientos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Mantenimiento" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Mantenimiento</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Equipo" htmlFor="equipo_id" error={errors.equipo_id} required>
                            <Select
                                id="equipo_id"
                                value={data.equipo_id}
                                onValueChange={(value) => setData('equipo_id', value)}
                            >
                                <option value="">Seleccionar equipo</option>
                                {equipos.map((equipo) => (
                                    <option key={equipo.id} value={equipo.id}>
                                        {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Fecha Programada" htmlFor="fecha_programada" error={errors.fecha_programada} required>
                            <Input
                                id="fecha_programada"
                                type="date"
                                value={data.fecha_programada}
                                onChange={(e) => setData('fecha_programada', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Tecnico" htmlFor="tecnico_id" error={errors.tecnico_id} required>
                            <Select
                                id="tecnico_id"
                                value={data.tecnico_id}
                                onValueChange={(value) => setData('tecnico_id', value)}
                            >
                                <option value="">Seleccionar tecnico</option>
                                {tecnicos.map((tecnico) => (
                                    <option key={tecnico.id} value={tecnico.id}>
                                        {tecnico.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Descripcion (opcional)" htmlFor="descripcion" error={errors.descripcion}>
                            <textarea
                                id="descripcion"
                                className="textarea textarea-bordered min-h-24 w-full"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del mantenimiento"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/mantenimientos">Cancelar</Link>
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
