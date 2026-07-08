import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';

type Configuracion = {
    id: number;
    dias_apartado: number;
    dias_cancelar_requisicion: number;
    dias_cancelar_solicitud: number;
    corte_activo: boolean;
    corte_dia: number;
    corte_hora: string;
};

type Props = { configuracion: Configuracion };

const DIAS_SEMANA: { value: number; label: string }[] = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Configuración', href: '/admin/costos/configuracion' },
];

export default function ConfiguracionCostosEdit({ configuracion }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        dias_apartado: configuracion.dias_apartado,
        dias_cancelar_requisicion: configuracion.dias_cancelar_requisicion,
        dias_cancelar_solicitud: configuracion.dias_cancelar_solicitud,
        corte_activo: configuracion.corte_activo,
        corte_dia: configuracion.corte_dia,
        corte_hora: configuracion.corte_hora,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/costos/configuracion', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configuración de Costos" />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="mb-1 text-2xl font-semibold">Configuración de Costos</h1>
                    <p className="mb-6 text-sm text-base-content/60">
                        Plazos automáticos del módulo. Los cambios aplican a partir de los próximos procesos diarios.
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <FormField
                            label="Días de apartado de presupuesto"
                            htmlFor="dias_apartado"
                            error={errors.dias_apartado}
                            required
                        >
                            <Input
                                id="dias_apartado"
                                type="number"
                                min={1}
                                max={365}
                                value={data.dias_apartado}
                                onChange={(e) => setData('dias_apartado', parseInt(e.target.value) || 1)}
                            />
                            <p className="mt-1 text-xs text-base-content/60">
                                Cuántos días dura la reserva temporal de presupuesto antes de liberarse.
                            </p>
                        </FormField>

                        <FormField
                            label="Días para cancelar requisiciones no aprobadas"
                            htmlFor="dias_cancelar_requisicion"
                            error={errors.dias_cancelar_requisicion}
                            required
                        >
                            <Input
                                id="dias_cancelar_requisicion"
                                type="number"
                                min={1}
                                max={365}
                                value={data.dias_cancelar_requisicion}
                                onChange={(e) => setData('dias_cancelar_requisicion', parseInt(e.target.value) || 1)}
                            />
                            <p className="mt-1 text-xs text-base-content/60">
                                Tras cuántos días sin avanzar se cancela una requisición pendiente de aprobación o aprobada.
                            </p>
                        </FormField>

                        <FormField
                            label="Días para cancelar solicitudes de pago no aprobadas"
                            htmlFor="dias_cancelar_solicitud"
                            error={errors.dias_cancelar_solicitud}
                            required
                        >
                            <Input
                                id="dias_cancelar_solicitud"
                                type="number"
                                min={1}
                                max={365}
                                value={data.dias_cancelar_solicitud}
                                onChange={(e) => setData('dias_cancelar_solicitud', parseInt(e.target.value) || 1)}
                            />
                            <p className="mt-1 text-xs text-base-content/60">
                                Tras cuántos días en "pendiente de firma" se cancela una solicitud de pago.
                            </p>
                        </FormField>

                        <div className="rounded-lg border border-base-300 p-4">
                            <label className="flex items-start gap-3">
                                <Checkbox
                                    className="mt-0.5"
                                    checked={data.corte_activo}
                                    onCheckedChange={(checked) => setData('corte_activo', checked)}
                                />
                                <span>
                                    <span className="font-medium">Corte semanal para la fecha de pago</span>
                                    <span className="mt-1 block text-xs text-base-content/60">
                                        Si está activo, una vez rebasado el día y hora de corte se bloquea el viernes de
                                        esa misma semana y solo pueden solicitarse pagos para el siguiente. Si se
                                        desactiva, puede elegirse cualquier viernes futuro, incluido el de la semana en
                                        curso.
                                    </span>
                                </span>
                            </label>

                            {data.corte_activo && (
                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <FormField label="Día de corte" htmlFor="corte_dia" error={errors.corte_dia}>
                                        <select
                                            id="corte_dia"
                                            className="select-bordered select w-full"
                                            value={data.corte_dia}
                                            onChange={(e) => setData('corte_dia', parseInt(e.target.value))}
                                        >
                                            {DIAS_SEMANA.map((dia) => (
                                                <option key={dia.value} value={dia.value}>
                                                    {dia.label}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>

                                    <FormField label="Hora de corte" htmlFor="corte_hora" error={errors.corte_hora}>
                                        <Input
                                            id="corte_hora"
                                            type="time"
                                            value={data.corte_hora}
                                            onChange={(e) => setData('corte_hora', e.target.value)}
                                        />
                                    </FormField>
                                </div>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                            {recentlySuccessful && <span className="text-sm text-success">Guardado.</span>}
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
