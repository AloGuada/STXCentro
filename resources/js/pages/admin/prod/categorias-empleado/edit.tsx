import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdCategoriaEmpleado } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = { categoria: ProdCategoriaEmpleado };

export default function CategoriasEmpleadoEdit({ categoria }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Categorias de empleado', href: '/admin/prod/categorias-empleado' },
        { title: categoria.nombre, href: `/admin/prod/categorias-empleado/${categoria.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: categoria.nombre,
        valor: Number(categoria.valor),
        orden: Number(categoria.orden ?? 0),
        activo: categoria.activo,
    });

    const enUso = (categoria.empleados_count ?? 0) > 0;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/categorias-empleado/${categoria.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${categoria.nombre}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Editar categoría</h1>
                    <p className="text-base-content/60 mb-6 mt-1 text-sm">
                        Cambiar el valor afecta los repartos de las semanas abiertas; las cerradas conservan el valor
                        con el que se liquidaron.
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                error={!!errors.nombre}
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                label="Valor (peso)"
                                htmlFor="valor"
                                error={errors.valor}
                                description="No es dinero: reparte el excedente del destajo."
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

                        <div className="flex items-center justify-between gap-2">
                            {enUso ? (
                                <span className="text-base-content/60 text-sm">
                                    {categoria.empleados_count} empleado(s) usan esta categoría.
                                </span>
                            ) : (
                                <DeleteDialog
                                    title="Eliminar categoría"
                                    description={`¿Eliminar la categoría "${categoria.nombre}"? Esta acción no se puede deshacer.`}
                                    deleteUrl={`/admin/prod/categorias-empleado/${categoria.id}`}
                                />
                            )}
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/categorias-empleado">Cancelar</Link>
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
