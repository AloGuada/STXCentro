import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, StiEquipo, StiStatus, StiTecnico } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Tickets', href: '/admin/sti/tickets' },
    { title: 'Nuevo Ticket', href: '/admin/sti/tickets/create' },
];

type Props = {
    tecnicos: Pick<StiTecnico, 'id' | 'descripcion'>[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    statuses: Pick<StiStatus, 'id' | 'descripcion'>[];
};

export default function TicketsCreate({ tecnicos, equipos, departamentos, statuses }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        nombre_solicitante: '',
        comentario: '',
        tecnico_id: '',
        equipo_id: '',
        departamento_id: '',
        status_id: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/sti/tickets');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo Ticket" />

            <div className="mx-auto max-w-2xl p-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Nuevo Ticket</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre del Solicitante" htmlFor="nombre_solicitante" error={errors.nombre_solicitante} required>
                                <Input
                                    id="nombre_solicitante"
                                    value={data.nombre_solicitante}
                                    onChange={(e) => setData('nombre_solicitante', e.target.value)}
                                    placeholder="Nombre de quien reporta"
                                />
                            </FormField>

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

                            <FormField label="Comentario / Descripción del problema" htmlFor="comentario" error={errors.comentario} required>
                                <textarea
                                    id="comentario"
                                    className="textarea textarea-bordered w-full min-h-24"
                                    value={data.comentario}
                                    onChange={(e) => setData('comentario', e.target.value)}
                                    placeholder="Describe el problema o solicitud"
                                />
                            </FormField>

                            <FormField label="Equipo (opcional)" htmlFor="equipo_id" error={errors.equipo_id}>
                                <Select
                                    id="equipo_id"
                                    value={data.equipo_id}
                                    onValueChange={(value) => setData('equipo_id', value)}
                                >
                                    <option value="">Sin equipo asociado</option>
                                    {equipos.map((equipo) => (
                                        <option key={equipo.id} value={equipo.id}>
                                            {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Técnico asignado (opcional)" htmlFor="tecnico_id" error={errors.tecnico_id}>
                                <Select
                                    id="tecnico_id"
                                    value={data.tecnico_id}
                                    onValueChange={(value) => setData('tecnico_id', value)}
                                >
                                    <option value="">Sin asignar</option>
                                    {tecnicos.map((tecnico) => (
                                        <option key={tecnico.id} value={tecnico.id}>
                                            {tecnico.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Estado inicial (opcional)" htmlFor="status_id" error={errors.status_id}>
                                <Select
                                    id="status_id"
                                    value={data.status_id}
                                    onValueChange={(value) => setData('status_id', value)}
                                >
                                    <option value="">Sin estado</option>
                                    {statuses.map((status) => (
                                        <option key={status.id} value={status.id}>
                                            {status.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/tickets">Cancelar</Link>
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
