import { FormField } from '@/components/form';
import { ChecklistManager } from '@/components/sti/checklist-manager';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiPlan } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Planes', href: '/admin/sti/planes' },
    { title: 'Editar Plan', href: '#' },
];

type Props = {
    plan: StiPlan;
};

export default function PlanesEdit({ plan }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: plan.descripcion,
        periodicidad: plan.periodicidad.toString(),
        activo: plan.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/planes/${plan.id}`);
    };

    const handleDelete = () => {
        if (!confirm('Eliminar este plan? Se eliminaran tambien los mantenimientos pendientes asociados.')) return;
        router.delete(`/admin/sti/planes/${plan.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Plan: ${plan.descripcion}`} />

            <div className="p-6">
                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Plan</h1>
                            <Button variant="destructive" size="sm" onClick={handleDelete}>
                                <TrashIcon className="mr-1 size-4" />
                                Eliminar
                            </Button>
                        </div>

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
                                />
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
                                    <span className="text-sm">Plan activo</span>
                                </label>
                            </FormField>

                            <div className="flex justify-end gap-2 border-t pt-4">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/planes">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <h2 className="mb-4 text-lg font-medium">Checklist</h2>
                        <ChecklistManager planId={plan.id} checks={plan.checks ?? []} />

                        <div className="mt-6 border-t pt-6">
                            <h2 className="mb-4 text-lg font-medium">Mantenimientos Programados</h2>
                            {plan.mantenimientos && plan.mantenimientos.length > 0 ? (
                                <div className="max-h-96 space-y-2 overflow-y-auto">
                                    {plan.mantenimientos.map((mant) => (
                                        <Link
                                            key={mant.id}
                                            href={`/admin/sti/mantenimientos/${mant.id}/edit`}
                                            className="flex items-center justify-between rounded-lg border p-3 transition hover:bg-gray-50"
                                        >
                                            <div>
                                                <span>{new Date(mant.fecha_programada.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX')}</span>
                                                {mant.equipo && (
                                                    <span className="ml-2 text-sm text-gray-500">{mant.equipo.descripcion}</span>
                                                )}
                                            </div>
                                            <span
                                                className={`badge badge-sm ${mant.status === 'realizado' ? 'badge-success' : 'badge-warning'}`}
                                            >
                                                {mant.status === 'realizado' ? 'Realizado' : 'Pendiente'}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">No hay mantenimientos programados para este plan.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
