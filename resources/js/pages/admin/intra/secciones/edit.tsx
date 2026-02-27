import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SeccionEstatica } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

type Props = {
    seccion: SeccionEstatica;
};

export default function SeccionesEdit({ seccion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Intranet', href: '/admin/intra/secciones' },
        { title: 'Secciones', href: '/admin/intra/secciones' },
        { title: seccion.titulo, href: `/admin/intra/secciones/${seccion.slug}/edit` },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        _method: string;
        titulo: string;
        descripcion: string;
        boton: string;
        url_externa: string;
        activo: boolean;
        file: File | null;
    }>({
        _method: 'PUT',
        titulo: seccion.titulo,
        descripcion: seccion.descripcion ?? '',
        boton: seccion.boton,
        url_externa: seccion.url_externa ?? '',
        activo: seccion.activo,
        file: null,
    });

    const isUrlExterna = data.url_externa.length > 0;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/intra/secciones/${seccion.slug}`);
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        setData('file', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${seccion.titulo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Sección</h1>
                        <DeleteDialog
                            title="Eliminar sección"
                            description={`¿Estás seguro de eliminar la sección "${seccion.titulo}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/intra/secciones/${seccion.slug}`}
                        />
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Título" htmlFor="titulo" error={errors.titulo} required>
                            <Input
                                id="titulo"
                                value={data.titulo}
                                onChange={(e) => setData('titulo', e.target.value)}
                                placeholder="Título de la sección"
                            />
                        </FormField>

                        <FormField label="Slug" htmlFor="slug" description="Se genera automáticamente desde el título">
                            <Input
                                id="slug"
                                value={seccion.slug}
                                disabled
                                className="bg-base-200 text-base-content/60"
                            />
                        </FormField>

                        <FormField label="Texto del Botón" htmlFor="boton" error={errors.boton} required>
                            <Input
                                id="boton"
                                value={data.boton}
                                onChange={(e) => setData('boton', e.target.value)}
                                placeholder="Texto que aparecerá en el botón"
                            />
                        </FormField>

                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion}>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripción breve (opcional)"
                            />
                        </FormField>

                        <FormField
                            label="URL Externa"
                            htmlFor="url_externa"
                            error={errors.url_externa}
                            description="Si se proporciona, el botón abrirá esta URL en una nueva pestaña en lugar del visor PDF."
                        >
                            <Input
                                id="url_externa"
                                value={data.url_externa}
                                onChange={(e) => setData('url_externa', e.target.value)}
                                placeholder="https://ejemplo.com/documento"
                            />
                        </FormField>

                        {!isUrlExterna && (
                            <FormField
                                label="Archivo PDF"
                                htmlFor="file"
                                error={errors.file}
                                description={seccion.media ? `Archivo actual: ${seccion.media.path.split('/').pop()}` : undefined}
                            >
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".pdf"
                                    onChange={handleFileChange}
                                    className="file:btn file:btn-sm file:btn-ghost"
                                />
                            </FormField>
                        )}

                        <FormField label="" htmlFor="activo" error={errors.activo}>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <Checkbox
                                    id="activo"
                                    checked={data.activo}
                                    onCheckedChange={(checked) => setData('activo', !!checked)}
                                />
                                <span>Activo</span>
                            </label>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/intra/secciones">Cancelar</Link>
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
