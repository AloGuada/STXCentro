import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosPermiso } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    permiso: CostosPermiso;
};

export default function PermisosEdit({ permiso }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/permisos' },
        { title: 'Niveles Aprobacion', href: '/admin/costos/permisos' },
        { title: permiso.descripcion, href: `/admin/costos/permisos/${permiso.id}` },
        { title: 'Editar', href: `/admin/costos/permisos/${permiso.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: permiso.descripcion,
        nivel: permiso.nivel,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/permisos/${permiso.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${permiso.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Nivel de Aprobacion</h1>
                        <DeleteDialog
                            title="Eliminar nivel"
                            description={`¿Estas seguro de eliminar "${permiso.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/costos/permisos/${permiso.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Ej: Jefe Depto, Gerente..."
                            />
                        </FormField>

                        <FormField label="Nivel" htmlFor="nivel" error={errors.nivel} required>
                            <Input
                                id="nivel"
                                type="number"
                                min={1}
                                value={data.nivel}
                                onChange={(e) => setData('nivel', parseInt(e.target.value) || 1)}
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/permisos">Cancelar</Link>
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
