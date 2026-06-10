import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/categorias-tarjeta' },
    { title: 'Categorías de tarjeta', href: '/admin/cotiz/categorias-tarjeta' },
    { title: 'Nueva', href: '/admin/cotiz/categorias-tarjeta/create' },
];

export default function CategoriasTarjetaCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        orden: '0',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/categorias-tarjeta');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva categoría de tarjeta" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva categoría de tarjeta</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                            <Input id="orden" type="number" min="0" value={data.orden} onChange={(e) => setData('orden', e.target.value)} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/categorias-tarjeta">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
