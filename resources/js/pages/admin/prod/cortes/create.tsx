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
    { title: 'Produccion', href: '/admin/prod/cortes' },
    { title: 'Cortes', href: '/admin/prod/cortes' },
    { title: 'Nuevo Corte', href: '/admin/prod/cortes/create' },
];

export default function CortesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        semana: '',
        fecha_inicio: '',
        fecha_fin: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/cortes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Corte" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Corte</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Semana" htmlFor="semana" error={errors.semana} required>
                            <Input
                                id="semana"
                                type="number"
                                min="1"
                                max="53"
                                value={data.semana}
                                onChange={(e) => setData('semana', e.target.value)}
                                placeholder="Numero de semana"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Fecha Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio} required>
                                <Input
                                    id="fecha_inicio"
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={(e) => setData('fecha_inicio', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Fecha Fin" htmlFor="fecha_fin" error={errors.fecha_fin} required>
                                <Input
                                    id="fecha_fin"
                                    type="date"
                                    value={data.fecha_fin}
                                    onChange={(e) => setData('fecha_fin', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/cortes">Cancelar</Link>
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
