import { CheckEjecucionList } from '@/components/sti/check-ejecucion-list';
import { CostosManager } from '@/components/sti/costos-manager';
import { ImageUpload } from '@/components/sti/image-upload';
import { ScheduleNextModal } from '@/components/sti/schedule-next-modal';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Media, StiCheckEjecucion, StiCostoMantenimiento, StiEquipo, StiMantenimiento, StiPlan, StiTecnico } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircleIcon, Loader2Icon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    mantenimiento: StiMantenimiento & {
        equipo: StiEquipo;
        media: Media[];
        costos: StiCostoMantenimiento[];
        plan?: StiPlan;
        check_ejecuciones?: StiCheckEjecucion[];
    };
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
    tecnicos: Pick<StiTecnico, 'id' | 'descripcion'>[];
};

export default function MantenimientosEdit({ mantenimiento, equipos, tecnicos }: Props) {
    const [showScheduleModal, setShowScheduleModal] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
        { title: `Mantenimiento #${mantenimiento.id}`, href: `/admin/sti/mantenimientos/${mantenimiento.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        equipo_id: mantenimiento.equipo_id.toString(),
        fecha_programada: mantenimiento.fecha_programada.split('T')[0],
        descripcion: mantenimiento.descripcion ?? '',
        tecnico_id: mantenimiento.tecnico_id?.toString() ?? '',
    });

    const checkEjecuciones = mantenimiento.check_ejecuciones ?? [];
    const checksCompletados = checkEjecuciones.filter((ce) => ce.resultado).length;
    const totalChecks = checkEjecuciones.length;
    const todosChecksCompletos = totalChecks === 0 || checksCompletados === totalChecks;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/mantenimientos/${mantenimiento.id}`);
    };

    const selectedEquipo = equipos.find((e) => e.id.toString() === data.equipo_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Mantenimiento #${mantenimiento.id}`} />

            <div className="space-y-6 p-6">
                {/* Estado del mantenimiento */}
                <div className="w-3/4">
                    <div className="flex items-center gap-4">
                        <h1 className="text-2xl font-semibold">Mantenimiento #{mantenimiento.id}</h1>
                        {mantenimiento.status === 'realizado' ? (
                            <span className="badge badge-success gap-1">
                                <CheckCircleIcon className="size-4" />
                                Realizado
                            </span>
                        ) : (
                            <span className="badge badge-warning">Pendiente</span>
                        )}
                    </div>

                    {mantenimiento.plan && (
                        <p className="mt-1 text-sm text-gray-500">
                            Plan:{' '}
                            <Link href={`/admin/sti/planes/${mantenimiento.plan.id}/edit`} className="text-blue-600 hover:underline">
                                {mantenimiento.plan.descripcion}
                            </Link>
                        </p>
                    )}

                    {mantenimiento.status === 'realizado' ? (
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Este mantenimiento fue realizado el{' '}
                            <strong>
                                {new Date(mantenimiento.fecha_realizado!.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </strong>
                        </p>
                    ) : (
                        <div className="mt-2 flex items-center gap-4">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Este mantenimiento esta pendiente de realizarse.
                            </p>
                            <Button onClick={() => setShowScheduleModal(true)} disabled={!todosChecksCompletos}>
                                <CheckCircleIcon className="size-4" />
                                Marcar como Realizado
                            </Button>
                            {!todosChecksCompletos && (
                                <span className="text-sm text-orange-600">
                                    Completa todos los checks ({checksCompletados}/{totalChecks})
                                </span>
                            )}
                        </div>
                    )}
                </div>

                {/* Formulario principal */}
                <div className="w-3/4">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Editar Mantenimiento</h2>
                        <DeleteDialog
                            title="Eliminar mantenimiento"
                            description={`¿Estas seguro de eliminar el mantenimiento #${mantenimiento.id}? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/sti/mantenimientos/${mantenimiento.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Equipo" htmlFor="equipo_id" error={errors.equipo_id} required>
                            <Select
                                id="equipo_id"
                                value={data.equipo_id}
                                onValueChange={(value) => setData('equipo_id', value)}
                                disabled={mantenimiento.status === 'realizado'}
                            >
                                <option value="">Seleccionar equipo</option>
                                {equipos.map((equipo) => (
                                    <option key={equipo.id} value={equipo.id}>
                                        {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Fecha Programada" htmlFor="fecha_programada" error={errors.fecha_programada} required>
                            <Input
                                id="fecha_programada"
                                type="date"
                                value={data.fecha_programada}
                                onChange={(e) => setData('fecha_programada', e.target.value)}
                                disabled={mantenimiento.status === 'realizado'}
                            />
                        </FormField>

                        <FormField label="Tecnico" htmlFor="tecnico_id" error={errors.tecnico_id} required>
                            <Select
                                id="tecnico_id"
                                value={data.tecnico_id}
                                onValueChange={(value) => setData('tecnico_id', value)}
                            >
                                <option value="">Seleccionar tecnico</option>
                                {tecnicos.map((tecnico) => (
                                    <option key={tecnico.id} value={tecnico.id}>
                                        {tecnico.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </FormField>

                        <FormField label="Descripcion (opcional)" htmlFor="descripcion" error={errors.descripcion}>
                            <textarea
                                id="descripcion"
                                className="textarea textarea-bordered min-h-24 w-full"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                placeholder="Descripcion del mantenimiento"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/mantenimientos">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Checklist */}
                {checkEjecuciones.length > 0 && (
                    <div className="w-3/4">
                        <CheckEjecucionList
                            mantenimientoId={mantenimiento.id}
                            checkEjecuciones={checkEjecuciones}
                            readonly={mantenimiento.status === 'realizado'}
                        />
                    </div>
                )}

                {/* Imagenes */}
                <div className="w-3/4">
                    <h2 className="mb-4 text-lg font-semibold">Imagenes</h2>
                    <ImageUpload
                        media={mantenimiento.media}
                        storeUrl={`/admin/sti/mantenimientos/${mantenimiento.id}/media`}
                        destroyUrlPrefix={`/admin/sti/mantenimientos/${mantenimiento.id}/media`}
                    />
                </div>

                {/* Costos */}
                <div className="w-3/4">
                    <h2 className="mb-4 text-lg font-semibold">Costos</h2>
                    <CostosManager
                        costos={mantenimiento.costos}
                        storeUrl={`/admin/sti/mantenimientos/${mantenimiento.id}/costos`}
                        destroyUrlPrefix={`/admin/sti/mantenimientos/${mantenimiento.id}/costos`}
                    />
                </div>
            </div>

            {/* Modal para marcar como realizado */}
            <ScheduleNextModal
                open={showScheduleModal}
                onClose={() => setShowScheduleModal(false)}
                equipo={selectedEquipo ?? mantenimiento.equipo}
                mantenimientoId={mantenimiento.id}
                plan={mantenimiento.plan}
            />
        </AppLayout>
    );
}
