import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProdTipoPagoExtra } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    tipo: ProdTipoPagoExtra;
};

export default function TiposPagoExtraEdit({ tipo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Tipos Pago Extra', href: '/admin/prod/tipos-pago-extra' },
        { title: tipo.descripcion, href: `/admin/prod/tipos-pago-extra/${tipo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: tipo.descripcion,
        orden: String(tipo.orden),
        desgloce: tipo.desgloce ? '1' : '0',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/tipos-pago-extra/${tipo.id}`);
    };

    const handleDelete = () => {
        if (confirm('Estas seguro de eliminar este tipo?')) {
            router.delete(`/admin/prod/tipos-pago-extra/${tipo.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${tipo.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar Tipo de Pago Extra</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Orden" htmlFor="orden" error={errors.orden} required>
                            <Input
                                id="orden"
                                type="number"
                                min="0"
                                value={data.orden}
                                onChange={(e) => setData('orden', e.target.value)}
                            />
                        </FormField>

                        <FormField label="Desgloce" htmlFor="desgloce" error={errors.desgloce} required>
                            <Select
                                id="desgloce"
                                value={data.desgloce}
                                onValueChange={(value) => setData('desgloce', value)}
                            >
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </Select>
                        </FormField>

                        <div className="flex justify-between">
                            <Button type="button" variant="destructive" onClick={handleDelete}>
                                Eliminar
                            </Button>
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/tipos-pago-extra">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
