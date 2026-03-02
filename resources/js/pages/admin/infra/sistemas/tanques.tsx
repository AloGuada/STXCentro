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

export default function TanquesCreate({ fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `Registrar Tanques de Gas${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    const { data, setData, post, processing, errors } = useForm({
        pa_sistema_oxigeno: '',
        presion_sistema_oxigeno: '',
        presion_tanque_oxigeno: '',
        lt_tanque_oxigeno: '',
        kg_tanque_oxigeno: '',
        pa_sistema_argon: '',
        presion_sistema_argon: '',
        presion_tanque_argon: '',
        lt_tanque_argon: '',
        kg_tanque_argon: '',
        pa_sistema_co2: '',
        presion_sistema_co2: '',
        presion_tanque_co2: '',
        lt_tanque_co2: '',
        kg_tanque_co2: '',
        pa_sistema_lp: '',
        presion_sistema_lp: '',
        nivel_tanque_lp: '',
        lt_tanque_lp: '',
        kg_tanque_lp: '',
        numero_tanque_lp: '',
        observaciones: '',
        fecha,
    });

    const { hasCachedData, clearCache } = useFormCache({
        key: `tanques:${fecha}:${turno?.id ?? 'null'}`,
        data,
        setData,
        exclude: ['fecha'],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams({ sistema: 'tanques', fecha });
        if (turno?.id) params.set('turno_id', String(turno.id));
        post('/admin/infra/recorridos?' + params.toString(), { onSuccess: () => clearCache() });
    };

    const gases = [
        { key: 'oxigeno', label: 'Oxigeno' },
        { key: 'argon', label: 'Argon' },
        { key: 'co2', label: 'CO2' },
        { key: 'lp', label: 'Gas LP' },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Tanques de Gas" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Registrar Tanques de Gas{turno ? ` - ${turno.nombre}` : ''}
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
                        {gases.map((gas) => {
                            const thirdFieldKey = gas.key === 'lp' ? 'nivel_tanque_lp' : `presion_tanque_${gas.key}`;
                            const thirdLabel = gas.key === 'lp' ? 'Nivel Tanque (%)' : 'Presion Tanque';
                            const thirdPlaceholder = gas.key === 'lp' ? '%' : 'PSI';

                            return (
                            <div key={gas.key} className="card bg-base-100 border border-base-300 shadow-sm">
                                <div className="card-body">
                                    <h2 className="card-title text-lg">{gas.label}</h2>
                                    <div className="grid grid-cols-2 gap-4">
                                        <FormField
                                            label="PA Sistema"
                                            htmlFor={`pa_sistema_${gas.key}`}
                                            error={errors[`pa_sistema_${gas.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`pa_sistema_${gas.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`pa_sistema_${gas.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`pa_sistema_${gas.key}` as keyof typeof data, e.target.value as never)
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="Presion Sistema"
                                            htmlFor={`presion_sistema_${gas.key}`}
                                            error={errors[`presion_sistema_${gas.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`presion_sistema_${gas.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`presion_sistema_${gas.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(
                                                        `presion_sistema_${gas.key}` as keyof typeof data,
                                                        e.target.value as never,
                                                    )
                                                }
                                                placeholder="PSI"
                                            />
                                        </FormField>
                                        <FormField
                                            label={thirdLabel}
                                            htmlFor={thirdFieldKey}
                                            error={errors[thirdFieldKey as keyof typeof errors]}
                                        >
                                            <Input
                                                id={thirdFieldKey}
                                                type="number"
                                                step="0.01"
                                                value={data[thirdFieldKey as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(
                                                        thirdFieldKey as keyof typeof data,
                                                        e.target.value as never,
                                                    )
                                                }
                                                placeholder={thirdPlaceholder}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Litros Tanque"
                                            htmlFor={`lt_tanque_${gas.key}`}
                                            error={errors[`lt_tanque_${gas.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`lt_tanque_${gas.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`lt_tanque_${gas.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`lt_tanque_${gas.key}` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="Lt"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Kilogramos Tanque"
                                            htmlFor={`kg_tanque_${gas.key}`}
                                            error={errors[`kg_tanque_${gas.key}` as keyof typeof errors]}
                                        >
                                            <Input
                                                id={`kg_tanque_${gas.key}`}
                                                type="number"
                                                step="0.01"
                                                value={data[`kg_tanque_${gas.key}` as keyof typeof data] as string}
                                                onChange={(e) =>
                                                    setData(`kg_tanque_${gas.key}` as keyof typeof data, e.target.value as never)
                                                }
                                                placeholder="Kg"
                                            />
                                        </FormField>
                                        {gas.key === 'lp' && (
                                            <FormField
                                                label="Numero de Tanque"
                                                htmlFor="numero_tanque_lp"
                                                error={errors.numero_tanque_lp}
                                            >
                                                <Input
                                                    id="numero_tanque_lp"
                                                    type="number"
                                                    step="1"
                                                    value={data.numero_tanque_lp}
                                                    onChange={(e) => setData('numero_tanque_lp', e.target.value)}
                                                />
                                            </FormField>
                                        )}
                                    </div>
                                </div>
                            </div>
                            );
                        })}

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
