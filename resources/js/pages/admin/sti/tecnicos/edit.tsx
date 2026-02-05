import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiTecnico } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tecnico: StiTecnico;
};

export default function TecnicosEdit({ tecnico }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Tecnicos', href: '/admin/sti/tecnicos' },
        { title: tecnico.descripcion, href: `/admin/sti/tecnicos/${tecnico.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tecnico.descripcion,
        activo: tecnico.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/tecnicos/${tecnico.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tecnico.descripcion}`} />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Tecnico</CardTitle>
                        <DeleteDialog
                            title="Eliminar tecnico"
                            description={`¿Estas seguro de eliminar al tecnico "${tecnico.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/sti/tecnicos/${tecnico.id}`}
                        />
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre del tecnico"
                                />
                            </FormField>

                            <FormField label="Estado" htmlFor="activo">
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        className="toggle toggle-success"
                                        checked={data.activo}
                                        onChange={(e) => setData('activo', e.target.checked)}
                                    />
                                    <span className="text-sm">{data.activo ? 'Activo' : 'Inactivo'}</span>
                                </label>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/tecnicos">Cancelar</Link>
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
