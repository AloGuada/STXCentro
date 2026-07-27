import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Categorias', href: '/admin/prod/categorias' },
    { title: 'Nueva', href: '/admin/prod/categorias/create' },
];

export default function CategoriasCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/categorias');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Categoria" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva categoria</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                error={!!errors.nombre}
                                placeholder="Ej. Columna, Trabe, Placa base"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/categorias">Cancelar</Link>
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
