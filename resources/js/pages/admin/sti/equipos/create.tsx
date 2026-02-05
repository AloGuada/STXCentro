import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Equipos', href: '/admin/sti/equipos' },
    { title: 'Nuevo Equipo', href: '/admin/sti/equipos/create' },
];

export default function EquiposCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        serie: '',
        marca: '',
        factor_criticidad: 'medio',
        periodicidad_mantenimiento: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/equipos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Equipo" />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Nuevo Equipo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre o descripcion del equipo"
                                />
                            </FormField>

                            <FormField label="Numero de Serie" htmlFor="serie" error={errors.serie}>
                                <Input
                                    id="serie"
                                    value={data.serie}
                                    onChange={(e) => setData('serie', e.target.value)}
                                    placeholder="Numero de serie"
                                />
                            </FormField>

                            <FormField label="Marca" htmlFor="marca" error={errors.marca}>
                                <Input
                                    id="marca"
                                    value={data.marca}
                                    onChange={(e) => setData('marca', e.target.value)}
                                    placeholder="Marca del equipo"
                                />
                            </FormField>

                            <FormField label="Factor de Criticidad" htmlFor="factor_criticidad" error={errors.factor_criticidad} required>
                                <Select
                                    id="factor_criticidad"
                                    value={data.factor_criticidad}
                                    onValueChange={(value) => setData('factor_criticidad', value)}
                                >
                                    <option value="bajo">Bajo</option>
                                    <option value="medio">Medio</option>
                                    <option value="alto">Alto</option>
                                    <option value="critico">Critico</option>
                                </Select>
                            </FormField>

                            <FormField
                                label="Periodicidad de Mantenimiento (dias)"
                                htmlFor="periodicidad_mantenimiento"
                                error={errors.periodicidad_mantenimiento}
                            >
                                <Input
                                    id="periodicidad_mantenimiento"
                                    type="number"
                                    min="1"
                                    value={data.periodicidad_mantenimiento}
                                    onChange={(e) => setData('periodicidad_mantenimiento', e.target.value)}
                                    placeholder="Ej: 30, 60, 90"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Cada cuantos dias se debe realizar mantenimiento preventivo. Dejar vacio si no aplica.
                                </p>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/equipos">Cancelar</Link>
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
