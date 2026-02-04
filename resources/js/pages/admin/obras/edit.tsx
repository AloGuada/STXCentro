import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    obra: Obra;
};

export default function ObrasEdit({ obra }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Obras', href: '/admin/obras' },
        { title: obra.no, href: `/admin/obras/${obra.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        no: obra.no,
        descripcion: obra.descripcion,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/obras/${obra.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${obra.no}`} />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Obra</CardTitle>
                        <DeleteDialog
                            title="Eliminar obra"
                            description={`¿Estás seguro de eliminar la obra "${obra.no}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/obras/${obra.id}`}
                        />
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Número" htmlFor="no" error={errors.no} required>
                                <Input
                                    id="no"
                                    value={data.no}
                                    onChange={(e) => setData('no', e.target.value)}
                                    placeholder="Ej: OBR-001"
                                />
                            </FormField>

                            <FormField
                                label="Descripción"
                                htmlFor="descripcion"
                                error={errors.descripcion}
                                required
                            >
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Descripción de la obra"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/obras">Cancelar</Link>
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
