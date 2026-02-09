import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Planes', href: '/admin/sti/planes' },
    { title: 'Nuevo Plan', href: '/admin/sti/planes/create' },
];

export default function PlanesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        descripcion: '',
        periodicidad: '',
        activo: true,
        checks: [] as { descripcion: string }[],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/planes');
    };

    const addCheck = () => {
        setData('checks', [...data.checks, { descripcion: '' }]);
    };

    const removeCheck = (index: number) => {
        setData(
            'checks',
            data.checks.filter((_, i) => i !== index),
        );
    };

    const updateCheck = (index: number, descripcion: string) => {
        const newChecks = [...data.checks];
        newChecks[index] = { descripcion };
        setData('checks', newChecks);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Plan de Mantenimiento" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Plan de Mantenimiento</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del plan de mantenimiento"
                            />
                        </FormField>

                        <FormField label="Periodicidad (dias)" htmlFor="periodicidad" error={errors.periodicidad} required>
                            <Input
                                id="periodicidad"
                                type="number"
                                min="1"
                                value={data.periodicidad}
                                onChange={(e) => setData('periodicidad', e.target.value)}
                                placeholder="Ej: 30, 60, 90"
                            />
                            <p className="mt-1 text-xs text-gray-500">Cada cuantos dias se generara un mantenimiento automaticamente.</p>
                        </FormField>

                        <FormField label="Activo" htmlFor="activo" error={errors.activo}>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="activo"
                                    className="checkbox"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                />
                                <span className="text-sm">Plan activo (genera mantenimientos automaticamente)</span>
                            </label>
                        </FormField>

                        <div className="border-t pt-4">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-medium">Checklist Inicial</h2>
                                <Button type="button" variant="outline" size="sm" onClick={addCheck}>
                                    <PlusIcon className="mr-1 size-4" />
                                    Agregar Check
                                </Button>
                            </div>

                            {data.checks.length === 0 ? (
                                <p className="text-sm text-gray-500">
                                    No hay checks agregados. Puedes agregarlos ahora o despues de crear el plan.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {data.checks.map((check, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            <span className="w-8 text-center text-sm text-gray-500">{index + 1}.</span>
                                            <Input
                                                value={check.descripcion}
                                                onChange={(e) => updateCheck(index, e.target.value)}
                                                placeholder="Descripcion del check"
                                                className="flex-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeCheck(index)}
                                                className="text-red-500 hover:bg-red-50 hover:text-red-600"
                                            >
                                                <TrashIcon className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end gap-2 border-t pt-4">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/planes">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Crear Plan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
