import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCategoria } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    categoria: ProdCategoria;
};

export default function CategoriasEdit({ categoria }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Categorias', href: '/admin/prod/categorias' },
        { title: categoria.nombre, href: `/admin/prod/categorias/${categoria.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: categoria.nombre,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/categorias/${categoria.id}`);
    };

    const enUso = (categoria.conceptos_count ?? 0) > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${categoria.nombre}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Editar categoria</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                error={!!errors.nombre}
                            />
                        </FormField>

                        <div className="flex items-center justify-between">
                            {enUso ? (
                                <span className="text-sm text-base-content/60">
                                    {categoria.conceptos_count} pieza(s) usan esta categoria.
                                </span>
                            ) : (
                                <DeleteDialog
                                    title="Eliminar categoria"
                                    description={`¿Eliminar la categoria "${categoria.nombre}"? Esta acción no se puede deshacer.`}
                                    deleteUrl={`/admin/prod/categorias/${categoria.id}`}
                                />
                            )}
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/categorias">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
