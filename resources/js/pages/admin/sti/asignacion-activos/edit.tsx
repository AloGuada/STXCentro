import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { ImageUpload } from '@/components/sti/image-upload';
import { SignaturePad } from '@/components/sti/signature-pad';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, Media, StiAsignacionActivo, StiEquipo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileTextIcon, Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    asignacion: StiAsignacionActivo & { media: Media[] };
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
};

export default function AsignacionActivosEdit({ asignacion, departamentos, equipos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Asignaciones', href: '/admin/sti/asignacion-activos' },
        { title: `Asignacion #${asignacion.id}`, href: `/admin/sti/asignacion-activos/${asignacion.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        departamento_id: asignacion.departamento_id.toString(),
        equipo_id: asignacion.equipo_id.toString(),
        no_empleado: asignacion.no_empleado,
        empleado: asignacion.empleado,
        firma_empleado: asignacion.firma_empleado,
        no_ti: asignacion.no_ti,
        nombre_ti: asignacion.nombre_ti,
        firma_ti: asignacion.firma_ti,
        fecha_inicial: asignacion.fecha_inicial.split('T')[0],
        fecha_termino: asignacion.fecha_termino?.split('T')[0] ?? null,
        estado: asignacion.estado,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/asignacion-activos/${asignacion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Asignacion #${asignacion.id}`} />

            <div className="space-y-6 p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Asignacion #{asignacion.id}</h1>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" asChild>
                                <a href={`/admin/sti/asignacion-activos/${asignacion.id}/pdf`} target="_blank" rel="noopener noreferrer">
                                    <FileTextIcon className="size-4" />
                                    Generar PDF
                                </a>
                            </Button>
                            <DeleteDialog
                                title="Eliminar asignacion"
                                description={`Estas seguro de eliminar la asignacion #${asignacion.id}? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/sti/asignacion-activos/${asignacion.id}`}
                            />
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid gap-4 md:grid-cols-2">
                            <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                                <Select
                                    id="departamento_id"
                                    value={data.departamento_id}
                                    onValueChange={(value) => setData('departamento_id', value)}
                                >
                                    <option value="">Seleccionar departamento</option>
                                    {departamentos.map((depto) => (
                                        <option key={depto.id} value={depto.id}>
                                            {depto.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Equipo" htmlFor="equipo_id" error={errors.equipo_id} required>
                                <Select
                                    id="equipo_id"
                                    value={data.equipo_id}
                                    onValueChange={(value) => setData('equipo_id', value)}
                                >
                                    <option value="">Seleccionar equipo</option>
                                    {equipos.map((equipo) => (
                                        <option key={equipo.id} value={equipo.id}>
                                            {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h3 className="mb-4 font-medium">Datos del Empleado</h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField label="No. Empleado" htmlFor="no_empleado" error={errors.no_empleado} required>
                                    <Input
                                        id="no_empleado"
                                        value={data.no_empleado}
                                        onChange={(e) => setData('no_empleado', e.target.value)}
                                        placeholder="Numero de empleado"
                                    />
                                </FormField>

                                <FormField label="Nombre del Empleado" htmlFor="empleado" error={errors.empleado} required>
                                    <Input
                                        id="empleado"
                                        value={data.empleado}
                                        onChange={(e) => setData('empleado', e.target.value)}
                                        placeholder="Nombre completo"
                                    />
                                </FormField>
                            </div>

                            <div className="mt-4">
                                <FormField label="Firma del Empleado" htmlFor="firma_empleado" error={errors.firma_empleado}>
                                    <SignaturePad
                                        value={data.firma_empleado}
                                        onChange={(value) => setData('firma_empleado', value)}
                                        width={380}
                                        height={150}
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h3 className="mb-4 font-medium">Datos del Personal TI</h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField label="No. TI" htmlFor="no_ti" error={errors.no_ti} required>
                                    <Input
                                        id="no_ti"
                                        value={data.no_ti}
                                        onChange={(e) => setData('no_ti', e.target.value)}
                                        placeholder="Numero de TI"
                                    />
                                </FormField>

                                <FormField label="Nombre TI" htmlFor="nombre_ti" error={errors.nombre_ti} required>
                                    <Input
                                        id="nombre_ti"
                                        value={data.nombre_ti}
                                        onChange={(e) => setData('nombre_ti', e.target.value)}
                                        placeholder="Nombre del personal TI"
                                    />
                                </FormField>
                            </div>

                            <div className="mt-4">
                                <FormField label="Firma del Personal TI" htmlFor="firma_ti" error={errors.firma_ti}>
                                    <SignaturePad
                                        value={data.firma_ti}
                                        onChange={(value) => setData('firma_ti', value)}
                                        width={380}
                                        height={150}
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <FormField label="Fecha Inicial" htmlFor="fecha_inicial" error={errors.fecha_inicial} required>
                                <Input
                                    id="fecha_inicial"
                                    type="date"
                                    value={data.fecha_inicial}
                                    onChange={(e) => setData('fecha_inicial', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Fecha Termino" htmlFor="fecha_termino" error={errors.fecha_termino}>
                                <Input
                                    id="fecha_termino"
                                    type="date"
                                    value={data.fecha_termino ?? ''}
                                    onChange={(e) => setData('fecha_termino', e.target.value || null)}
                                />
                            </FormField>

                            <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                                <Select
                                    id="estado"
                                    value={data.estado}
                                    onValueChange={(value) => setData('estado', value)}
                                >
                                    <option value="activo">Activo</option>
                                    <option value="devuelto">Devuelto</option>
                                    <option value="transferido">Transferido</option>
                                </Select>
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/asignacion-activos">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>

                {/* Archivos */}
                <div className="w-3/4">
                    <h2 className="mb-4 text-lg font-semibold">Archivos Adjuntos</h2>
                    <ImageUpload
                        media={asignacion.media}
                        storeUrl={`/admin/sti/asignacion-activos/${asignacion.id}/media`}
                        destroyUrlPrefix={`/admin/sti/asignacion-activos/${asignacion.id}/media`}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
