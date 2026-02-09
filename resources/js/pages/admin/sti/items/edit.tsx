import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    ITEM_ACCION_LABELS,
    ITEM_ESTADO_COLORS,
    ITEM_ESTADO_LABELS,
    type StiItem,
    type StiItemAccion,
    type StiItemEstado,
    type StiItemHistorial,
    type StiItemTipo,
} from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon, MonitorIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    item: StiItem & { historial: StiItemHistorial[] };
    tipos: StiItemTipo[];
};

export default function ItemsEdit({ item, tipos }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'historial'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Inventario', href: '/admin/sti/items' },
        { title: item.descripcion, href: `/admin/sti/items/${item.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: item.descripcion,
        tipo_id: item.tipo_id.toString(),
        costo: Number(item.costo),
        no_serie: item.no_serie ?? '',
        estado: item.estado,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/sti/items/${item.id}`);
    };

    const isAsignado = item.estado === 'instalado' && item.grupo;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${item.descripcion}`} />

            <div className="space-y-6 p-6">
                {/* Tabs */}
                <div className="tabs tabs-boxed w-3/4">
                    <button
                        type="button"
                        className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('datos')}
                    >
                        Datos del Item
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'historial' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('historial')}
                    >
                        Historial ({item.historial?.length ?? 0})
                    </button>
                </div>

                {/* Tab: Datos */}
                {activeTab === 'datos' && (
                    <div className="w-3/4 space-y-6">
                        {/* Estado actual */}
                        <div className="flex items-center gap-4">
                            <span className={`badge ${ITEM_ESTADO_COLORS[item.estado] ?? ''}`}>
                                {ITEM_ESTADO_LABELS[item.estado] ?? item.estado}
                            </span>
                            {isAsignado && (
                                <Link
                                    href={`/admin/sti/equipos/${item.grupo?.equipo_id}/edit`}
                                    className="flex items-center gap-1 text-sm text-base-content/70 hover:underline"
                                >
                                    <MonitorIcon className="size-4" />
                                    Asignado a: <strong>{item.grupo?.equipo?.descripcion}</strong>
                                </Link>
                            )}
                        </div>

                        {/* Formulario de datos */}
                        <div>
                            <div className="mb-6 flex items-center justify-between">
                                <h1 className="text-2xl font-semibold">Editar Item</h1>
                                <DeleteDialog
                                    title="Eliminar item"
                                    description={`¿Estas seguro de eliminar el item "${item.descripcion}"? Esta accion no se puede deshacer.`}
                                    deleteUrl={`/admin/sti/items/${item.id}`}
                                />
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                    <Input
                                        id="descripcion"
                                        value={data.descripcion}
                                        onChange={(e) => setData('descripcion', e.target.value)}
                                        placeholder="Descripcion del item"
                                    />
                                </FormField>

                                <FormField label="Tipo" htmlFor="tipo_id" error={errors.tipo_id} required>
                                    <Select
                                        id="tipo_id"
                                        value={data.tipo_id}
                                        onValueChange={(value) => setData('tipo_id', value)}
                                    >
                                        {tipos.map((tipo) => (
                                            <option key={tipo.id} value={tipo.id}>
                                                {tipo.descripcion}
                                            </option>
                                        ))}
                                    </Select>
                                </FormField>

                                <FormField label="Costo" htmlFor="costo" error={errors.costo} required>
                                    <Input
                                        id="costo"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.costo}
                                        onChange={(e) => setData('costo', parseFloat(e.target.value) || 0)}
                                        placeholder="0.00"
                                    />
                                </FormField>

                                <FormField label="No. Serie" htmlFor="no_serie" error={errors.no_serie}>
                                    <Input
                                        id="no_serie"
                                        value={data.no_serie}
                                        onChange={(e) => setData('no_serie', e.target.value)}
                                        placeholder="Numero de serie"
                                    />
                                </FormField>

                                <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                                    <Select
                                        id="estado"
                                        value={data.estado}
                                        onValueChange={(value) => setData('estado', value as StiItemEstado)}
                                    >
                                        {(Object.entries(ITEM_ESTADO_LABELS) as [StiItemEstado, string][]).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                </FormField>

                                <div className="flex justify-end gap-2">
                                    <Button variant="outline" asChild>
                                        <Link href="/admin/sti/items">Cancelar</Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                                        Guardar
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Tab: Historial */}
                {activeTab === 'historial' && (
                    <div className="w-3/4">
                        <h2 className="mb-4 text-lg font-semibold">Historial de Movimientos</h2>
                        {item.historial && item.historial.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Accion</th>
                                            <th>Equipo</th>
                                            <th>Tecnico</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {item.historial.map((h) => (
                                            <tr key={h.id}>
                                                <td>
                                                    {new Date(h.fecha).toLocaleDateString('es-MX', {
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    })}
                                                </td>
                                                <td>
                                                    <span className="badge badge-sm badge-outline">
                                                        {ITEM_ACCION_LABELS[h.accion as StiItemAccion] ?? h.accion}
                                                    </span>
                                                </td>
                                                <td>{h.equipo?.descripcion ?? '-'}</td>
                                                <td>{h.tecnico?.descripcion ?? '-'}</td>
                                                <td className="max-w-xs truncate">{h.observaciones ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay movimientos registrados para este item.</p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
