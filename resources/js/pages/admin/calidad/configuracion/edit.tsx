import { Head, useForm, usePage } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/dashboard' },
    { title: 'Configuración', href: '/admin/calidad/configuracion' },
];

type Props = { configuracion: { formularios_segun_avance: boolean } };

export default function ConfiguracionCalidad({ configuracion }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const { data, setData, put, processing, errors } = useForm({
        formularios_segun_avance: configuracion.formularios_segun_avance,
    });

    const guardar = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/calidad/configuracion', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuración de Calidad" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Configuración de Calidad</h1>
                    <p className="text-base-content/60 mt-1 mb-6 text-sm">
                        Lo que cambia aquí cambia cómo trabaja toda la planta en Formularios.
                    </p>

                    {flash?.success && (
                        <div className="alert alert-success mb-4">
                            <span>{flash.success}</span>
                        </div>
                    )}

                    <form onSubmit={guardar} className="space-y-4">
                        <label className="rounded-box border-base-300 flex cursor-pointer items-start gap-3 border p-4">
                            <input
                                type="checkbox"
                                className="toggle toggle-primary mt-0.5"
                                checked={data.formularios_segun_avance}
                                onChange={(e) => setData('formularios_segun_avance', e.target.checked)}
                            />
                            <span>
                                <span className="block font-medium">Formularios según el avance de producción</span>
                                <span className="text-base-content/60 mt-1 block text-sm">
                                    Encendido, en Formularios sólo se pueden escanear y registrar las piezas que
                                    Producción programó: las del plan cerrado de la semana, las atrasadas de semanas
                                    anteriores y las que ya tienen inspección en esa fase. Armado y soldado se miden
                                    contra el plan de 2ª y pintura contra el de 3ª. Si una obra no tiene cerrado el
                                    plan de la semana en una fase, no se registra ninguna pieza de esa fase. La 1ª
                                    transformación no se limita.
                                </span>
                                <span className="text-base-content/60 mt-1 block text-sm">
                                    La pestaña Registros de Formularios también enseña sólo esas piezas.
                                </span>
                                {errors.formularios_segun_avance && (
                                    <span className="text-error mt-1 block text-sm">{errors.formularios_segun_avance}</span>
                                )}
                            </span>
                        </label>

                        <div className="flex justify-end">
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
