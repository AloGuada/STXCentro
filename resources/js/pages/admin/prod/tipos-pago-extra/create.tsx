import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Tipos Pago Extra', href: '/admin/prod/tipos-pago-extra' },
    { title: 'Nuevo', href: '/admin/prod/tipos-pago-extra/create' },
];

export default function TiposPagoExtraCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        orden: '0',
        desgloce: '0',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/tipos-pago-extra');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Tipo Pago Extra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Tipo de Pago Extra</h1>

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

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/tipos-pago-extra">Cancelar</Link>
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
