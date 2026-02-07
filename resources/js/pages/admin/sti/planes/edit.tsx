import { FormField } from '@/components/form';
import { ChecklistManager } from '@/components/sti/checklist-manager';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiEquipo, StiPlan } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarIcon, Loader2Icon, TrashIcon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Planes', href: '/admin/sti/planes' },
    { title: 'Editar Plan', href: '#' },
];

type Props = {
    plan: StiPlan;
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
};

export default function PlanesEdit({ plan, equipos }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: plan.descripcion,
        periodicidad: plan.periodicidad.toString(),
        fecha_inicial: plan.fecha_inicial.split('T')[0],
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

    const handleGenerarAnio = (year: number) => {
        router.post(`/admin/sti/planes/${plan.id}/generar-anio/${year}`, {}, { preserveScroll: true });
    };

    const currentYear = new Date().getFullYear();

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
                            <FormField label="Equipo" htmlFor="equipo">
                                <Input id="equipo" value={plan.equipo?.descripcion ?? ''} disabled className="bg-gray-50" />
                                <p className="mt-1 text-xs text-gray-500">El equipo no se puede cambiar despues de creado el plan.</p>
                            </FormField>

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

                            <FormField label="Fecha Inicial" htmlFor="fecha_inicial" error={errors.fecha_inicial} required>
                                <Input
                                    id="fecha_inicial"
                                    type="date"
                                    value={data.fecha_inicial}
                                    onChange={(e) => setData('fecha_inicial', e.target.value)}
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

                        <div className="mt-6 border-t pt-6">
                            <h2 className="mb-4 text-lg font-medium">Generar Mantenimientos</h2>
                            <div className="flex flex-wrap gap-2">
                                {[currentYear, currentYear + 1].map((year) => (
                                    <Button key={year} variant="outline" size="sm" onClick={() => handleGenerarAnio(year)}>
                                        <CalendarIcon className="mr-1 size-4" />
                                        Generar {year}
                                    </Button>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-gray-500">
                                Solo se generaran mantenimientos que no existan. Los mantenimientos existentes no se duplicaran.
                            </p>
                        </div>
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
                                            <span>{new Date(mant.fecha_programada).toLocaleDateString('es-MX')}</span>
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
