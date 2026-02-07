import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Tag } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tag: Tag;
};

export default function TagsEdit({ tag }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Tags', href: '/admin/tags' },
        { title: tag.name, href: `/admin/tags/${tag.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: tag.name,
        color: tag.color ?? '#3b82f6',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/tags/${tag.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tag.name}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Tag</h1>
                        <DeleteDialog
                            title="Eliminar tag"
                            description={`¿Estas seguro de eliminar el tag "${tag.name}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/tags/${tag.id}`}
                        />
                    </div>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="name" error={errors.name} required>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Nombre del tag"
                            />
                        </FormField>

                        <FormField label="Color" htmlFor="color" error={errors.color}>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="color"
                                    type="color"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="h-10 w-16 cursor-pointer p-1"
                                />
                                <Input
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    placeholder="#000000"
                                    className="flex-1"
                                />
                            </div>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/tags">Cancelar</Link>
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
