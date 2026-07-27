import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Produccion', href: '/admin/prod/destajos' },
    { title: 'Destajos', href: '/admin/prod/destajos' },
    { title: 'Nuevo', href: '/admin/prod/destajos/create' },
];

type Props = {
    anioSugerido: number;
    semanaSugerida: number;
};

/** Lunes de la semana ISO indicada. */
function lunesSemanaIso(anio: number, semana: number): Date {
    const simple = new Date(Date.UTC(anio, 0, 1 + (semana - 1) * 7));
    const day = simple.getUTCDay();
    const lunes = simple;
    if (day <= 4) {
        lunes.setUTCDate(simple.getUTCDate() - day + 1);
    } else {
        lunes.setUTCDate(simple.getUTCDate() + 8 - day);
    }
    return lunes;
}

function iso(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export default function DestajosCreate({ anioSugerido, semanaSugerida }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        anio: anioSugerido,
        semana: semanaSugerida,
        fecha_inicio: '',
        fecha_fin: '',
    });

    useEffect(() => {
        if (data.anio >= 2000 && data.semana >= 1 && data.semana <= 52) {
            const lunes = lunesSemanaIso(data.anio, data.semana);
            const domingo = new Date(lunes);
            domingo.setUTCDate(lunes.getUTCDate() + 6);
            setData((prev) => ({ ...prev, fecha_inicio: iso(lunes), fecha_fin: iso(domingo) }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.anio, data.semana]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/prod/destajos');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo destajo" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-6 text-2xl font-semibold">Nuevo destajo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Año" htmlFor="anio" error={errors.anio} required>
                                <Input
                                    id="anio"
                                    type="number"
                                    value={data.anio}
                                    onChange={(e) => setData('anio', Number(e.target.value))}
                                    error={!!errors.anio}
                                />
                            </FormField>
                            <FormField
                                label="Semana"
                                htmlFor="semana"
                                error={errors.semana}
                                description="1 a 52 (una por semana del año)"
                                required
                            >
                                <Input
                                    id="semana"
                                    type="number"
                                    min={1}
                                    max={52}
                                    value={data.semana}
                                    onChange={(e) => setData('semana', Number(e.target.value))}
                                    error={!!errors.semana}
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Inicio del periodo" htmlFor="fecha_inicio" error={errors.fecha_inicio} required>
                                <Input
                                    id="fecha_inicio"
                                    type="date"
                                    value={data.fecha_inicio}
                                    onChange={(e) => setData('fecha_inicio', e.target.value)}
                                    error={!!errors.fecha_inicio}
                                />
                            </FormField>
                            <FormField label="Fin del periodo" htmlFor="fecha_fin" error={errors.fecha_fin} required>
                                <Input
                                    id="fecha_fin"
                                    type="date"
                                    value={data.fecha_fin}
                                    onChange={(e) => setData('fecha_fin', e.target.value)}
                                    error={!!errors.fecha_fin}
                                />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/prod/destajos">Cancelar</Link>
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
