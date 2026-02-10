import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdTipo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tipo: ProdTipo;
};

export default function TiposEdit({ tipo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Tipos Pago', href: '/admin/prod/tipos' },
        { title: tipo.descripcion, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tipo.descripcion,
        orden: tipo.orden.toString(),
        desgloce: tipo.desgloce,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/tipos/${tipo.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Tipo: ${tipo.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Tipo de Pago</h1>
                        <DeleteDialog
                            title="Eliminar tipo"
                            description={`Eliminar el tipo "${tipo.descripcion}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/prod/tipos/${tipo.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del tipo de pago"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input
                                    id="orden"
                                    type="number"
                                    min="0"
                                    value={data.orden}
                                    onChange={(e) => setData('orden', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Desgloce" htmlFor="desgloce" error={errors.desgloce}>
                                <label className="flex cursor-pointer items-center gap-2">
                                    <input
                                        id="desgloce"
                                        type="checkbox"
                                        className="checkbox"
                                        checked={data.desgloce}
                                        onChange={(e) => setData('desgloce', e.target.checked)}
                                    />
                                    <span className="text-sm">Activar desgloce</span>
                                </label>
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/tipos">Cancelar</Link>
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
