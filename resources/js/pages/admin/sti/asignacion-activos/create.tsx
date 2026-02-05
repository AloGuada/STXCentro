import { FormField } from '@/components/form';
import { SignaturePad } from '@/components/sti/signature-pad';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, StiEquipo } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Asignaciones', href: '/admin/sti/asignacion-activos' },
    { title: 'Nueva Asignacion', href: '/admin/sti/asignacion-activos/create' },
];

type Props = {
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
};

export default function AsignacionActivosCreate({ departamentos, equipos }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        departamento_id: '',
        equipo_id: '',
        no_empleado: '',
        empleado: '',
        firma_empleado: '' as string | null,
        no_ti: '',
        nombre_ti: '',
        firma_ti: '' as string | null,
        fecha_inicial: new Date().toISOString().split('T')[0],
        fecha_termino: '' as string | null,
        estado: 'activo',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/asignacion-activos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Asignacion de Activo" />

            <div className="mx-auto max-w-4xl p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Nueva Asignacion de Activo</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
