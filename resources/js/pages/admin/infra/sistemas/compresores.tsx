import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Infraestructura', href: '/admin/infra/recorridos' },
    { title: 'Recorridos', href: '/admin/infra/recorridos' },
    { title: 'Registrar Compresores', href: '#' },
];

export default function CompresoresCreate({ fecha }: { fecha: string }) {
    const { data, setData, post, processing, errors } = useForm({
        compresor_1_status: false,
        compresor_1_presion_aire: '',
        compresor_1_kwhr: '',
        compresor_1_tiempo_trabajo: '',
        compresor_1_tiempo_marcha: '',
        compresor_2_status: false,
        compresor_2_presion_aire: '',
        compresor_2_kwhr: '',
        compresor_2_tiempo_trabajo: '',
        compresor_2_tiempo_marcha: '',
        compresor_3_status: false,
        compresor_3_presion_aire: '',
        compresor_3_kwhr: '',
        compresor_3_tiempo_trabajo: '',
        compresor_3_tiempo_marcha: '',
        observaciones: '',
        fecha,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/infra/recorridos?sistema=compresores&fecha=' + fecha);
    };

    const compresores = [1, 2, 3] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Compresores" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Registrar Compresores</h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {compresores.map((num) => (
                            <div key={num} className="card bg-base-100 border border-base-300 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title text-lg">Compresor {num}</h2>

                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data[`compresor_${num}_status` as keyof typeof data] as boolean}
                                                onChange={(e) =>
                                                    setData(`compresor_${num}_status` as keyof typeof data, e.target.checked as never)
                                                }
                                            />
                                            <span className="label-text">Encendido</span>
                                        </label>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <FormField
                                            label="Presion de Aire"
                                            htmlFor={`compresor_${num}_presion_aire`}
                                            error={errors[`compresor_${num}_presion_aire` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`compresor_${num}_presion_aire`}
                                                type="number"
                                                step="0.01"
                                                value={data[`compresor_${num}_presion_aire` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`compresor_${num}_presion_aire` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="PSI"
                                            />
                                        </FormField>

                                        <FormField
                                            label="kW/h"
                                            htmlFor={`compresor_${num}_kwhr`}
                                            error={errors[`compresor_${num}_kwhr` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`compresor_${num}_kwhr`}
                                                type="number"
                                                step="0.01"
                                                value={data[`compresor_${num}_kwhr` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`compresor_${num}_kwhr` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="kW/h"
                                            />
                                        </FormField>

                                        <FormField
                                            label="Tiempo de Trabajo"
                                            htmlFor={`compresor_${num}_tiempo_trabajo`}
                                            error={errors[`compresor_${num}_tiempo_trabajo` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`compresor_${num}_tiempo_trabajo`}
                                                type="number"
                                                step="0.01"
                                                value={data[`compresor_${num}_tiempo_trabajo` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(
                                                        `compresor_${num}_tiempo_trabajo` as keyof typeof data,
                                                        e.target.value as never,
                                                    )
                                                }
                                                placeholder="Horas"
                                            />
                                        </FormField>

                                        <FormField
                                            label="Tiempo de Marcha"
                                            htmlFor={`compresor_${num}_tiempo_marcha`}
                                            error={errors[`compresor_${num}_tiempo_marcha` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`compresor_${num}_tiempo_marcha`}
                                                type="number"
                                                step="0.01"
                                                value={data[`compresor_${num}_tiempo_marcha` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(
                                                        `compresor_${num}_tiempo_marcha` as keyof typeof data,
                                                        e.target.value as never,
                                                    )
                                                }
                                                placeholder="Horas"
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            </div>
                        ))}

                        <FormField label="Observaciones" htmlFor="observaciones" error={errors.observaciones}>
                            <textarea
                                id="observaciones"
                                className="textarea textarea-bordered min-h-24 w-full"
                                value={data.observaciones}
                                onChange={(e) => setData('observaciones', e.target.value)}
                                placeholder="Observaciones generales"
                            />
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/infra/recorridos?fecha=${fecha}`}>Cancelar</Link>
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
