import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Media } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLinkIcon, Loader2Icon } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

type Props = {
    media: Media;
};

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

export default function MediaEdit({ media }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Media', href: '/admin/media' },
        { title: media.descripcion, href: `/admin/media/${media.id}/edit` },
    ];

    const { data, setData, post, processing, errors, progress } = useForm<{
        descripcion: string;
        file: File | null;
        _method: string;
    }>({
        descripcion: media.descripcion,
        file: null,
        _method: 'PUT',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/media/${media.id}`, {
            forceFormData: true,
        });
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('file', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${media.descripcion}`} />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Archivo</CardTitle>
                        <DeleteDialog
                            title="Eliminar archivo"
                            description={`¿Estas seguro de eliminar "${media.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/media/${media.id}`}
                        />
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 rounded-lg border p-4">
                            <h4 className="mb-2 font-medium">Archivo actual</h4>
                            <div className="text-muted-foreground space-y-1 text-sm">
                                <p>Tipo: {media.mime}</p>
                                <p>Tamano: {formatFileSize(media.size)}</p>
                                <a
                                    href={`/storage/${media.path}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                                >
                                    Ver archivo
                                    <ExternalLinkIcon className="size-3" />
                                </a>
                            </div>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField
                                label="Descripcion"
                                htmlFor="descripcion"
                                error={errors.descripcion}
                                required
                            >
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Descripcion del archivo"
                                />
                            </FormField>

                            <FormField
                                label="Reemplazar archivo"
                                htmlFor="file"
                                error={errors.file}
                                description="Dejar vacio para mantener el archivo actual"
                            >
                                <div className="space-y-2">
                                    <Input
                                        id="file"
                                        type="file"
                                        onChange={handleFileChange}
                                        className="cursor-pointer"
                                    />
                                    {data.file && (
                                        <p className="text-muted-foreground text-sm">
                                            Nuevo archivo: {data.file.name}
                                        </p>
                                    )}
                                    {progress && (
                                        <div className="h-2 w-full rounded-full bg-gray-200">
                                            <div
                                                className="h-2 rounded-full bg-blue-600 transition-all"
                                                style={{ width: `${progress.percentage}%` }}
                                            />
                                        </div>
                                    )}
                                </div>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/media">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
