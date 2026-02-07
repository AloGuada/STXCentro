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
    { title: 'Obras', href: '/admin/obras' },
    { title: 'Nueva Obra', href: '/admin/obras/create' },
];

export default function ObrasCreate() {
    const { data, setData, post, processing, errors } = useForm({
        no: '',
        descripcion: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/obras');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Obra" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Obra</h1>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Numero" htmlFor="no" error={errors.no} required>
                            <Input
                                id="no"
                                value={data.no}
                                onChange={(e) => setData('no', e.target.value)}
                                placeholder="Ej: OBR-001"
                            />
                        </FormField>

                        <FormField
                            label="Descripcion"
                            htmlFor="descripcion"
                            error={errors.descripcion}
                            required
                        >
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion de la obra"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/obras">Cancelar</Link>
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
