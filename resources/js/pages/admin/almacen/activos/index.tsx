import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDeAlmacen } from '@/lib/alm/almacenes';
import type { BreadcrumbItem } from '@/types';
import type { AlmActivoEstatus, AlmAlmacenOpcion, AlmOpcion, AlmProductoOpcion, PaginatedData } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
];

const CLASE_ESTATUS: Record<AlmActivoEstatus, string> = {
    disponible: 'badge-success',
    prestado: 'badge-warning',
    en_reparacion: 'badge-info',
    baja: 'badge-ghost',
};

const moneda = (n: number) => n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

type ActivoFila = {
    id: number;
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    no_serie: string;
    codigo_barras: string | null;
    marca: string | null;
    modelo: string | null;
    id_mantenimiento: string | null;
    almacen_id: number;
    almacen: string | null;
    obra: string | null;
    ubicacion_id: number | null;
    ubicacion: string | null;
    costo: number;
    estatus: AlmActivoEstatus;
    estatus_etiqueta: string;
    condicion: string | null;
};

type Props = {
    activos: PaginatedData<ActivoFila>;
    filters: { almacen_id?: string; articulo_id?: string; estatus?: string; search?: string };
    resumen: { vigentes: number; disponibles: number; prestadas: number; en_reparacion: number; baja: number };
    ubicacionesPorAlmacen: Record<number, { id: number; ruta: string }[]>;
    almacenes: AlmAlmacenOpcion[];
    articulos: AlmProductoOpcion[];
    estatuses: AlmOpcion[];
};

/**
 * El padrón de piezas: una fila por número de serie.
 *
 * No es un inventario aparte — cada pieza suma 1 a la existencia de su artículo.
 * Lo que responde esta pantalla es cuál es cuál y en qué anda, que es justo lo
 * que el saldo por cantidad no puede decir.
 */
export default function ActivosIndex({
    activos,
    filters,
    resumen,
    ubicacionesPorAlmacen,
    almacenes,
    articulos,
    estatuses,
}: Props) {
    const [editando, setEditando] = useState<ActivoFila | null>(null);
    const [dandoBaja, setDandoBaja] = useState<ActivoFila | null>(null);

    const filtrar = (cambio: Record<string, string | undefined>) =>
        router.get('/admin/almacen/activos', { ...filters, ...cambio, page: undefined }, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Activos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Una fila por número de serie. La marca, el modelo y el id de mantenimiento son de la pieza:
                            el catálogo dice qué es, y esto con qué se cumplió.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/activos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Dar de alta piezas
                    </ButtonLink>
                </div>

                <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">En el almacén</p>
                        <p className="font-mono text-xl">{resumen.vigentes}</p>
                    </div>
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">Se pueden entregar</p>
                        <p className="text-success font-mono text-xl">{resumen.disponibles}</p>
                    </div>
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">Afuera</p>
                        <p className="text-warning font-mono text-xl">{resumen.prestadas}</p>
                    </div>
                    <div className="rounded-box border-base-300 border p-3">
                        <p className="text-base-content/60 text-xs">En reparación</p>
                        <p className="font-mono text-xl">{resumen.en_reparacion}</p>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-48">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select
                            value={filters.almacen_id ?? ''}
                            onValueChange={(v) => filtrar({ almacen_id: v || undefined })}
                            placeholder="Todos"
                        >
                            {almacenes.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {etiquetaDeAlmacen(a)}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-60">
                        <label className="label label-text text-xs">Artículo</label>
                        <Select
                            value={filters.articulo_id ?? ''}
                            onValueChange={(v) => filtrar({ articulo_id: v || undefined })}
                            placeholder="Todos"
                        >
                            {articulos.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                    {a.codigo} — {a.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-40">
                        <label className="label label-text text-xs">Estado</label>
                        <Select
                            value={filters.estatus ?? ''}
                            onValueChange={(v) => filtrar({ estatus: v || undefined })}
                            placeholder="Todos"
                        >
                            {estatuses.map((e) => (
                                <SelectItem key={e.value} value={e.value}>
                                    {e.label}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="max-w-sm flex-1">
                        <label className="label label-text text-xs">Buscar</label>
                        <Input
                            defaultValue={filters.search ?? ''}
                            placeholder="Serie, marca, modelo o id de mantenimiento..."
                            onChange={(e) => filtrar({ search: e.target.value || undefined })}
                        />
                    </div>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>No. de serie</th>
                                <th>Artículo</th>
                                <th>Marca y modelo</th>
                                <th>Id de mto.</th>
                                <th>Almacén</th>
                                <th>Ubicación</th>
                                <th className="text-right">Costo</th>
                                <th>Estado</th>
                                <th>Condición</th>
                                <th className="w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {activos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-base-content/50 py-6 text-center">
                                        No hay piezas con esos filtros.
                                    </td>
                                </tr>
                            ) : (
                                activos.data.map((a) => (
                                    <tr key={a.id} className={a.estatus === 'baja' ? 'hover opacity-50' : 'hover'}>
                                        <td className="font-mono font-medium">{a.no_serie}</td>
                                        <td>
                                            <Link
                                                href={`/admin/almacen/articulos/${a.articulo_id}`}
                                                className="link link-hover font-mono text-xs"
                                            >
                                                {a.codigo}
                                            </Link>
                                            <span className="block text-sm">{a.descripcion}</span>
                                        </td>
                                        <td className="text-sm">
                                            {[a.marca, a.modelo].filter(Boolean).join(' · ') || (
                                                <span className="text-base-content/40">—</span>
                                            )}
                                        </td>
                                        <td className="text-base-content/60 font-mono text-xs">
                                            {a.id_mantenimiento ?? '—'}
                                        </td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost font-mono">{a.almacen}</span>
                                            {a.obra && (
                                                <span className="text-base-content/60 ml-1 text-xs">{a.obra}</span>
                                            )}
                                        </td>
                                        <td className="text-base-content/60 text-sm">
                                            {a.ubicacion ?? <span className="text-base-content/40">—</span>}
                                        </td>
                                        <td className="text-right font-mono">{moneda(a.costo)}</td>
                                        <td>
                                            <span className={`badge badge-sm ${CLASE_ESTATUS[a.estatus]}`}>
                                                {a.estatus_etiqueta}
                                            </span>
                                        </td>
                                        <td className="text-base-content/60 text-sm">{a.condicion}</td>
                                        <td>
                                            {a.estatus !== 'baja' && (
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Corregir la pieza"
                                                        onClick={() => setEditando(a)}
                                                    >
                                                        <PencilIcon className="size-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn btn-ghost btn-xs"
                                                        title="Retirar la pieza"
                                                        onClick={() => setDandoBaja(a)}
                                                    >
                                                        <Trash2Icon className="size-3.5" />
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {activos.links.length > 3 && (
                    <div className="join mt-4 flex justify-center">
                        {activos.links.map((link, i) =>
                            link.url === null ? (
                                <button key={i} className="join-item btn btn-sm btn-disabled">
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </button>
                            ) : (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`join-item btn btn-sm ${link.active ? 'btn-active' : ''}`}
                                    preserveState
                                >
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Link>
                            ),
                        )}
                    </div>
                )}
            </div>

            {editando && (
                <ModalCorregir
                    activo={editando}
                    ubicaciones={ubicacionesPorAlmacen[editando.almacen_id] ?? []}
                    estatuses={estatuses.filter((e) => e.value !== 'baja')}
                    onCerrar={() => setEditando(null)}
                />
            )}

            {dandoBaja && <ModalBaja activo={dandoBaja} onCerrar={() => setDandoBaja(null)} />}
        </AppLayout>
    );
}

/**
 * La pieza se corrige en un modal y no en una ficha propia: son seis campos, y
 * una pantalla aparte sólo agregaría un clic para cambiar una condición.
 */
function ModalCorregir({
    activo,
    ubicaciones,
    estatuses,
    onCerrar,
}: {
    activo: ActivoFila;
    ubicaciones: { id: number; ruta: string }[];
    estatuses: AlmOpcion[];
    onCerrar: () => void;
}) {
    const form = useForm({
        no_serie: activo.no_serie,
        marca: activo.marca ?? '',
        modelo: activo.modelo ?? '',
        id_mantenimiento: activo.id_mantenimiento ?? '',
        codigo_barras: activo.codigo_barras ?? '',
        ubicacion_id: activo.ubicacion_id === null ? '' : String(activo.ubicacion_id),
        condicion: activo.condicion ?? '',
        estatus: activo.estatus as string,
    });

    return (
        <dialog className="modal modal-open">
            <div className="modal-box max-w-2xl">
                <h3 className="text-lg font-semibold">Corregir pieza</h3>
                <p className="text-base-content/60 mb-4 text-sm">
                    {activo.codigo} — {activo.descripcion}. El almacén no se cambia aquí: mover de bodega es una
                    transferencia.
                </p>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`/admin/almacen/activos/${activo.id}`, {
                            preserveScroll: true,
                            onSuccess: onCerrar,
                        });
                    }}
                    className="grid grid-cols-1 gap-3 md:grid-cols-2"
                >
                    <label className="form-control">
                        <span className="label label-text">No. de serie</span>
                        <Input
                            value={form.data.no_serie}
                            onChange={(e) => form.setData('no_serie', e.target.value)}
                            className="font-mono"
                        />
                        {form.errors.no_serie && <span className="text-error text-xs">{form.errors.no_serie}</span>}
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Código de barras</span>
                        <Input
                            value={form.data.codigo_barras}
                            onChange={(e) => form.setData('codigo_barras', e.target.value)}
                            className="font-mono"
                        />
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Marca</span>
                        <Input value={form.data.marca} onChange={(e) => form.setData('marca', e.target.value)} />
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Modelo</span>
                        <Input value={form.data.modelo} onChange={(e) => form.setData('modelo', e.target.value)} />
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Id de mantenimiento</span>
                        <Input
                            value={form.data.id_mantenimiento}
                            onChange={(e) => form.setData('id_mantenimiento', e.target.value)}
                            className="font-mono"
                        />
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Ubicación</span>
                        <Select
                            value={form.data.ubicacion_id}
                            onValueChange={(v) => form.setData('ubicacion_id', v)}
                            disabled={ubicaciones.length === 0}
                        >
                            <SelectItem value="">Sin acomodar</SelectItem>
                            {ubicaciones.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.ruta}
                                </SelectItem>
                            ))}
                        </Select>
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Estado</span>
                        <Select value={form.data.estatus} onValueChange={(v) => form.setData('estatus', v)}>
                            {estatuses.map((e) => (
                                <SelectItem key={e.value} value={e.value}>
                                    {e.label}
                                </SelectItem>
                            ))}
                        </Select>
                        {form.errors.estatus && <span className="text-error text-xs">{form.errors.estatus}</span>}
                    </label>

                    <label className="form-control">
                        <span className="label label-text">Condición</span>
                        <Input
                            value={form.data.condicion}
                            onChange={(e) => form.setData('condicion', e.target.value)}
                            placeholder="Buena, carbones gastados..."
                        />
                    </label>

                    <div className="modal-action md:col-span-2">
                        <button type="button" className="btn btn-ghost" onClick={onCerrar}>
                            Cancelar
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onCerrar} />
        </dialog>
    );
}

/** Retirar la pieza descarga existencia, así que va aparte de la corrección. */
function ModalBaja({ activo, onCerrar }: { activo: ActivoFila; onCerrar: () => void }) {
    const form = useForm({ motivo: '' });

    return (
        <dialog className="modal modal-open">
            <div className="modal-box">
                <h3 className="text-lg font-semibold">Retirar {activo.no_serie}</h3>
                <p className="text-base-content/60 mb-4 text-sm">
                    Baja definitiva: resta 1 a la existencia del artículo y deja su asiento en el kardex. La pieza no se
                    borra, su historia se conserva.
                </p>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/admin/almacen/activos/${activo.id}/baja`, {
                            preserveScroll: true,
                            onSuccess: onCerrar,
                        });
                    }}
                >
                    <label className="form-control">
                        <span className="label label-text">¿Por qué se retira?</span>
                        <Input
                            value={form.data.motivo}
                            onChange={(e) => form.setData('motivo', e.target.value)}
                            placeholder="Se quemó el motor, se perdió en obra..."
                            autoFocus
                        />
                        {form.errors.motivo && <span className="text-error text-xs">{form.errors.motivo}</span>}
                    </label>

                    <div className="modal-action">
                        <button type="button" className="btn btn-ghost" onClick={onCerrar}>
                            Cancelar
                        </button>
                        <button type="submit" className="btn btn-error" disabled={form.processing}>
                            Retirar pieza
                        </button>
                    </div>
                </form>
            </div>
            <div className="modal-backdrop" onClick={onCerrar} />
        </dialog>
    );
}
