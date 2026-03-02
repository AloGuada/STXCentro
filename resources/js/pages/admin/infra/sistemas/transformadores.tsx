import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormCache } from '@/hooks/use-form-cache';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { InfraTurno } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    fecha: string;
    turno: InfraTurno | null;
};

export default function TransformadoresCreate({ fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `Registrar Transformadores${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    const { data, setData, post, processing, errors } = useForm({
        linea_a: '',
        linea_a_max: '',
        date_a: '',
        voltaje_a: '',
        registro_a: '',
        linea_b: '',
        linea_b_max: '',
        date_b: '',
        voltaje_b: '',
        registro_b: '',
        linea_c: '',
        linea_c_max: '',
        date_c: '',
        voltaje_c: '',
        registro_c: '',
        total_1: '',
        total_5: '',
        lectura_5y5: '',
        tarifa: '',
        observaciones: '',
        fecha,
    });

    const { hasCachedData, clearCache } = useFormCache({
        key: `transformadores:${fecha}:${turno?.id ?? 'null'}`,
        data,
        setData,
        exclude: ['fecha'],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams({ sistema: 'transformadores', fecha });
        if (turno?.id) params.set('turno_id', String(turno.id));
        post('/admin/infra/recorridos?' + params.toString(), { onSuccess: () => clearCache() });
    };

    const lineas = [
        { key: 'a', label: 'Linea A', codEnergia: '11', codRegistro: '212', codPotencia: '41', codFecha: '71', codHora: '81' },
        { key: 'b', label: 'Linea B', codEnergia: '12', codRegistro: '234', codPotencia: '42', codFecha: '72', codHora: '82' },
        { key: 'c', label: 'Linea C', codEnergia: '13', codRegistro: '256', codPotencia: '43', codFecha: '73', codHora: '83' },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Transformadores" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Registrar Transformadores{turno ? ` - ${turno.nombre}` : ''}
                    </h1>

                    {hasCachedData && (
                        <div className="alert alert-warning mb-4">
                            <span>Tienes datos no guardados de una sesion anterior.</span>
                            <button className="btn btn-sm btn-ghost" onClick={clearCache}>
                                Descartar
                            </button>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Voltajes */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Voltajes</h2>
                                <div className="grid grid-cols-3 gap-4">
                                    {lineas.map((linea) => (
                                        <FormField
                                            key={linea.key}
                                            label={`Voltaje ${linea.label} (V)`}
                                            htmlFor={`voltaje_${linea.key}`}
                                            error={errors[`voltaje_${linea.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`voltaje_${linea.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`voltaje_${linea.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`voltaje_${linea.key}` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="120.00"
                                            />
                                        </FormField>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Lineas A, B, C */}
                        {lineas.map((linea) => (
                            <div key={linea.key} className="card bg-base-100 border border-base-300 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title text-lg">{linea.label}</h2>
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        <FormField
                                            label={`Energía Consumida kWh (${linea.codEnergia})`}
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
                                                placeholder="0.0"
                                            />
                                        </FormField>
                                        <FormField
                                            label={`Energía Registro kWh (${linea.codRegistro})`}
                                            htmlFor={`registro_${linea.key}`}
                                            error={errors[`registro_${linea.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`registro_${linea.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`registro_${linea.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`registro_${linea.key}` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="0.0"
                                            />
                                        </FormField>
                                        <FormField
                                            label={`Potencia Instantánea kW (${linea.codPotencia})`}
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
                                                placeholder="0.0"
                                            />
                                        </FormField>
                                        <FormField
                                            label={`Fecha/Hora (${linea.codFecha}/${linea.codHora})`}
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
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <FormField label="Consumo Total kWh (4)" htmlFor="total_1" error={errors.total_1}>
                                        <Input
                                            id="total_1"
                                            type="number"
                                            step="0.01"
                                            value={data.total_1}
                                            onChange={(e) => setData('total_1', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Consumo Red kWh (5)" htmlFor="total_5" error={errors.total_5}>
                                        <Input
                                            id="total_5"
                                            type="number"
                                            step="0.01"
                                            value={data.total_5}
                                            onChange={(e) => setData('total_5', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Energía Generada kWh (190)" htmlFor="lectura_5y5" error={errors.lectura_5y5}>
                                        <Input
                                            id="lectura_5y5"
                                            type="number"
                                            step="0.01"
                                            value={data.lectura_5y5}
                                            onChange={(e) => setData('lectura_5y5', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Tarifa (8)" htmlFor="tarifa" error={errors.tarifa}>
                                        <Input
                                            id="tarifa"
                                            type="number"
                                            step="1"
                                            value={data.tarifa}
                                            onChange={(e) => setData('tarifa', e.target.value)}
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
