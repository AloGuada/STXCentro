import { formatearMXN } from '@/components/cob/money-display';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CobIcsoeSeguimiento } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircleIcon, CheckCircle2Icon, PlusIcon } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cobranza', href: '/admin/cob/dashboard' },
    { title: 'ICSOE / SIROC', href: '/admin/cob/icsoe' },
];

type Props = {
    seguimientos: CobIcsoeSeguimiento[];
    proyectosSinSeguimiento: { id: number; no: string; descripcion: string | null }[];
    filters: { estatus: string; search: string };
    estatusOptions: Record<string, string>;
    metodoOptions: Record<string, string>;
};

export default function IcsoeIndex({ seguimientos, proyectosSinSeguimiento, filters, estatusOptions, metodoOptions }: Props) {
    const { can } = useCan();
    const [mostrarAlta, setMostrarAlta] = useState(false);

    const alta = useForm({
        proyecto_id: '',
        metodo: 'porcentaje',
        fecha_inicio: '',
        fecha_fin: '',
        superficie_m2: '',
        costo_m2: '1154',
        porcentaje_mo: '30',
        prima_riesgo: '7.58875',
    });

    const filtrar = (campo: 'estatus' | 'search', valor: string) => {
        router.get('/admin/cob/icsoe', { ...filters, [campo]: valor }, { preserveState: true, replace: true });
    };

    const crear = (e: FormEvent) => {
        e.preventDefault();
        if (!alta.data.proyecto_id) return;
        alta.post(`/admin/cob/proyectos/${alta.data.proyecto_id}/icsoe`);
    };

    const verificar = (id: number) => router.post(`/admin/cob/icsoe/${id}/verificar`, {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ICSOE / SIROC" />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">ICSOE / SIROC</h1>
                        <p className="text-sm text-base-content/60">
                            Mano de obra que el IMSS espera comprobar por proyecto, contra la realmente cotizada. La base sale del valor a
                            ejecutar de cobranza: el comparativo en precios unitarios, las partidas en precio alzado.
                        </p>
                    </div>
                    {can('cob.icsoe.crear') && proyectosSinSeguimiento.length > 0 && (
                        <Button type="button" onClick={() => setMostrarAlta((v) => !v)}>
                            <PlusIcon className="size-4" />
                            Nuevo seguimiento
                        </Button>
                    )}
                </div>

                {mostrarAlta && (
                    <form onSubmit={crear} className="grid grid-cols-1 gap-4 rounded-box border border-base-300 p-4 md:grid-cols-3">
                        <div className="md:col-span-3">
                            <label className="mb-1 block text-sm font-medium">Proyecto</label>
                            <Select
                                value={alta.data.proyecto_id}
                                onValueChange={(valor) => alta.setData('proyecto_id', valor)}
                                placeholder="Elige un proyecto"
                            >
                                {proyectosSinSeguimiento.map((proyecto) => (
                                    <SelectItem key={proyecto.id} value={String(proyecto.id)}>
                                        {proyecto.no} — {proyecto.descripcion}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium">Método</label>
                            <Select value={alta.data.metodo} onValueChange={(valor) => alta.setData('metodo', valor)}>
                                {Object.entries(metodoOptions).map(([valor, etiqueta]) => (
                                    <SelectItem key={valor} value={valor}>
                                        {etiqueta}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Fecha de inicio</label>
                            <Input type="date" value={alta.data.fecha_inicio} onChange={(e) => alta.setData('fecha_inicio', e.target.value)} />
                            {alta.errors.fecha_inicio && <p className="mt-1 text-sm text-error">{alta.errors.fecha_inicio}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Fecha de término</label>
                            <Input type="date" value={alta.data.fecha_fin} onChange={(e) => alta.setData('fecha_fin', e.target.value)} />
                            {alta.errors.fecha_fin && <p className="mt-1 text-sm text-error">{alta.errors.fecha_fin}</p>}
                        </div>

                        {alta.data.metodo === 'superficie' ? (
                            <>
                                <div>
                                    <label className="mb-1 block text-sm font-medium">Superficie (m²)</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={alta.data.superficie_m2}
                                        onChange={(e) => alta.setData('superficie_m2', e.target.value)}
                                    />
                                    {alta.errors.superficie_m2 && <p className="mt-1 text-sm text-error">{alta.errors.superficie_m2}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium">Costo DOF ($/m²)</label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={alta.data.costo_m2}
                                        onChange={(e) => alta.setData('costo_m2', e.target.value)}
                                    />
                                </div>
                            </>
                        ) : (
                            <div>
                                <label className="mb-1 block text-sm font-medium">% de M.O.</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={alta.data.porcentaje_mo}
                                    onChange={(e) => alta.setData('porcentaje_mo', e.target.value)}
                                />
                                {alta.errors.porcentaje_mo && <p className="mt-1 text-sm text-error">{alta.errors.porcentaje_mo}</p>}
                            </div>
                        )}

                        <div>
                            <label className="mb-1 block text-sm font-medium">Prima de riesgo (%)</label>
                            <Input
                                type="number"
                                step="0.00001"
                                value={alta.data.prima_riesgo}
                                onChange={(e) => alta.setData('prima_riesgo', e.target.value)}
                            />
                        </div>

                        <div className="flex items-end justify-end gap-2 md:col-span-3">
                            <Button type="button" variant="outline" onClick={() => setMostrarAlta(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={alta.processing || !alta.data.proyecto_id}>
                                Crear seguimiento
                            </Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-56">
                        <label className="mb-1 block text-sm font-medium">Estatus</label>
                        <Select value={filters.estatus} onValueChange={(valor) => filtrar('estatus', valor)}>
                            <SelectItem value="todos">Todos</SelectItem>
                            {Object.entries(estatusOptions).map(([valor, etiqueta]) => (
                                <SelectItem key={valor} value={valor}>
                                    {etiqueta}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                    <div className="w-72">
                        <label className="mb-1 block text-sm font-medium">Buscar proyecto</label>
                        <Input
                            defaultValue={filters.search}
                            placeholder="Número o descripción"
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    filtrar('search', (e.target as HTMLInputElement).value);
                                }
                            }}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-box border border-base-300">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <th>Proyecto</th>
                                <th>Periodo</th>
                                <th>Método</th>
                                <th className="text-right">Meta IMSS</th>
                                <th className="text-right">M.O. real</th>
                                <th className="text-right">Diferencia</th>
                                <th className="text-right">Riesgo en cuotas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {seguimientos.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-8 text-center text-base-content/60">
                                        Sin seguimientos ICSOE.
                                    </td>
                                </tr>
                            ) : (
                                seguimientos.map((seguimiento) => {
                                    const pendiente = seguimiento.estatus === 'pendiente_verificacion';
                                    const diferencia = Number(seguimiento.diferencia_mo);

                                    return (
                                        <tr
                                            key={seguimiento.id}
                                            className={pendiente ? 'border-l-4 border-l-warning bg-warning/10' : 'hover'}
                                        >
                                            <td>
                                                <a className="font-medium hover:underline" href={`/admin/cob/icsoe/${seguimiento.id}`}>
                                                    {seguimiento.proyecto?.no}
                                                </a>
                                                <div className="text-xs text-base-content/60">{seguimiento.proyecto?.descripcion}</div>
                                                {pendiente && (
                                                    <div className="mt-1 space-y-0.5">
                                                        <span className="badge badge-warning badge-sm gap-1">
                                                            <AlertCircleIcon className="size-3" />
                                                            Pendiente de verificación
                                                        </span>
                                                        {seguimiento.monto_base_anterior && (
                                                            <div className="text-xs opacity-70">
                                                                Base: {formatearMXN(Number(seguimiento.monto_base_anterior))} →{' '}
                                                                {formatearMXN(Number(seguimiento.monto_base))}
                                                            </div>
                                                        )}
                                                        {seguimiento.motivo_cambio && (
                                                            <div className="text-xs opacity-60">{seguimiento.motivo_cambio}</div>
                                                        )}
                                                    </div>
                                                )}
                                                {seguimiento.estatus === 'cerrado' && (
                                                    <span className="mt-1 badge badge-ghost badge-sm">Cerrado</span>
                                                )}
                                            </td>
                                            <td className="text-sm">
                                                {seguimiento.fecha_inicio} al {seguimiento.fecha_fin}
                                                <div className="text-xs text-base-content/60">{seguimiento.total_dias} días</div>
                                            </td>
                                            <td className="text-sm">{metodoOptions[seguimiento.metodo]}</td>
                                            <td className="text-right">{formatearMXN(Number(seguimiento.mo_estimada_total))}</td>
                                            <td className="text-right text-success">{formatearMXN(Number(seguimiento.mo_real_total))}</td>
                                            <td className={`text-right ${diferencia > 0 ? 'text-error' : 'text-base-content/50'}`}>
                                                {formatearMXN(diferencia)}
                                            </td>
                                            <td className={`text-right font-medium ${diferencia > 0 ? 'text-error' : ''}`}>
                                                {formatearMXN(Number(seguimiento.monto_riesgo))}
                                            </td>
                                            <td>
                                                {pendiente && can('cob.icsoe.verificar') && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Confirmar"
                                                        onClick={() => verificar(seguimiento.id)}
                                                    >
                                                        <CheckCircle2Icon className="size-4" />
                                                        Confirmar
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
