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
    { title: 'Registrar Transformadores', href: '#' },
];

export default function TransformadoresCreate({ fecha }: { fecha: string }) {
    const { data, setData, post, processing, errors } = useForm({
        linea_a: '',
        linea_a_max: '',
        date_a: '',
        linea_b: '',
        linea_b_max: '',
        date_b: '',
        linea_c: '',
        linea_c_max: '',
        date_c: '',
        total_1: '',
        total_5: '',
        lectura_5y5: '',
        lectura_301: '',
        lectura_302: '',
        lectura_303: '',
        lectura_310: '',
        observaciones: '',
        fecha,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/infra/recorridos?sistema=transformadores&fecha=' + fecha);
    };

    const lineas = [
        { key: 'a', label: 'Linea A' },
        { key: 'b', label: 'Linea B' },
        { key: 'c', label: 'Linea C' },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Transformadores" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Registrar Transformadores</h1>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Lineas A, B, C */}
                        {lineas.map((linea) => (
                            <div key={linea.key} className="card bg-base-100 border border-base-300 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title text-lg">{linea.label}</h2>
                                    <div className="grid grid-cols-3 gap-4">
                                        <FormField
                                            label="Lectura"
                                            htmlFor={`linea_${linea.key}`}
                                            error={errors[`linea_${linea.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`linea_${linea.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`linea_${linea.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`linea_${linea.key}` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="kW/h"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Maximo"
                                            htmlFor={`linea_${linea.key}_max`}
                                            error={errors[`linea_${linea.key}_max` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`linea_${linea.key}_max`}
                                                type="number"
                                                step="0.01"
                                                value={data[`linea_${linea.key}_max` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`linea_${linea.key}_max` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="kW/h"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Fecha/Hora"
                                            htmlFor={`date_${linea.key}`}
                                            error={errors[`date_${linea.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`date_${linea.key}`}
                                                type="datetime-local"
                                                value={data[`date_${linea.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`date_${linea.key}` as keyof typeof data, e.target.value as never)
                                                }
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            </div>
                        ))}

                        {/* Totales */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Totales</h2>
                                <div className="grid grid-cols-3 gap-4">
                                    <FormField label="Total 1" htmlFor="total_1" error={errors.total_1}>
                                        <Input
                                            id="total_1"
                                            type="number"
                                            step="0.01"
                                            value={data.total_1}
                                            onChange={(e) => setData('total_1', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Total 5" htmlFor="total_5" error={errors.total_5}>
                                        <Input
                                            id="total_5"
                                            type="number"
                                            step="0.01"
                                            value={data.total_5}
                                            onChange={(e) => setData('total_5', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Lectura 5y5" htmlFor="lectura_5y5" error={errors.lectura_5y5}>
                                        <Input
                                            id="lectura_5y5"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_5y5}
                                            onChange={(e) => setData('lectura_5y5', e.target.value)}
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>

                        {/* Lecturas Adicionales */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Lecturas Adicionales</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Lectura 301" htmlFor="lectura_301" error={errors.lectura_301}>
                                        <Input
                                            id="lectura_301"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_301}
                                            onChange={(e) => setData('lectura_301', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Lectura 302" htmlFor="lectura_302" error={errors.lectura_302}>
                                        <Input
                                            id="lectura_302"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_302}
                                            onChange={(e) => setData('lectura_302', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Lectura 303" htmlFor="lectura_303" error={errors.lectura_303}>
                                        <Input
                                            id="lectura_303"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_303}
                                            onChange={(e) => setData('lectura_303', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Lectura 310" htmlFor="lectura_310" error={errors.lectura_310}>
                                        <Input
                                            id="lectura_310"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_310}
                                            onChange={(e) => setData('lectura_310', e.target.value)}
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>

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
