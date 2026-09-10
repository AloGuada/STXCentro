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
    { title: 'Tipos Pago Extra', href: '/admin/prod/tipos-pago-extra' },
    { title: 'Nuevo', href: '/admin/prod/tipos-pago-extra/create' },
];

export default function TiposPagoExtraCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        orden: '0',
        desgloce: false,
        es_descuento: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/tipos-pago-extra');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Tipo Pago Extra" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo tipo de pago extra</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                            />
                        </FormField>

                        <FormField label="Orden" htmlFor="orden" error={errors.orden} required>
                            <Input
                                id="orden"
                                type="number"
                                min="0"
                                value={data.orden}
                                onChange={(e) => setData('orden', e.target.value)}
                                error={!!errors.orden}
                            />
                        </FormField>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={data.desgloce}
                                onChange={(e) => setData('desgloce', e.target.checked)}
                            />
                            <span className="text-sm">Desgloce</span>
                        </label>

                        <label className="flex cursor-pointer items-start gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm mt-0.5"
                                checked={data.es_descuento}
                                onChange={(e) => setData('es_descuento', e.target.checked)}
                            />
                            <span className="text-sm">
                                Es descuento
                                <span className="block text-xs text-base-content/60">
                                    El importe resta del total del grupo (el precio se sigue capturando en positivo).
                                </span>
                            </span>
                        </label>

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
