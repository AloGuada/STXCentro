import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CostosTipoRubro } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tipoRubro: CostosTipoRubro;
};

export default function TipoRubrosEdit({ tipoRubro }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Costos', href: '/admin/costos/tipo-rubros' },
        { title: 'Tipos de Centro de Costos', href: '/admin/costos/tipo-rubros' },
        { title: tipoRubro.descripcion, href: `/admin/costos/tipo-rubros/${tipoRubro.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tipoRubro.descripcion,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/costos/tipo-rubros/${tipoRubro.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tipoRubro.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Tipo de Centro de Costos</h1>
                        <DeleteDialog
                            title="Eliminar tipo de centro de costos"
                            description={`¿Estás seguro de eliminar "${tipoRubro.descripcion}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/costos/tipo-rubros/${tipoRubro.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/costos/tipo-rubros">Cancelar</Link>
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
