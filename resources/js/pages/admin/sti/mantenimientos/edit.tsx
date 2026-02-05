import { CostosManager } from '@/components/sti/costos-manager';
import { ImageUpload } from '@/components/sti/image-upload';
import { ScheduleNextModal } from '@/components/sti/schedule-next-modal';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Media, StiCostoMantenimiento, StiEquipo, StiMantenimiento, StiTecnico } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircleIcon, Loader2Icon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    mantenimiento: StiMantenimiento & {
        equipo: StiEquipo;
        media: Media[];
        costos: StiCostoMantenimiento[];
    };
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie' | 'periodicidad_mantenimiento'>[];
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
        fecha_programada: mantenimiento.fecha_programada.slice(0, 10),
        descripcion: mantenimiento.descripcion ?? '',
        tecnico_id: mantenimiento.tecnico_id.toString(),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/mantenimientos/${mantenimiento.id}`);
    };

    const selectedEquipo = equipos.find((e) => e.id.toString() === data.equipo_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Mantenimiento #${mantenimiento.id}`} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                {/* Estado del mantenimiento */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            Estado del Mantenimiento
                            {mantenimiento.status === 'realizado' ? (
                                <span className="badge badge-success gap-1">
                                    <CheckCircleIcon className="size-4" />
                                    Realizado
                                </span>
                            ) : (
                                <span className="badge badge-warning">Pendiente</span>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {mantenimiento.status === 'realizado' ? (
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                <p>
                                    Este mantenimiento fue realizado el{' '}
                                    <strong>
                                        {new Date(mantenimiento.fecha_realizado!).toLocaleDateString('es-MX', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </strong>
                                </p>
                            </div>
                        ) : (
                            <div className="flex items-center gap-4">
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Este mantenimiento esta pendiente de realizarse.
                                </p>
                                <Button onClick={() => setShowScheduleModal(true)}>
                                    <CheckCircleIcon className="size-4" />
                                    Marcar como Realizado
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Formulario principal */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Mantenimiento</CardTitle>
                        <DeleteDialog
                            title="Eliminar mantenimiento"
                            description={`¿Estas seguro de eliminar el mantenimiento #${mantenimiento.id}? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/sti/mantenimientos/${mantenimiento.id}`}
                        />
                    </CardHeader>
                    <CardContent>
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
                    </CardContent>
                </Card>

                {/* Imagenes */}
                <Card>
                    <CardHeader>
                        <CardTitle>Imagenes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ImageUpload
                            media={mantenimiento.media}
                            storeUrl={`/admin/sti/mantenimientos/${mantenimiento.id}/media`}
                            destroyUrlPrefix={`/admin/sti/mantenimientos/${mantenimiento.id}/media`}
                        />
                    </CardContent>
                </Card>

                {/* Costos */}
                <Card>
                    <CardHeader>
                        <CardTitle>Costos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CostosManager
                            costos={mantenimiento.costos}
                            storeUrl={`/admin/sti/mantenimientos/${mantenimiento.id}/costos`}
                            destroyUrlPrefix={`/admin/sti/mantenimientos/${mantenimiento.id}/costos`}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Modal para agendar siguiente mantenimiento */}
            <ScheduleNextModal
                open={showScheduleModal}
                onClose={() => setShowScheduleModal(false)}
                equipo={selectedEquipo ?? mantenimiento.equipo}
                mantenimientoId={mantenimiento.id}
                fechaRealizado={new Date().toISOString().split('T')[0]}
            />
        </AppLayout>
    );
}
