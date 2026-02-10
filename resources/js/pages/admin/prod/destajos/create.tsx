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
    { title: 'Destajos', href: '/admin/prod/destajos' },
    { title: 'Nuevo Destajo', href: '/admin/prod/destajos/create' },
];

export default function DestajosCreate() {
    const { data, setData, post, processing, errors } = useForm({
        semana: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/destajos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Destajo" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Destajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Semana (numero)" htmlFor="semana" error={errors.semana} required>
                            <Input
                                id="semana"
                                type="number"
                                min="1"
                                max="53"
                                value={data.semana}
                                onChange={(e) => setData('semana', e.target.value)}
                                placeholder="Ej: 1, 2, 3..."
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/destajos">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Crear Destajo
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
