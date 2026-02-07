import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CRITICIDAD_LABELS, type StiCriticidad } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Equipos', href: '/admin/sti/equipos' },
    { title: 'Nuevo Equipo', href: '/admin/sti/equipos/create' },
];

export default function EquiposCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        serie: '',
        marca: '',
        factor_criticidad: 2 as StiCriticidad,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/equipos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Equipo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Equipo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre o descripcion del equipo"
                            />
                        </FormField>

                        <FormField label="Numero de Serie" htmlFor="serie" error={errors.serie}>
                            <Input
                                id="serie"
                                value={data.serie}
                                onChange={(e) => setData('serie', e.target.value)}
                                placeholder="Numero de serie"
                            />
                        </FormField>

                        <FormField label="Marca" htmlFor="marca" error={errors.marca}>
                            <Input
                                id="marca"
                                value={data.marca}
                                onChange={(e) => setData('marca', e.target.value)}
                                placeholder="Marca del equipo"
                            />
                        </FormField>

                        <FormField label="Factor de Criticidad" htmlFor="factor_criticidad" error={errors.factor_criticidad} required>
                            <Select
                                id="factor_criticidad"
                                value={data.factor_criticidad.toString()}
                                onValueChange={(value) => setData('factor_criticidad', parseInt(value) as StiCriticidad)}
                            >
                                {Object.entries(CRITICIDAD_LABELS).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/equipos">Cancelar</Link>
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
