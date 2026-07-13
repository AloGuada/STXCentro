import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhPermisoAusencia } from '@/types/models';

type Props = {
    permiso: RhPermisoAusencia;
};

export default function PermisoAusenciaEdit({ permiso }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Permisos de Ausencia', href: '/admin/rh/permisos-ausencia' },
        { title: permiso.folio ?? `Permiso #${permiso.id}`, href: `/admin/rh/permisos-ausencia/${permiso.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombres: permiso.nombres,
        apellidos: permiso.apellidos,
        numero_empleado: permiso.numero_empleado ?? '',
        departamento: permiso.departamento ?? '',
        gerente: permiso.gerente ?? '',
        tipo: permiso.tipo ?? '',
        modalidad: permiso.modalidad ?? '',
        razon: permiso.razon ?? '',
        fecha_permiso: permiso.fecha_permiso ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/rh/permisos-ausencia/${permiso.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Permiso - ${permiso.nombres} ${permiso.apellidos}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Permiso de Ausencia</h1>
                        <DeleteDialog
                            title="Eliminar permiso"
                            description={`¿Estas seguro de eliminar el permiso de "${permiso.nombres} ${permiso.apellidos}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/rh/permisos-ausencia/${permiso.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nombres" htmlFor="nombres" error={errors.nombres} required>
                                <Input id="nombres" value={data.nombres} onChange={(e) => setData('nombres', e.target.value)} placeholder="Nombres" />
                            </FormField>

                            <FormField label="Apellidos" htmlFor="apellidos" error={errors.apellidos} required>
                                <Input id="apellidos" value={data.apellidos} onChange={(e) => setData('apellidos', e.target.value)} placeholder="Apellidos" />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Numero de Empleado" htmlFor="numero_empleado" error={errors.numero_empleado}>
                                <Input id="numero_empleado" value={data.numero_empleado} onChange={(e) => setData('numero_empleado', e.target.value)} placeholder="Numero de empleado" />
                            </FormField>

                            <FormField label="Departamento" htmlFor="departamento" error={errors.departamento}>
                                <Input id="departamento" value={data.departamento} onChange={(e) => setData('departamento', e.target.value)} placeholder="Departamento" />
                            </FormField>
                        </div>

                        <FormField label="Gerente" htmlFor="gerente" error={errors.gerente}>
                            <Input id="gerente" value={data.gerente} onChange={(e) => setData('gerente', e.target.value)} placeholder="Nombre del gerente" />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tipo" htmlFor="tipo" error={errors.tipo}>
                                <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)} placeholder="Seleccionar tipo">
                                    <SelectItem value="personal">Personal</SelectItem>
                                    <SelectItem value="medico">Medico</SelectItem>
                                    <SelectItem value="vacaciones">Vacaciones</SelectItem>
                                    <SelectItem value="maternidad">Maternidad</SelectItem>
                                    <SelectItem value="paternidad">Paternidad</SelectItem>
                                    <SelectItem value="otro">Otro</SelectItem>
                                </Select>
                            </FormField>

                            <FormField label="Modalidad" htmlFor="modalidad" error={errors.modalidad}>
                                <Select value={data.modalidad} onValueChange={(v) => setData('modalidad', v)} placeholder="Seleccionar modalidad">
                                    <SelectItem value="con_goce">Con goce de sueldo</SelectItem>
                                    <SelectItem value="sin_goce">Sin goce de sueldo</SelectItem>
                                    <SelectItem value="a_cuenta_vacaciones">A cuenta de vacaciones</SelectItem>
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Razon" htmlFor="razon" error={errors.razon}>
                            <textarea id="razon" className="textarea textarea-bordered w-full" value={data.razon} onChange={(e) => setData('razon', e.target.value)} placeholder="Razon del permiso" />
                        </FormField>

                        <FormField label="Fecha del Permiso" htmlFor="fecha_permiso" error={errors.fecha_permiso}>
                            <Input id="fecha_permiso" type="date" value={data.fecha_permiso} onChange={(e) => setData('fecha_permiso', e.target.value)} />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/permisos-ausencia">Cancelar</Link>
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
