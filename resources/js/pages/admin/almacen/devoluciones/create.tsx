import { Head, Link, useForm } from '@inertiajs/react';
import { TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activos', href: '/admin/almacen/activos' },
    { title: 'Devoluciones', href: '/admin/almacen/devoluciones' },
    { title: 'Nueva', href: '/admin/almacen/devoluciones/create' },
];

const hoy = () => new Date().toISOString().slice(0, 10);
const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Renglon = {
    id: number;
    prestamo_id: number;
    folio: string | null;
    almacen: string | null;
    destino: string;
    fecha_salida: string | null;
    fecha_retorno_esperada: string | null;
    vencido: boolean;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    por_pieza: boolean;
    no_serie: string | null;
    pendiente: number;
    condicion_salida: string | null;
};

type Responsable = { id: string; nombre: string | null; renglones: Renglon[] };

type Props = {
    responsables: Responsable[];
    usuarios: { id: string; name: string }[];
    /** Si vienen desde un resguardo, se preselecciona a su responsable. */
    prestamoId: number | null;
};

type Retorno = { vuelve: boolean; cantidad: string; condicion_retorno: string; en_reparacion: boolean };

/**
 * Devolución: se elige a la persona y se palomea lo que entrega, venga del
 * resguardo que venga. Cada resguardo que se queda sin pendientes cierra solo.
 *
 * No captura partidas ni toca la existencia: lo prestado siempre fue del
 * almacén, lo único que cambia es que deja de estar en custodia de alguien.
 */
export default function DevolucionCreate({ responsables, usuarios, prestamoId }: Props) {
    const inicial = prestamoId === null ? '' : (responsables.find((r) => r.renglones.some((d) => d.prestamo_id === prestamoId))?.id ?? '');
    const [devolvio, setDevolvio] = useState(inicial);
    const [retornos, setRetornos] = useState<Record<number, Retorno>>({});

    const form = useForm<{
        fecha: string;
        recibido_por: string;
        renglones: { detalle_id: number; cantidad: number | string; condicion_retorno: string | null; en_reparacion: boolean }[];
    }>({
        fecha: hoy(),
        recibido_por: '',
        renglones: [],
    });

    const persona = responsables.find((r) => r.id === devolvio);
    const renglones = persona?.renglones ?? [];
    const queVuelven = renglones.filter((d) => retornos[d.id]?.vuelve);

    /** Cambiar de persona tira lo palomeado: eran los renglones de otro. */
    const elegirDevolvio = (valor: string) => {
        setDevolvio(valor);
        setRetornos({});
    };

    const editar = (d: Renglon, cambio: Partial<Retorno>) =>
        setRetornos((prev) => ({
            ...prev,
            [d.id]: {
                ...(prev[d.id] ?? { vuelve: false, cantidad: String(d.pendiente), condicion_retorno: '', en_reparacion: false }),
                ...cambio,
            },
        }));

    const errorDe = (i: number, campo: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[`renglones.${i}.${campo}`];

    const enviar = (e: React.FormEvent) => {
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

        form.post('/admin/almacen/devoluciones');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recibir devolución" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Recibir devolución</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Activos que vuelven al almacén y dejan de estar a nombre de alguien. Busca a la persona y palomea
                        lo que entrega, venga del resguardo que venga.
                    </p>
                </div>

                {responsables.length === 0 ? (
                    <div className="rounded-box border-base-300 text-base-content/60 border p-8 text-center">
                        No hay nada afuera: no hay nada que devolver.
                    </div>
                ) : (
                    <form onSubmit={enviar} className="space-y-6">
                        <div className="rounded-box border-base-300 border p-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField label="Devolvió" htmlFor="devolvio" description="Quién trae las cosas de vuelta." required>
                                    <SearchSelect
                                        options={responsables.map((r) => ({
                                            value: r.id,
                                            label: `${r.nombre ?? r.id} — ${r.renglones.length} renglón(es) afuera`,
                                        }))}
                                        value={devolvio}
                                        onValueChange={elegirDevolvio}
                                        placeholder="Escribe un nombre..."
                                        maxOptions={30}
                                    />
                                </FormField>

                                <FormField
                                    label="Fecha de devolución"
                                    htmlFor="fecha"
                                    error={form.errors.fecha}
                                    description="Cuándo entregó. Puede ser antes de hoy, nunca después."
                                    required
                                >
                                    <Input
                                        id="fecha"
                                        type="date"
                                        max={hoy()}
                                        value={form.data.fecha}
                                        onChange={(e) => form.setData('fecha', e.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    label="Recibió"
                                    htmlFor="recibido_por"
                                    error={form.errors.recibido_por}
                                    description="Quién del almacén revisó cómo vuelve. Vacío: quien captura."
                                >
                                    <SearchSelect
                                        options={usuarios.map((u) => ({ value: u.id, label: u.name }))}
                                        value={form.data.recibido_por}
                                        onValueChange={(v) => form.setData('recibido_por', v)}
                                        placeholder="Escribe un nombre..."
                                        maxOptions={30}
                                    />
                                </FormField>
                            </div>
                        </div>

                        <div>
                            <h2 className="mb-1 text-lg font-semibold">Lo que trae afuera</h2>
                            <p className="text-base-content/60 mb-3 text-sm">
                                {devolvio === ''
                                    ? 'Elige quién devuelve para ver qué trae a su nombre.'
                                    : 'Palomea sólo lo que entrega hoy; el resto se queda a su nombre.'}
                            </p>
                            {typeof form.errors.renglones === 'string' && (
                                <p className="text-error mb-2 text-sm">{form.errors.renglones}</p>
                            )}

                            {devolvio !== '' && (
                                <div className="rounded-box border-base-300 overflow-x-auto border">
                                    <table className="table table-sm">
                                        <thead className="bg-base-200">
                                            <tr>
                                                <th className="w-10">Vuelve</th>
                                                <th>Resguardo</th>
                                                <th>Artículo</th>
                                                <th>Serie</th>
                                                <th>Dónde</th>
                                                <th>Debía volver</th>
                                                <th className="text-right">Afuera</th>
                                                <th className="w-[22%]">Cómo vuelve</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {renglones.map((d) => {
                                                const retorno = retornos[d.id];
                                                const vuelve = retorno?.vuelve === true;
                                                const i = queVuelven.findIndex((q) => q.id === d.id);

                                                return (
                                                    <tr key={d.id} className={vuelve ? 'bg-success/10' : d.vencido ? 'bg-error/5' : 'hover'}>
                                                        <td>
                                                            <input
                                                                type="checkbox"
                                                                className="checkbox checkbox-sm"
                                                                checked={vuelve}
                                                                onChange={(e) => editar(d, { vuelve: e.target.checked })}
                                                                aria-label={`Devolver ${d.no_serie ?? d.descripcion ?? ''}`}
                                                            />
                                                        </td>
                                                        <td>
                                                            <Link
                                                                href={`/admin/almacen/prestamos/${d.prestamo_id}`}
                                                                className="link link-hover font-mono text-xs font-medium"
                                                            >
                                                                {d.folio}
                                                            </Link>
                                                            <span className="badge badge-xs badge-ghost ml-1 font-mono">{d.almacen}</span>
                                                        </td>
                                                        <td>
                                                            <span className="font-mono text-xs">{d.codigo}</span>
                                                            <span className="block text-sm">{d.descripcion}</span>
                                                        </td>
                                                        <td className="font-mono text-xs">
                                                            {d.no_serie ?? <span className="text-base-content/40">por cantidad</span>}
                                                        </td>
                                                        <td className="text-sm">{d.destino}</td>
                                                        <td className="font-mono text-xs">
                                                            {d.fecha_retorno_esperada ? (
                                                                <span className={d.vencido ? 'text-error font-semibold' : ''}>
                                                                    {d.vencido && <TriangleAlertIcon className="mr-1 inline size-3" />}
                                                                    {d.fecha_retorno_esperada}
                                                                </span>
                                                            ) : (
                                                                <span className="text-base-content/40">Sin fecha</span>
                                                            )}
                                                        </td>
                                                        <td className="text-right font-mono">
                                                            {d.por_pieza || !vuelve ? (
                                                                <>
                                                                    {numero(d.pendiente)}{' '}
                                                                    <span className="text-base-content/40 text-xs">{d.unidad}</span>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Input
                                                                        type="number"
                                                                        min="0"
                                                                        max={d.pendiente}
                                                                        step="0.0001"
                                                                        className="input-sm w-24 text-right"
                                                                        value={retorno?.cantidad ?? String(d.pendiente)}
                                                                        onChange={(e) => editar(d, { cantidad: e.target.value })}
                                                                        error={Boolean(i >= 0 && errorDe(i, 'cantidad'))}
                                                                    />
                                                                    <span className="text-base-content/50 block text-xs">
                                                                        de {numero(d.pendiente)} {d.unidad}
                                                                    </span>
                                                                    {i >= 0 && errorDe(i, 'cantidad') && (
                                                                        <span className="text-error text-xs">{errorDe(i, 'cantidad')}</span>
                                                                    )}
                                                                </>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <Input
                                                                className="input-sm"
                                                                value={retorno?.condicion_retorno ?? ''}
                                                                onChange={(e) => editar(d, { condicion_retorno: e.target.value })}
                                                                placeholder={d.condicion_salida ? `Salió: ${d.condicion_salida}` : 'Cómo vuelve'}
                                                                disabled={!vuelve}
                                                            />
                                                            {d.por_pieza && (
                                                                <label className="mt-1 flex cursor-pointer items-center gap-2">
                                                                    <input
                                                                        type="checkbox"
                                                                        className="checkbox checkbox-xs"
                                                                        checked={retorno?.en_reparacion ?? false}
                                                                        onChange={(e) => editar(d, { en_reparacion: e.target.checked })}
                                                                        disabled={!vuelve}
                                                                    />
                                                                    <span className="text-xs">Vuelve dañada: a reparación</span>
                                                                </label>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {queVuelven.length > 0 && queVuelven.length < renglones.length && (
                                <p className="text-base-content/60 mt-2 text-sm">
                                    Vuelven {queVuelven.length} de {renglones.length} renglones; el resto sigue a nombre de{' '}
                                    {persona?.nombre}.
                                </p>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/almacen/devoluciones">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing || queVuelven.length === 0}>
                                {queVuelven.length > 1 ? `Registrar devolución de ${queVuelven.length} renglones` : 'Registrar devolución'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
