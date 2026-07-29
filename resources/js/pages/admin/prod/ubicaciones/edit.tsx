import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdUbicacion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = { ubicacion: ProdUbicacion };

export default function UbicacionesEdit({ ubicacion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Ubicaciones', href: '/admin/prod/ubicaciones' },
        { title: ubicacion.nombre, href: `/admin/prod/ubicaciones/${ubicacion.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: ubicacion.nombre,
        activo: ubicacion.activo,
    });

    const enUso = (ubicacion.grupos_trabajo_count ?? 0) > 0;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/ubicaciones/${ubicacion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${ubicacion.nombre}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Editar ubicación</h1>
                    <p className="text-base-content/60 mb-6 mt-1 text-sm">
                        {enUso
                            ? `Usada por ${ubicacion.grupos_trabajo_count} grupo(s) de trabajo.`
                            : 'Sin grupos asignados.'}
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

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                            />
                            <span className="text-sm">Activa</span>
                        </label>

                        <div className="flex justify-between gap-2">
                            {enUso ? (
                                <span className="text-base-content/60 text-sm">
                                    {ubicacion.grupos_trabajo_count} grupo(s) usan esta ubicación.
                                </span>
                            ) : (
                                <DeleteDialog
                                    title="Eliminar ubicación"
                                    description={`¿Eliminar la ubicación "${ubicacion.nombre}"? Esta acción no se puede deshacer.`}
                                    deleteUrl={`/admin/prod/ubicaciones/${ubicacion.id}`}
                                />
                            )}
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/ubicaciones">Cancelar</Link>
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
