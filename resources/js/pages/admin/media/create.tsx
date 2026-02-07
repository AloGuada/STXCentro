import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, UploadIcon } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Media', href: '/admin/media' },
    { title: 'Subir Archivo', href: '/admin/media/create' },
];

export default function MediaCreate() {
    const { data, setData, post, processing, errors, progress } = useForm<{
        descripcion: string;
        file: File | null;
    }>({
        descripcion: '',
        file: null,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/media', {
            forceFormData: true,
        });
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('file', file);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Subir Archivo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Subir Archivo</h1>
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
                            label="Archivo"
                            htmlFor="file"
                            error={errors.file}
                            description="Tamano maximo: 10MB"
                            required
                        >
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="file"
                                        type="file"
                                        onChange={handleFileChange}
                                        className="cursor-pointer"
                                    />
                                </div>
                                {data.file && (
                                    <p className="text-muted-foreground text-sm">
                                        Archivo seleccionado: {data.file.name}
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
                                {processing ? (
                                    <Loader2Icon className="size-4 animate-spin" />
                                ) : (
                                    <UploadIcon className="size-4" />
                                )}
                                Subir
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
