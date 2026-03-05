import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    CRITICIDAD_LABELS,
    ITEM_ESTADO_COLORS,
    ITEM_ESTADO_LABELS,
    type StiCostoMantenimiento,
    type StiCriticidad,
    type StiEquipo,
    type StiGrupo,
    type StiItem,
    type StiItemTipo,
    type StiMantenimiento,
    type StiStatus,
    type StiTecnico,
    type StiTicket,
    type StiTicketHistorial,
} from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardListIcon, Loader2Icon, PackageIcon, PackageMinusIcon, PackagePlusIcon, WrenchIcon } from 'lucide-react';
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
        grupos: (StiGrupo & {
            item: StiItem & { tipo: StiItemTipo };
        })[];
    };
    itemsDisponibles: (StiItem & { tipo: StiItemTipo })[];
    tecnicos: StiTecnico[];
};

export default function EquiposEdit({ equipo, itemsDisponibles, tecnicos }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'tickets' | 'mantenimientos' | 'inventario'>('datos');
    const [retirarItemId, setRetirarItemId] = useState<number | null>(null);

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

    const asignarForm = useForm({
        equipo_id: equipo.id.toString(),
        item_id: '',
        tecnico_id: '',
        observaciones: '',
    });

    const retirarForm = useForm({
        tecnico_id: '',
        observaciones: '',
    });

    const handleAsignar = (e: FormEvent) => {
        e.preventDefault();
        if (!asignarForm.data.item_id) {
            return;
        }
        asignarForm.post(`/admin/sti/items/${asignarForm.data.item_id}/asignar`, {
            preserveScroll: true,
            onSuccess: () => {
                asignarForm.reset('item_id', 'tecnico_id', 'observaciones');
            },
        });
    };

    const handleRetirar = (e: FormEvent, itemId: number) => {
        e.preventDefault();
        retirarForm.post(`/admin/sti/items/${itemId}/retirar`, {
            preserveScroll: true,
            onSuccess: () => {
                setRetirarItemId(null);
                retirarForm.reset();
            },
        });
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
                    <button
                        type="button"
                        className={`tab ${activeTab === 'inventario' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('inventario')}
                    >
                        <PackageIcon className="mr-1 size-4" />
                        Inventario ({equipo.grupos?.length ?? 0})
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
                                                        <span
                                                            className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                                            style={{ backgroundColor: currentStatus?.color ?? '#6b7280' }}
                                                        >
                                                            {currentStatus?.descripcion ?? 'Sin estado'}
                                                        </span>
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
                                                        {new Date(mant.fecha_programada.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX', {
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
                                                            ? new Date(mant.fecha_realizado.split('T')[0] + 'T00:00:00').toLocaleDateString('es-MX', {
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

                {/* Tab: Inventario */}
                {activeTab === 'inventario' && (
                    <div className="w-3/4 space-y-6">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <PackageIcon className="size-5" />
                            Items Asignados
                        </h2>

                        {/* Tabla de items asignados */}
                        {equipo.grupos && equipo.grupos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Descripcion</th>
                                            <th>Tipo</th>
                                            <th>No. Serie</th>
                                            <th className="text-right">Costo</th>
                                            <th>Estado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {equipo.grupos.map((grupo) => (
                                            <>
                                                <tr key={grupo.id}>
                                                    <td>
                                                        <Link
                                                            href={`/admin/sti/items/${grupo.item.id}/edit`}
                                                            className="link link-hover"
                                                        >
                                                            {grupo.item.descripcion}
                                                        </Link>
                                                    </td>
                                                    <td>{grupo.item.tipo?.descripcion ?? '-'}</td>
                                                    <td className="font-mono text-sm">{grupo.item.no_serie ?? '-'}</td>
                                                    <td className="text-right font-mono">
                                                        ${Number(grupo.item.costo).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                    </td>
                                                    <td>
                                                        <span className={`badge badge-sm ${ITEM_ESTADO_COLORS[grupo.item.estado] ?? ''}`}>
                                                            {ITEM_ESTADO_LABELS[grupo.item.estado] ?? grupo.item.estado}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setRetirarItemId(retirarItemId === grupo.item.id ? null : grupo.item.id)
                                                            }
                                                        >
                                                            <PackageMinusIcon className="size-4" />
                                                            Retirar
                                                        </Button>
                                                    </td>
                                                </tr>
                                                {retirarItemId === grupo.item.id && (
                                                    <tr key={`retirar-${grupo.id}`}>
                                                        <td colSpan={6}>
                                                            <form
                                                                onSubmit={(e) => handleRetirar(e, grupo.item.id)}
                                                                className="flex items-end gap-4 rounded-lg bg-base-200 p-4"
                                                            >
                                                                <FormField
                                                                    label="Tecnico"
                                                                    htmlFor={`retirar_tecnico_${grupo.item.id}`}
                                                                    error={retirarForm.errors.tecnico_id}
                                                                >
                                                                    <Select
                                                                        id={`retirar_tecnico_${grupo.item.id}`}
                                                                        value={retirarForm.data.tecnico_id}
                                                                        onValueChange={(value) => retirarForm.setData('tecnico_id', value)}
                                                                        placeholder="Seleccionar tecnico"
                                                                    >
                                                                        <option value="">Sin tecnico</option>
                                                                        {tecnicos.map((tecnico) => (
                                                                            <option key={tecnico.id} value={tecnico.id}>
                                                                                {tecnico.descripcion}
                                                                            </option>
                                                                        ))}
                                                                    </Select>
                                                                </FormField>
                                                                <FormField
                                                                    label="Observaciones"
                                                                    htmlFor={`retirar_obs_${grupo.item.id}`}
                                                                    error={retirarForm.errors.observaciones}
                                                                >
                                                                    <Input
                                                                        id={`retirar_obs_${grupo.item.id}`}
                                                                        value={retirarForm.data.observaciones}
                                                                        onChange={(e) => retirarForm.setData('observaciones', e.target.value)}
                                                                        placeholder="Observaciones opcionales"
                                                                    />
                                                                </FormField>
                                                                <Button type="submit" variant="destructive" disabled={retirarForm.processing}>
                                                                    {retirarForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                                                    Confirmar
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    onClick={() => {
                                                                        setRetirarItemId(null);
                                                                        retirarForm.reset();
                                                                    }}
                                                                >
                                                                    Cancelar
                                                                </Button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                )}
                                            </>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan={3} className="text-right font-medium">
                                                Total:
                                            </td>
                                            <td className="text-right font-mono font-bold">
                                                $
                                                {equipo.grupos
                                                    .reduce((sum, g) => sum + Number(g.item.costo), 0)
                                                    .toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td colSpan={2}></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay items asignados a este equipo.</p>
                        )}

                        {/* Asignar item */}
                        <div className="divider" />
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <PackagePlusIcon className="size-5" />
                            Asignar Item
                        </h2>
                        {asignarForm.errors.asignar && (
                            <div className="alert alert-error mb-4">
                                <span>{asignarForm.errors.asignar}</span>
                            </div>
                        )}
                        {itemsDisponibles.length > 0 ? (
                            <form onSubmit={handleAsignar} className="space-y-4">
                                <FormField label="Item" htmlFor="asignar_item_id" required>
                                    <Select
                                        id="asignar_item_id"
                                        value={asignarForm.data.item_id}
                                        onValueChange={(value) => asignarForm.setData('item_id', value)}
                                        placeholder="Seleccionar item"
                                    >
                                        {itemsDisponibles.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.descripcion} ({item.tipo?.descripcion}){item.no_serie ? ` - ${item.no_serie}` : ''}
                                            </option>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField label="Tecnico" htmlFor="asignar_tecnico_id">
                                    <Select
                                        id="asignar_tecnico_id"
                                        value={asignarForm.data.tecnico_id}
                                        onValueChange={(value) => asignarForm.setData('tecnico_id', value)}
                                        placeholder="Seleccionar tecnico"
                                    >
                                        <option value="">Sin tecnico</option>
                                        {tecnicos.map((tecnico) => (
                                            <option key={tecnico.id} value={tecnico.id}>
                                                {tecnico.descripcion}
                                            </option>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField label="Observaciones" htmlFor="asignar_observaciones">
                                    <Input
                                        id="asignar_observaciones"
                                        value={asignarForm.data.observaciones}
                                        onChange={(e) => asignarForm.setData('observaciones', e.target.value)}
                                        placeholder="Observaciones opcionales"
                                    />
                                </FormField>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={asignarForm.processing || !asignarForm.data.item_id}>
                                        {asignarForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                                        <PackagePlusIcon className="size-4" />
                                        Asignar
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <p className="text-sm text-gray-500">No hay items disponibles para asignar.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
