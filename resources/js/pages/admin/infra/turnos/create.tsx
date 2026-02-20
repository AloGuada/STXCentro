import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Turnos', href: '/admin/infra/turnos' },
    { title: 'Nuevo Turno', href: '/admin/infra/turnos/create' },
];

const DIAS = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
    { value: 7, label: 'Domingo' },
];

export default function TurnoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        hora_inicio: '08:00',
        hora_fin: '12:00',
        orden: 0,
        dias_semana: [1, 2, 3, 4, 5] as number[],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/infra/turnos');
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
            <Head title="Nuevo Turno" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo Turno</h1>

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
