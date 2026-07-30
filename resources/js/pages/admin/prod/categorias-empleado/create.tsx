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
    { title: 'Categorias de empleado', href: '/admin/prod/categorias-empleado' },
    { title: 'Nueva', href: '/admin/prod/categorias-empleado/create' },
];

export default function CategoriasEmpleadoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        valor: 1500,
        orden: 0,
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/categorias-empleado');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva categoria de empleado" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva categoría de empleado</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                error={!!errors.nombre}
                                placeholder="Ej. Oficial, Media cuchara, Ayudante"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                label="Valor (peso)"
                                htmlFor="valor"
                                error={errors.valor}
                                description="Reparte el excedente del destajo; no es dinero. Un 2000 se lleva el doble que un 1000."
                                required
                            >
                                <Input
                                    id="valor"
                                    type="number"
                                    min={1}
                                    value={data.valor}
                                    onChange={(e) => setData('valor', Number(e.target.value))}
                                    error={!!errors.valor}
                                />
                            </FormField>

                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input
                                    id="orden"
                                    type="number"
                                    min={0}
                                    value={data.orden}
                                    onChange={(e) => setData('orden', Number(e.target.value))}
                                    error={!!errors.orden}
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
                            <span className="text-sm">Activa</span>
                        </label>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/categorias-empleado">Cancelar</Link>
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
