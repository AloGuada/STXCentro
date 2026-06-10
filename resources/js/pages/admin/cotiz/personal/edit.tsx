import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizPersonalCategoria } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/personal' },
    { title: 'Categorías de personal', href: '/admin/cotiz/personal' },
    { title: 'Editar', href: '/admin/cotiz/personal' },
];

type Props = {
    personal: CotizPersonalCategoria;
};

export default function PersonalEdit({ personal }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        codigo: personal.codigo,
        nombre: personal.nombre,
        sueldo_semanal: personal.sueldo_semanal,
        orden: String(personal.orden),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/personal/${personal.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${personal.codigo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar categoría de personal</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Sueldo semanal" htmlFor="sueldo_semanal" error={errors.sueldo_semanal} required>
                                <Input id="sueldo_semanal" type="number" step="0.01" min="0" value={data.sueldo_semanal} onChange={(e) => setData('sueldo_semanal', e.target.value)} />
                            </FormField>
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/personal">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
