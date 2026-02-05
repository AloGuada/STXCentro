import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Estados', href: '/admin/sti/status' },
    { title: 'Nuevo Estado', href: '/admin/sti/status/create' },
];

export default function StatusCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        orden: 0,
        detiene_tiempo: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/status');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Estado" />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Nuevo Estado</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre del estado"
                                />
                            </FormField>

                            <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                                <Input
                                    id="orden"
                                    type="number"
                                    min={0}
                                    value={data.orden}
                                    onChange={(e) => setData('orden', parseInt(e.target.value) || 0)}
                                    placeholder="Orden de visualización"
                                />
                            </FormField>

                            <FormField label="" htmlFor="detiene_tiempo" error={errors.detiene_tiempo}>
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <Checkbox
                                        id="detiene_tiempo"
                                        checked={data.detiene_tiempo}
                                        onCheckedChange={(checked) => setData('detiene_tiempo', !!checked)}
                                    />
                                    <span>Detiene tiempo (pausa el contador de tiempo del ticket)</span>
                                </label>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/status">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
