import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiItemTipo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tipo: StiItemTipo;
};

export default function ItemsTiposEdit({ tipo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Tipos de Item', href: '/admin/sti/items-tipos' },
        { title: tipo.descripcion, href: `/admin/sti/items-tipos/${tipo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tipo.descripcion,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/items-tipos/${tipo.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tipo.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Tipo de Item</h1>
                        <DeleteDialog
                            title="Eliminar tipo"
                            description={`¿Estas seguro de eliminar el tipo "${tipo.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/sti/items-tipos/${tipo.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Nombre del tipo de item"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/items-tipos">Cancelar</Link>
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
