import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraTurno } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    turno: InfraTurno;
};

const DIAS = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
    { value: 7, label: 'Domingo' },
];

export default function TurnoEdit({ turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Turnos', href: '/admin/infra/turnos' },
        { title: turno.nombre, href: `/admin/infra/turnos/${turno.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: turno.nombre,
        hora_inicio: turno.hora_inicio?.substring(0, 5) ?? '08:00',
        hora_fin: turno.hora_fin?.substring(0, 5) ?? '12:00',
        orden: turno.orden,
        activo: turno.activo ?? true,
        dias_semana: (turno.dias_semana ?? []).map((d) => d.dia_semana),
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/infra/turnos/${turno.id}`);
    };

    const toggleDia = (dia: number) => {
        const current = data.dias_semana;
        if (current.includes(dia)) {
            setData('dias_semana', current.filter((d) => d !== dia));
        } else {
            setData('dias_semana', [...current, dia].sort());
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${turno.nombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Turno</h1>
                        <DeleteDialog
                            title="Eliminar turno"
                            description={`¿Estás seguro de eliminar el turno "${turno.nombre}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/infra/turnos/${turno.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input
                                id="nombre"
                                value={data.nombre}
                                onChange={(e) => setData('nombre', e.target.value)}
                                placeholder="Ej: R1 Matutino"
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Hora Inicio" htmlFor="hora_inicio" error={errors.hora_inicio} required>
                                <Input
                                    id="hora_inicio"
                                    type="time"
                                    value={data.hora_inicio}
                                    onChange={(e) => setData('hora_inicio', e.target.value)}
                                />
                            </FormField>

                            <FormField label="Hora Fin" htmlFor="hora_fin" error={errors.hora_fin} required>
                                <Input
                                    id="hora_fin"
                                    type="time"
                                    value={data.hora_fin}
                                    onChange={(e) => setData('hora_fin', e.target.value)}
                                />
                            </FormField>
                        </div>

                        <FormField label="Orden" htmlFor="orden" error={errors.orden}>
                            <Input
                                id="orden"
                                type="number"
                                min={0}
                                value={data.orden}
                                onChange={(e) => setData('orden', parseInt(e.target.value) || 0)}
                                placeholder="Orden de visualización"
                            />
                        </FormField>

                        <FormField label="Activo" htmlFor="activo" error={errors.activo}>
                            <label className="flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    className="toggle toggle-primary"
                                    checked={data.activo}
                                    onChange={(e) => setData('activo', e.target.checked)}
                                />
                                <span className="text-sm">{data.activo ? 'Turno activo' : 'Turno inactivo'}</span>
                            </label>
                        </FormField>

                        <FormField label="Días de la semana" htmlFor="dias_semana" error={errors.dias_semana} required>
                            <div className="flex flex-wrap gap-4">
                                {DIAS.map((dia) => (
                                    <label key={dia.value} className="flex cursor-pointer items-center gap-2">
                                        <Checkbox
                                            checked={data.dias_semana.includes(dia.value)}
                                            onCheckedChange={() => toggleDia(dia.value)}
                                        />
                                        <span>{dia.label}</span>
                                    </label>
                                ))}
                            </div>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/infra/turnos">Cancelar</Link>
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
