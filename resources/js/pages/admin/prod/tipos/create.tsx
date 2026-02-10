import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Tipos Pago', href: '/admin/prod/tipos' },
    { title: 'Nuevo Tipo', href: '/admin/prod/tipos/create' },
];

export default function TiposCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        orden: '0',
        desgloce: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/tipos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Tipo de Pago" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Tipo de Pago</h1>

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
