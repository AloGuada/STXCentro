import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobTipoRetencion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tipoRetencion: CobTipoRetencion;
};

export default function TiposRetencionesEdit({ tipoRetencion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cobranza', href: '/admin/cob/dashboard' },
        { title: 'Tipos Retenciones', href: '/admin/cob/tipos-retenciones' },
        { title: tipoRetencion.nombre, href: `/admin/cob/tipos-retenciones/${tipoRetencion.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: tipoRetencion.nombre,
        descripcion: tipoRetencion.descripcion ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cob/tipos-retenciones/${tipoRetencion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tipoRetencion.nombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Tipo de Retencion</h1>
                        <DeleteDialog
                            title="Eliminar tipo de retencion"
                            description={`¿Estas seguro de eliminar "${tipoRetencion.nombre}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/cob/tipos-retenciones/${tipoRetencion.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                placeholder="Nombre del tipo de retencion"
                            />
                        </FormField>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion}>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del tipo de retencion"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/cob/tipos-retenciones">Cancelar</Link>
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
