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

export default function PtarCreate({ fecha, turno }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Infraestructura', href: '/admin/infra/recorridos' },
        { title: 'Recorridos', href: '/admin/infra/recorridos' },
        { title: `Registrar PTAR${turno ? ` - ${turno.nombre}` : ''}`, href: '#' },
    ];
    const { data, setData, post, processing, errors } = useForm({
        soplador_activa: false,
        bomba_activa: false,
        trampa_solida: false,
        nivel_cloro: '',
        observaciones: '',
        fecha,
    });

    const { hasCachedData, clearCache } = useFormCache({
        key: `ptar:${fecha}:${turno?.id ?? 'null'}`,
        data,
        setData,
        exclude: ['fecha'],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams({ sistema: 'ptar', fecha });
        if (turno?.id) params.set('turno_id', String(turno.id));
        post('/admin/infra/recorridos?' + params.toString(), { onSuccess: () => clearCache() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar PTAR" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">
                        Registrar PTAR{turno ? ` - ${turno.nombre}` : ''}
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
                        <div className="card bg-base-100 border border-base-300 shadow-sm">
                            <div className="card-body">
                                <h2 className="card-title text-lg">Estado del Sistema</h2>
                                <div className="flex gap-6">
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.soplador_activa}
                                                onChange={(e) => setData('soplador_activa', e.target.checked)}
                                            />
                                            <span className="label-text">Soplador Activo</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.bomba_activa}
                                                onChange={(e) => setData('bomba_activa', e.target.checked)}
                                            />
                                            <span className="label-text">Bomba Activa</span>
                                        </label>
                                    </div>
                                    <div className="form-control">
                                        <label className="label cursor-pointer justify-start gap-3">
                                            <input
                                                type="checkbox"
                                                className="toggle toggle-primary"
                                                checked={data.trampa_solida}
                                                onChange={(e) => setData('trampa_solida', e.target.checked)}
                                            />
                                            <span className="label-text">Trampa Sólidos {data.trampa_solida ? '(Limpio)' : '(Sucio)'}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <FormField label="Nivel de Cloro" htmlFor="nivel_cloro" error={errors.nivel_cloro}>
                            <Input
                                id="nivel_cloro"
                                type="number"
                                step="0.01"
                                value={data.nivel_cloro}
                                onChange={(e) => setData('nivel_cloro', e.target.value)}
                                placeholder="%"
                            />
                        </FormField>

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
