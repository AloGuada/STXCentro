import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { type FormEvent } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Configuracion = {
    id: number;
    dias_apartado: number;
    dias_cancelar_requisicion: number;
    dias_cancelar_solicitud: number;
    corte_activo: boolean;
    corte_dia: number;
    corte_hora: string;
    dia_comprobante_recepcion: number | null;
    tolerancia_recepcion: number;
    gerente_compras_id: string | null;
};

type Usuario = { id: string; name: string };

type Props = { configuracion: Configuracion; usuarios: Usuario[] };

const DIAS_SEMANA: { value: number; label: string }[] = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
];

// Numeración Carbon: 0 = domingo … 6 = sábado.
const DIAS_COMPROBANTE: { value: number; label: string }[] = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Costos', href: '/admin/costos/ordenes-compra' },
    { title: 'Configuración', href: '/admin/costos/configuracion' },
];

export default function ConfiguracionCostosEdit({ configuracion, usuarios }: Props) {
    const { data, setData, put, transform, processing, errors, recentlySuccessful } = useForm<{
        dias_apartado: number;
        dias_cancelar_requisicion: number;
        dias_cancelar_solicitud: number;
        corte_activo: boolean;
        corte_dia: number;
        corte_hora: string;
        dia_comprobante_recepcion: number | '';
        tolerancia_recepcion: number;
        gerente_compras_id: string;
    }>({
        dias_apartado: configuracion.dias_apartado,
        dias_cancelar_requisicion: configuracion.dias_cancelar_requisicion,
        dias_cancelar_solicitud: configuracion.dias_cancelar_solicitud,
        corte_activo: configuracion.corte_activo,
        corte_dia: configuracion.corte_dia,
        corte_hora: configuracion.corte_hora,
        dia_comprobante_recepcion: configuracion.dia_comprobante_recepcion ?? '',
        tolerancia_recepcion: Number(configuracion.tolerancia_recepcion ?? 0.01),
        gerente_compras_id: configuracion.gerente_compras_id ?? '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        transform((d) => ({
            ...d,
            dia_comprobante_recepcion: d.dia_comprobante_recepcion === '' ? null : d.dia_comprobante_recepcion,
        }));
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

                        <div className="rounded-lg border border-base-300 p-4">
                            <FormField
                                label="Día para subir el comprobante de recepción"
                                htmlFor="dia_comprobante_recepcion"
                                error={errors.dia_comprobante_recepcion}
                            >
                                <select
                                    id="dia_comprobante_recepcion"
                                    className="select-bordered select w-full"
                                    value={data.dia_comprobante_recepcion === '' ? '' : String(data.dia_comprobante_recepcion)}
                                    onChange={(e) => setData('dia_comprobante_recepcion', e.target.value === '' ? '' : parseInt(e.target.value))}
                                >
                                    <option value="">Libre (cualquier día)</option>
                                    {DIAS_COMPROBANTE.map((dia) => (
                                        <option key={dia.value} value={dia.value}>
                                            {dia.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-base-content/60">
                                    Día en que el proveedor puede subir el comprobante de recepción de su factura en el
                                    portal. Si se deja en "Libre", puede subirlo cualquier día.
                                </p>
                            </FormField>
                        </div>

                        <div className="rounded-lg border border-base-300 p-4">
                            <FormField
                                label="Tolerancia entre factura y recepción ($)"
                                htmlFor="tolerancia_recepcion"
                                error={errors.tolerancia_recepcion}
                                required
                            >
                                <Input
                                    id="tolerancia_recepcion"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.tolerancia_recepcion}
                                    onChange={(e) => setData('tolerancia_recepcion', parseFloat(e.target.value) || 0)}
                                />
                                <p className="mt-1 text-xs text-base-content/60">
                                    Hasta cuántos pesos puede diferir el total del CFDI de lo que se está recibiendo en
                                    Almacén sin que la entrada se rechace. La recepción guarda lo que entró tal cual; la
                                    diferencia se acepta como redondeo del proveedor.
                                </p>
                            </FormField>
                        </div>

                        <div className="rounded-lg border border-base-300 p-4">
                            <FormField
                                label="Gerente de compras (firma de solicitudes de OC)"
                                htmlFor="gerente_compras_id"
                                error={errors.gerente_compras_id}
                            >
                                <select
                                    id="gerente_compras_id"
                                    className="select-bordered select w-full"
                                    value={data.gerente_compras_id}
                                    onChange={(e) => setData('gerente_compras_id', e.target.value)}
                                >
                                    <option value="">— Sin asignar —</option>
                                    {usuarios.map((usuario) => (
                                        <option key={usuario.id} value={usuario.id}>
                                            {usuario.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-base-content/60">
                                    Las solicitudes de pago generadas por una orden de compra llevan una sola firma: la
                                    de este gerente (ignoran los niveles de aprobación del departamento). En el PDF se
                                    imprime además un espacio de firma para el usuario de compras que la elaboró.
                                </p>
                            </FormField>
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
