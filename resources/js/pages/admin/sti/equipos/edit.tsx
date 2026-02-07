import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CRITICIDAD_LABELS, type StiCostoMantenimiento, type StiCriticidad, type StiEquipo, type StiMantenimiento, type StiStatus, type StiTicket, type StiTicketHistorial } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardListIcon, Loader2Icon, WrenchIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    equipo: StiEquipo & {
        tickets: (StiTicket & {
            historial: (StiTicketHistorial & { status: StiStatus })[];
            costos: StiCostoMantenimiento[];
        })[];
        mantenimientos: (StiMantenimiento & {
            costos: StiCostoMantenimiento[];
        })[];
    };
};

export default function EquiposEdit({ equipo }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'tickets' | 'mantenimientos'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Equipos', href: '/admin/sti/equipos' },
        { title: equipo.descripcion, href: `/admin/sti/equipos/${equipo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: equipo.descripcion,
        serie: equipo.serie ?? '',
        marca: equipo.marca ?? '',
        factor_criticidad: equipo.factor_criticidad,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/equipos/${equipo.id}`);
    };

    const calcularTotalCostos = (costos: StiCostoMantenimiento[]) => {
        return costos.reduce((sum, c) => sum + c.cantidad, 0);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${equipo.descripcion}`} />

            <div className="space-y-6 p-6">
                {/* Tabs */}
                <div className="tabs tabs-boxed w-3/4">
                    <button
                        type="button"
                        className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('datos')}
                    >
                        Datos del Equipo
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'tickets' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('tickets')}
                    >
                        <ClipboardListIcon className="mr-1 size-4" />
                        Tickets ({equipo.tickets?.length ?? 0})
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'mantenimientos' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('mantenimientos')}
                    >
                        <WrenchIcon className="mr-1 size-4" />
                        Mantenimientos ({equipo.mantenimientos?.length ?? 0})
                    </button>
                </div>

                {/* Tab: Datos del Equipo */}
                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Equipo</h1>
                            <DeleteDialog
                                title="Eliminar equipo"
                                description={`¿Estas seguro de eliminar el equipo "${equipo.descripcion}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/sti/equipos/${equipo.id}`}
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre o descripcion del equipo"
                                />
                            </FormField>

                            <FormField label="Numero de Serie" htmlFor="serie" error={errors.serie}>
                                <Input
                                    id="serie"
                                    value={data.serie}
                                    onChange={(e) => setData('serie', e.target.value)}
                                    placeholder="Numero de serie"
                                />
                            </FormField>

                            <FormField label="Marca" htmlFor="marca" error={errors.marca}>
                                <Input
                                    id="marca"
                                    value={data.marca}
                                    onChange={(e) => setData('marca', e.target.value)}
                                    placeholder="Marca del equipo"
                                />
                            </FormField>

                            <FormField label="Factor de Criticidad" htmlFor="factor_criticidad" error={errors.factor_criticidad} required>
                                <Select
                                    id="factor_criticidad"
                                    value={data.factor_criticidad.toString()}
                                    onValueChange={(value) => setData('factor_criticidad', parseInt(value) as StiCriticidad)}
                                >
                                    {Object.entries(CRITICIDAD_LABELS).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/equipos">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab: Historial de Tickets */}
                {activeTab === 'tickets' && (
                    <div className="w-3/4">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                            <ClipboardListIcon className="size-5" />
                            Historial de Tickets
                        </h2>
                        {equipo.tickets && equipo.tickets.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Solicitante</th>
                                            <th>Estado</th>
                                            <th className="text-right">Costos</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {equipo.tickets.map((ticket) => {
                                            const currentStatus = ticket.historial?.[0]?.status;
                                            const totalCostos = calcularTotalCostos(ticket.costos ?? []);
                                            return (
                                                <tr key={ticket.id}>
                                                    <td>#{ticket.id}</td>
                                                    <td>
                                                        {new Date(ticket.created_at).toLocaleDateString('es-MX', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        })}
                                                    </td>
                                                    <td>{ticket.nombre_solicitante}</td>
                                                    <td>
                                                        <span className="badge badge-sm">{currentStatus?.descripcion ?? 'Sin estado'}</span>
                                                    </td>
                                                    <td className="text-right font-mono">
                                                        ${totalCostos.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td>
                                                        <Link
                                                            href={`/admin/sti/tickets/${ticket.id}/edit`}
                                                            className="btn btn-ghost btn-xs"
                                                        >
                                                            Ver
                                                        </Link>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan={4} className="text-right font-medium">
                                                Total:
                                            </td>
                                            <td className="text-right font-mono font-bold">
                                                $
                                                {equipo.tickets
                                                    .reduce((sum, t) => sum + calcularTotalCostos(t.costos ?? []), 0)
                                                    .toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay tickets registrados para este equipo.</p>
                        )}
                    </div>
                )}

                {/* Tab: Historial de Mantenimientos */}
                {activeTab === 'mantenimientos' && (
                    <div className="w-3/4">
                        <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                            <WrenchIcon className="size-5" />
                            Historial de Mantenimientos
                        </h2>
                        {equipo.mantenimientos && equipo.mantenimientos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha Programada</th>
                                            <th>Estado</th>
                                            <th>Fecha Realizado</th>
                                            <th className="text-right">Costos</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {equipo.mantenimientos.map((mant) => {
                                            const totalCostos = calcularTotalCostos(mant.costos ?? []);
                                            return (
                                                <tr key={mant.id}>
                                                    <td>#{mant.id}</td>
                                                    <td>
                                                        {new Date(mant.fecha_programada + 'T00:00:00').toLocaleDateString('es-MX', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        })}
                                                    </td>
                                                    <td>
                                                        <span
                                                            className={`badge badge-sm ${mant.status === 'realizado' ? 'badge-success' : 'badge-warning'}`}
                                                        >
                                                            {mant.status === 'realizado' ? 'Realizado' : 'Pendiente'}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        {mant.fecha_realizado
                                                            ? new Date(mant.fecha_realizado + 'T00:00:00').toLocaleDateString('es-MX', {
                                                                  day: 'numeric',
                                                                  month: 'short',
                                                                  year: 'numeric',
                                                              })
                                                            : '-'}
                                                    </td>
                                                    <td className="text-right font-mono">
                                                        ${totalCostos.toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td>
                                                        <Link
                                                            href={`/admin/sti/mantenimientos/${mant.id}/edit`}
                                                            className="btn btn-ghost btn-xs"
                                                        >
                                                            Ver
                                                        </Link>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan={4} className="text-right font-medium">
                                                Total:
                                            </td>
                                            <td className="text-right font-mono font-bold">
                                                $
                                                {equipo.mantenimientos
                                                    .reduce((sum, m) => sum + calcularTotalCostos(m.costos ?? []), 0)
                                                    .toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay mantenimientos registrados para este equipo.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
