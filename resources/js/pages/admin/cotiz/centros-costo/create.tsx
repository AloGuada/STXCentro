import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/centros-costo' },
    { title: 'Centros de costo', href: '/admin/cotiz/centros-costo' },
    { title: 'Nuevo', href: '/admin/cotiz/centros-costo/create' },
];

export default function CentrosCostoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        cod_coste: '',
        concepto: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/cotiz/centros-costo');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo centro de costo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo centro de costo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Código de coste" htmlFor="cod_coste" error={errors.cod_coste} required>
                            <Input id="cod_coste" value={data.cod_coste} onChange={(e) => setData('cod_coste', e.target.value)} />
                        </FormField>

                        <FormField label="Concepto" htmlFor="concepto" error={errors.concepto} required>
                            <Input id="concepto" value={data.concepto} onChange={(e) => setData('concepto', e.target.value)} />
                        </FormField>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/centros-costo">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
