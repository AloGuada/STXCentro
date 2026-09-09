import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, TriangleAlertIcon, Undo2Icon } from 'lucide-react';
import { useState } from 'react';
import { BotonFormato } from '@/components/alm/boton-formato';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const hoy = () => new Date().toISOString().slice(0, 10);
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Detalle = {
    id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    por_pieza: boolean;
    no_serie: string | null;
    marca_modelo: string;
    cantidad: number;
    cantidad_devuelta: number;
    pendiente: number;
    condicion_salida: string | null;
    condicion_retorno: string | null;
    devuelto_en: string | null;
    recibio: string | null;
    observaciones: string | null;
};

type Props = {
    prestamo: {
        id: number;
        folio: string;
        almacen: string | null;
        almacen_nombre: string | null;
        responsable: string | null;
        destino: string;
        obra: string | null;
        fecha_salida: string;
        fecha_retorno_esperada: string | null;
        dias_fuera: number;
        vencido: boolean;
        estatus: 'abierto' | 'cerrado';
        estatus_etiqueta: string;
        autorizo: string | null;
        entrego: string | null;
        cerrado_en: string | null;
        observaciones: string | null;
        pendiente: number;
    };
    detalles: Detalle[];
    usuarios: { id: string; name: string }[];
    puede_devolver: boolean;
};

/** Lo que se captura de cada renglón que vuelve. */
type Retorno = { vuelve: boolean; cantidad: string; condicion_retorno: string; en_reparacion: boolean };

/**
 * La ficha del resguardo y, si sigue abierto, la devolución de sus renglones.
 * Devolver aquí es lo mismo que desde Devoluciones: cierra renglones, y el
 * resguardo cierra solo cuando no le queda nada afuera.
 */
export default function PrestamoShow({ prestamo, detalles, usuarios, puede_devolver }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const abierto = prestamo.estatus === 'abierto';
    const [devolviendo, setDevolviendo] = useState(false);

    const pendientes = detalles.filter((d) => d.pendiente > 0);

    const [retornos, setRetornos] = useState<Record<number, Retorno>>(() =>
        Object.fromEntries(
            pendientes.map((d) => [
                d.id,
                { vuelve: false, cantidad: String(d.pendiente), condicion_retorno: '', en_reparacion: false },
            ]),
        ),
    );

    const form = useForm<{
        fecha: string;
        recibido_por: string;
        renglones: { detalle_id: number; cantidad: number | string; condicion_retorno: string | null; en_reparacion: boolean }[];
    }>({
        fecha: hoy(),
        recibido_por: '',
        renglones: [],
    });

    const editar = (id: number, cambio: Partial<Retorno>) =>
        setRetornos((prev) => ({ ...prev, [id]: { ...prev[id], ...cambio } }));

    const queVuelven = pendientes.filter((d) => retornos[d.id]?.vuelve);

    const errorDe = (i: number, campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`renglones.${i}.${campo}`];

    const devolver = (e: React.FormEvent) => {
        e.preventDefault();

        form.transform((d) => ({
            fecha: d.fecha,
            recibido_por: d.recibido_por || null,
            renglones: queVuelven.map((det) => ({
                detalle_id: det.id,
                cantidad: det.por_pieza ? 1 : retornos[det.id].cantidad,
                condicion_retorno: retornos[det.id].condicion_retorno || null,
                en_reparacion: det.por_pieza ? retornos[det.id].en_reparacion : false,
            })),
        }));

        form.post('/admin/almacen/devoluciones', { preserveScroll: true, onSuccess: () => setDevolviendo(false) });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Activos', href: '/admin/almacen/activos' },
        { title: 'Préstamos', href: '/admin/almacen/prestamos' },
        { title: prestamo.folio, href: `/admin/almacen/prestamos/${prestamo.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Resguardo ${prestamo.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{prestamo.folio}</h1>
                            <span className={`badge badge-sm ${abierto ? 'badge-warning' : 'badge-success'}`}>
                                {prestamo.estatus_etiqueta}
                            </span>
                            {prestamo.vencido && (
                                <span className="badge badge-sm badge-error">
                                    <TriangleAlertIcon className="mr-1 size-3" />
                                    Vencido
                                </span>
                            )}
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Almacén {prestamo.almacen}
                            {prestamo.almacen_nombre ? ` — ${prestamo.almacen_nombre}` : ''} · responde{' '}
                            <strong>{prestamo.responsable}</strong> · en {prestamo.destino}
                            {prestamo.obra ? ` (${prestamo.obra})` : ''}
                        </p>
                        <p className="text-base-content/60 text-sm">
                            Salió el {prestamo.fecha_salida}
                            {prestamo.fecha_retorno_esperada
                                ? ` · debe volver el ${prestamo.fecha_retorno_esperada}`
                                : ' · sin fecha de retorno'}{' '}
                            · {prestamo.dias_fuera} día(s) {abierto ? 'afuera' : 'que estuvo afuera'}
                            {prestamo.autorizo ? ` · autorizó ${prestamo.autorizo}` : ''}
                        </p>
                        {prestamo.observaciones && <p className="mt-1 text-sm">{prestamo.observaciones}</p>}
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/prestamos" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <BotonFormato href={`/admin/almacen/prestamos/${prestamo.id}/pdf`} etiqueta="Resguardo" />
                        {abierto && puede_devolver && !devolviendo && (
                            <Button variant="primary" onClick={() => setDevolviendo(true)}>
                                <Undo2Icon className="size-4" />
                                Devolver
                            </Button>
                        )}
                    </div>
                </div>

                {flash?.success && (
                    <div className="alert alert-success mb-4">
                        <span>{flash.success}</span>
                    </div>
                )}

                <form onSubmit={devolver}>
                    {devolviendo && (
                        <div className="rounded-box border-base-300 mb-4 border p-4">
                            <h2 className="mb-3 font-medium">Qué vuelve hoy</h2>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <label className="form-control">
                                    <span className="label label-text">Fecha de devolución</span>
                                    <Input
                                        type="date"
                                        max={hoy()}
                                        value={form.data.fecha}
                                        onChange={(e) => form.setData('fecha', e.target.value)}
                                    />
                                    {form.errors.fecha && <span className="text-error text-xs">{form.errors.fecha}</span>}
                                </label>
                                <label className="form-control md:col-span-2">
                                    <span className="label label-text">Recibió (quién del almacén revisó cómo vuelve)</span>
                                    <SearchSelect
                                        options={usuarios.map((u) => ({ value: u.id, label: u.name }))}
                                        value={form.data.recibido_por}
                                        onValueChange={(v) => form.setData('recibido_por', v)}
                                        placeholder="Vacío: quien captura."
                                        maxOptions={30}
                                    />
                                </label>
                            </div>
                            <p className="text-base-content/60 mt-2 text-sm">
                                Palomea en la tabla lo que entrega; lo demás sigue a nombre de {prestamo.responsable}.
                            </p>
                            {typeof form.errors.renglones === 'string' && (
                                <p className="text-error mt-1 text-sm">{form.errors.renglones}</p>
                            )}
                        </div>
                    )}

                    <div className="rounded-box border-base-300 overflow-x-auto border">
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    {devolviendo && <th className="w-10">Vuelve</th>}
                                    <th>Artículo</th>
                                    <th>Serie</th>
                                    <th className="text-right">Prestado</th>
                                    <th className="text-right">Afuera</th>
                                    <th>Salió en</th>
                                    <th>{devolviendo ? 'Cómo vuelve' : 'Volvió en'}</th>
                                    {!devolviendo && <th>Devuelto</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((d) => {
                                    const retorno = retornos[d.id];
                                    const editable = devolviendo && d.pendiente > 0;
                                    const i = queVuelven.findIndex((q) => q.id === d.id);

                                    return (
                                        <tr key={d.id} className={retorno?.vuelve ? 'bg-success/10' : 'hover'}>
                                            {devolviendo && (
                                                <td>
                                                    {d.pendiente > 0 && (
                                                        <input
                                                            type="checkbox"
                                                            className="checkbox checkbox-sm"
                                                            checked={retorno?.vuelve ?? false}
                                                            onChange={(e) => editar(d.id, { vuelve: e.target.checked })}
                                                            aria-label={`Devolver ${d.no_serie ?? d.descripcion ?? ''}`}
                                                        />
                                                    )}
                                                </td>
                                            )}
                                            <td>
                                                <span className="font-mono text-xs">{d.codigo}</span>
                                                <span className="block text-sm">{d.descripcion}</span>
                                                {d.marca_modelo && (
                                                    <span className="text-base-content/50 block text-xs">{d.marca_modelo}</span>
                                                )}
                                                {d.observaciones && (
                                                    <span className="text-base-content/60 block text-xs">{d.observaciones}</span>
                                                )}
                                            </td>
                                            <td className="font-mono text-xs">
                                                {d.no_serie ?? <span className="text-base-content/40">por cantidad</span>}
                                            </td>
                                            <td className="text-right font-mono">
                                                {numero(d.cantidad)}{' '}
                                                <span className="text-base-content/40 text-xs">{d.unidad}</span>
                                            </td>
                                            <td className="text-right font-mono">
                                                {editable && !d.por_pieza && retorno?.vuelve ? (
                                                    <>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            max={d.pendiente}
                                                            step="1"
                                                            className="input-sm w-24 text-right"
                                                            value={retorno.cantidad}
                                                            onChange={(e) => editar(d.id, { cantidad: e.target.value })}
                                                            error={Boolean(i >= 0 && errorDe(i, 'cantidad'))}
                                                        />
                                                        <span className="text-base-content/50 block text-xs">
                                                            vuelven de {numero(d.pendiente)}
                                                        </span>
                                                        {i >= 0 && errorDe(i, 'cantidad') && (
                                                            <span className="text-error text-xs">{errorDe(i, 'cantidad')}</span>
                                                        )}
                                                    </>
                                                ) : d.pendiente > 0 ? (
                                                    <span className="text-warning font-semibold">{numero(d.pendiente)}</span>
                                                ) : (
                                                    <span className="text-base-content/30">0</span>
                                                )}
                                            </td>
                                            <td className="text-base-content/70 text-xs">{d.condicion_salida ?? '—'}</td>
                                            <td>
                                                {editable ? (
                                                    <div className="space-y-1">
                                                        <Input
                                                            className="input-sm"
                                                            value={retorno?.condicion_retorno ?? ''}
                                                            onChange={(e) => editar(d.id, { condicion_retorno: e.target.value })}
                                                            placeholder={d.condicion_salida ? `Salió: ${d.condicion_salida}` : 'Cómo vuelve'}
                                                            disabled={!retorno?.vuelve}
                                                        />
                                                        {d.por_pieza && (
                                                            <label className="flex cursor-pointer items-center gap-2">
                                                                <input
                                                                    type="checkbox"
                                                                    className="checkbox checkbox-xs"
                                                                    checked={retorno?.en_reparacion ?? false}
                                                                    onChange={(e) => editar(d.id, { en_reparacion: e.target.checked })}
                                                                    disabled={!retorno?.vuelve}
                                                                />
                                                                <span className="text-xs">Vuelve dañada: a reparación</span>
                                                            </label>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-base-content/70 text-xs">{d.condicion_retorno ?? '—'}</span>
                                                )}
                                            </td>
                                            {!devolviendo && (
                                                <td className="text-xs">
                                                    {d.devuelto_en ? (
                                                        <>
                                                            <span className="font-mono">{d.devuelto_en}</span>
                                                            {d.recibio && (
                                                                <span className="text-base-content/50 block">recibió {d.recibio}</span>
                                                            )}
                                                            {d.cantidad_devuelta < d.cantidad && (
                                                                <span className="text-base-content/50 block">
                                                                    {numero(d.cantidad_devuelta)} de {numero(d.cantidad)}
                                                                </span>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <span className="text-base-content/30">—</span>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {devolviendo && (
                        <div className="mt-4 flex items-center justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setDevolviendo(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing || queVuelven.length === 0}>
                                <Undo2Icon className="size-4" />
                                Registrar devolución
                                {queVuelven.length > 0 ? ` (${queVuelven.length})` : ''}
                            </Button>
                        </div>
                    )}
                </form>

                {!abierto && (
                    <div className="alert alert-success mt-4">
                        <span>
                            Resguardo cerrado{prestamo.cerrado_en ? ` el ${prestamo.cerrado_en}` : ''}: todo volvió al
                            almacén.
                        </span>
                    </div>
                )}

                <p className="text-base-content/60 mt-4 text-sm">
                    Lo prestado nunca salió del saldo del almacén: la existencia no se toca al prestar ni al devolver.
                    Una pieza que vuelve dañada pasa a reparación y sigue contando.
                </p>
            </div>
        </AppLayout>
    );
}
