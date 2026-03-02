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

export default function BombasCreate({ fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `Registrar Bombas${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    const { data, setData, post, processing, errors } = useForm({
        bomba_posos_1: false,
        bomba_posos_2: false,
        bomba_planta_1: false,
        bomba_planta_2: false,
        bomba_planta_3: false,
        nivel_salmuera: '',
        nivel_tinaco: '',
        nivel_sisterna: '',
        nivel_hipoclorito: '',
        nivel_anticongelante: '',
        presion_tuberia: '',
        aceite_del_motor: '',
        tanque_diesel: '',
        voltaje_bateria: '',
        bomba_jockey: false,
        bomba_electrica: false,
        bomba_diesel: false,
        presion_tuberia_incendio: '',
        observaciones: '',
        fecha,
    });

    const { hasCachedData, clearCache } = useFormCache({
        key: `bombas:${fecha}:${turno?.id ?? 'null'}`,
        data,
        setData,
        exclude: ['fecha'],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams({ sistema: 'bombas', fecha });
        if (turno?.id) params.set('turno_id', String(turno.id));
        post('/admin/infra/recorridos?' + params.toString(), { onSuccess: () => clearCache() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Bombas" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Registrar Bombas{turno ? ` - ${turno.nombre}` : ''}
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
                        {/* Bombas de Pozos */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Bombas de Pozos</h2>
                                <div className="flex gap-6">
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_posos_1}
                                                onChange={(e) => setData('bomba_posos_1', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Pozo 1</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_posos_2}
                                                onChange={(e) => setData('bomba_posos_2', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Pozo 2</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Bombas de Planta */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Bombas de Planta</h2>
                                <div className="flex gap-6">
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_planta_1}
                                                onChange={(e) => setData('bomba_planta_1', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Planta 1</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_planta_2}
                                                onChange={(e) => setData('bomba_planta_2', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Planta 2</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_planta_3}
                                                onChange={(e) => setData('bomba_planta_3', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Planta 3</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Niveles */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Niveles</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Nivel Salmuera" htmlFor="nivel_salmuera" error={errors.nivel_salmuera}>
                                        <Input
                                            id="nivel_salmuera"
                                            type="number"
                                            step="0.01"
                                            value={data.nivel_salmuera}
                                            onChange={(e) => setData('nivel_salmuera', e.target.value)}
                                            placeholder="%"
                                        />
                                    </FormField>
                                    <FormField label="Nivel Tinaco" htmlFor="nivel_tinaco" error={errors.nivel_tinaco}>
                                        <Input
                                            id="nivel_tinaco"
                                            type="number"
                                            step="0.01"
                                            value={data.nivel_tinaco}
                                            onChange={(e) => setData('nivel_tinaco', e.target.value)}
                                            placeholder="%"
                                        />
                                    </FormField>
                                    <FormField label="Nivel Cisterna" htmlFor="nivel_sisterna" error={errors.nivel_sisterna}>
                                        <Input
                                            id="nivel_sisterna"
                                            type="number"
                                            step="0.01"
                                            value={data.nivel_sisterna}
                                            onChange={(e) => setData('nivel_sisterna', e.target.value)}
                                            placeholder="%"
                                        />
                                    </FormField>
                                    <FormField label="Nivel Hipoclorito" htmlFor="nivel_hipoclorito" error={errors.nivel_hipoclorito}>
                                        <Input
                                            id="nivel_hipoclorito"
                                            type="number"
                                            step="0.01"
                                            value={data.nivel_hipoclorito}
                                            onChange={(e) => setData('nivel_hipoclorito', e.target.value)}
                                            placeholder="%"
                                        />
                                    </FormField>
                                    <FormField label="Nivel Anticongelante" htmlFor="nivel_anticongelante" error={errors.nivel_anticongelante}>
                                        <Input
                                            id="nivel_anticongelante"
                                            type="number"
                                            step="0.01"
                                            value={data.nivel_anticongelante}
                                            onChange={(e) => setData('nivel_anticongelante', e.target.value)}
                                            placeholder="%"
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>

                        {/* Presion */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Presion</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Presion Tuberia" htmlFor="presion_tuberia" error={errors.presion_tuberia}>
                                        <Input
                                            id="presion_tuberia"
                                            type="number"
                                            step="0.01"
                                            value={data.presion_tuberia}
                                            onChange={(e) => setData('presion_tuberia', e.target.value)}
                                            placeholder="PSI"
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>

                        {/* Motor Diesel */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Motor Diesel</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField label="Aceite del Motor" htmlFor="aceite_del_motor" error={errors.aceite_del_motor}>
                                        <Input
                                            id="aceite_del_motor"
                                            type="number"
                                            step="0.01"
                                            value={data.aceite_del_motor}
                                            onChange={(e) => setData('aceite_del_motor', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Tanque Diesel" htmlFor="tanque_diesel" error={errors.tanque_diesel}>
                                        <Input
                                            id="tanque_diesel"
                                            type="number"
                                            step="0.01"
                                            value={data.tanque_diesel}
                                            onChange={(e) => setData('tanque_diesel', e.target.value)}
                                        />
                                    </FormField>
                                    <FormField label="Voltaje Bateria" htmlFor="voltaje_bateria" error={errors.voltaje_bateria}>
                                        <Input
                                            id="voltaje_bateria"
                                            type="number"
                                            step="0.01"
                                            value={data.voltaje_bateria}
                                            onChange={(e) => setData('voltaje_bateria', e.target.value)}
                                        />
                                    </FormField>
                                </div>
                            </div>
                        </div>

                        {/* Sistema Contra Incendios */}
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Sistema Contra Incendios</h2>
                                <div className="flex gap-6">
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_jockey}
                                                onChange={(e) => setData('bomba_jockey', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Jockey</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_electrica}
                                                onChange={(e) => setData('bomba_electrica', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Electrica</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_diesel}
                                                onChange={(e) => setData('bomba_diesel', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Diesel</span>
                                        </label>
                                    </div>
                                </div>
                                <div className="mt-4 grid grid-cols-2 gap-4">
                                    <FormField
                                        label="Presion Tuberia Incendio"
                                        htmlFor="presion_tuberia_incendio"
                                        error={errors.presion_tuberia_incendio}
                                    >
                                        <Input
                                            id="presion_tuberia_incendio"
                                            type="number"
                                            step="0.01"
                                            value={data.presion_tuberia_incendio}
                                            onChange={(e) => setData('presion_tuberia_incendio', e.target.value)}
                                            placeholder="PSI"
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
