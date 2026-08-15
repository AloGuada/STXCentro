import { FechasMovimiento } from '@/components/alm/fechas-movimiento';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { diasFuera, prestamosAbiertosDe, responsablesConPrestamos, USUARIOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventarios', href: '/admin/almacen/existencias' },
    { title: 'Devoluciones', href: '/admin/almacen/devoluciones' },
    { title: 'Nueva', href: '/admin/almacen/devoluciones/create' },
];

/** Lo que se captura de cada pieza que vuelve. */
type RetornoPieza = {
    vuelve: boolean;
    condicion: string;
};

/**
 * Devolución de piezas: cierra resguardos.
 *
 * Sólo entra aquí lo que tiene número de serie. El material por cantidad no se
 * devuelve: si sobró en la obra, vuelve al almacén general con una
 * transferencia, que es como se mueve el saldo entre almacenes. Por eso esta
 * pantalla no captura partidas ni toca la existencia — la pieza siempre fue del
 * almacén, lo único que cambia es que deja de estar en custodia de alguien.
 */
export default function DevolucionCreate() {
    const [devolvio, setDevolvio] = useState('');
    const [recibio, setRecibio] = useState('');
    const [fecha, setFecha] = useState('');
    const [retornos, setRetornos] = useState<Record<number, RetornoPieza>>({});

    const conPrestamos = responsablesConPrestamos();

    /** Sólo quien trae algo afuera: no hay nada más que devolver. */
    const opcionesDevolvio = conPrestamos.map((nombre) => {
        const piezas = prestamosAbiertosDe(nombre).length;

        return {
            value: nombre,
            label: `${nombre} — ${piezas} ${piezas === 1 ? 'pieza afuera' : 'piezas afuera'}`,
        };
    });

    const prestados = devolvio === '' ? [] : prestamosAbiertosDe(devolvio);
    const queVuelven = prestados.filter((p) => retornos[p.id]?.vuelve);

    /** Cambiar de persona tira lo palomeado: eran las piezas de otro. */
    const elegirDevolvio = (valor: string) => {
        setDevolvio(valor);
        setRetornos({});
    };

    const editarRetorno = (prestamoId: number, cambio: Partial<RetornoPieza>) =>
        setRetornos((previos) => ({
            ...previos,
            [prestamoId]: { ...(previos[prestamoId] ?? { vuelve: false, condicion: '' }), ...cambio },
        }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva devolución" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Nueva devolución</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        Piezas que vuelven al almacén y dejan de estar a nombre de alguien. Busca a la persona y
                        palomea lo que entrega.
                    </p>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: el formulario todavía no guarda nada.</span>
                </div>

                <div className="alert alert-info mb-4">
                    <span>
                        Aquí sólo vuelve lo que tiene <strong>número de serie</strong>. El material por cantidad que
                        sobró en una obra regresa con una <strong>transferencia</strong> al almacén general, que es
                        como se mueve el saldo entre almacenes.
                    </span>
                </div>

                {conPrestamos.length === 0 ? (
                    <div className="rounded-box border-base-300 text-base-content/60 border p-8 text-center">
                        No hay ninguna pieza afuera: no hay nada que devolver.
                    </div>
                ) : (
                    <form onSubmit={(e) => e.preventDefault()} className="space-y-6">
                        <div className="rounded-box border-base-300 border p-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField
                                    label="Devolvió"
                                    htmlFor="devolvio"
                                    description="Quién trae las piezas de vuelta."
                                    required
                                >
                                    <SearchSelect
                                        options={opcionesDevolvio}
                                        value={devolvio}
                                        onValueChange={elegirDevolvio}
                                        placeholder="Escribe un nombre..."
                                    />
                                </FormField>

                                <FechasMovimiento
                                    fecha={fecha}
                                    onChange={setFecha}
                                    label="Fecha de devolución"
                                    descripcion="Cuándo entregó las piezas. Puede ser antes de hoy, nunca después."
                                />

                                <FormField
                                    label="Recibió"
                                    htmlFor="recibio"
                                    description="Quién del almacén revisó cómo vuelven."
                                    required
                                >
                                    <Select
                                        id="recibio"
                                        value={recibio}
                                        onValueChange={setRecibio}
                                        placeholder="¿Quién las recibe?"
                                    >
                                        {USUARIOS_DEMO.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.nombre} — {u.puesto}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            </div>
                        </div>

                        <div>
                            <h2 className="mb-1 text-lg font-semibold">Piezas que trae afuera</h2>
                            <p className="text-base-content/60 mb-3 text-sm">
                                {devolvio === ''
                                    ? 'Elige quién devuelve para ver qué trae a su nombre.'
                                    : 'Palomea sólo lo que entrega hoy; el resto se queda a su nombre.'}
                            </p>

                            {devolvio !== '' && (
                                <div className="rounded-box border-base-300 overflow-x-auto border">
                                    <table className="table table-sm">
                                        <thead className="bg-base-200">
                                            <tr>
                                                <th className="w-10">Vuelve</th>
                                                <th>Folio</th>
                                                <th>Artículo</th>
                                                <th>Serie</th>
                                                <th>Almacén</th>
                                                <th>Estaba en</th>
                                                <th>Salió</th>
                                                <th>Debía volver</th>
                                                <th>Salió en</th>
                                                <th className="w-[20%]">Cómo vuelve</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {prestados.map((prestamo) => {
                                                const { vencido } = diasFuera(prestamo);
                                                const retorno = retornos[prestamo.id];
                                                const vuelve = retorno?.vuelve === true;

                                                return (
                                                    <tr
                                                        key={prestamo.id}
                                                        className={
                                                            vuelve ? 'bg-success/10' : vencido ? 'bg-error/5' : 'hover'
                                                        }
                                                    >
                                                        <td>
                                                            <input
                                                                type="checkbox"
                                                                className="checkbox checkbox-sm"
                                                                checked={vuelve}
                                                                onChange={(e) =>
                                                                    editarRetorno(prestamo.id, {
                                                                        vuelve: e.target.checked,
                                                                    })
                                                                }
                                                                aria-label={`Devolver ${prestamo.no_serie}`}
                                                            />
                                                        </td>
                                                        <td className="font-mono text-xs font-medium">
                                                            {prestamo.folio}
                                                        </td>
                                                        <td className="text-sm">{prestamo.articulo}</td>
                                                        <td className="font-mono text-xs">{prestamo.no_serie}</td>
                                                        <td>
                                                            <span className="badge badge-sm badge-ghost font-mono">
                                                                {prestamo.almacen}
                                                            </span>
                                                        </td>
                                                        <td className="text-sm">{prestamo.destino}</td>
                                                        <td className="font-mono text-xs">{prestamo.fecha_salida}</td>
                                                        <td className="font-mono text-xs">
                                                            <span className={vencido ? 'text-error font-semibold' : ''}>
                                                                {vencido && (
                                                                    <TriangleAlertIcon className="mr-1 inline size-3" />
                                                                )}
                                                                {prestamo.fecha_retorno_esperada}
                                                            </span>
                                                        </td>
                                                        <td className="text-base-content/60 text-xs">
                                                            {prestamo.condicion_salida}
                                                        </td>
                                                        <td>
                                                            {/*
                                                             * Se compara contra la condición de salida: es
                                                             * lo único que permite reclamar un daño.
                                                             */}
                                                            <Input
                                                                className="input-sm"
                                                                value={retorno?.condicion ?? ''}
                                                                onChange={(e) =>
                                                                    editarRetorno(prestamo.id, {
                                                                        condicion: e.target.value,
                                                                    })
                                                                }
                                                                placeholder={`Salió: ${prestamo.condicion_salida}`}
                                                                disabled={!vuelve}
                                                            />
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {queVuelven.length > 0 && queVuelven.length < prestados.length && (
                                <p className="text-base-content/60 mt-2 text-sm">
                                    Se cierran {queVuelven.length} de {prestados.length} resguardos; el resto sigue a
                                    nombre de {devolvio}.
                                </p>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/almacen/devoluciones">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled title="La maqueta todavía no guarda">
                                {queVuelven.length > 1
                                    ? `Registrar devolución de ${queVuelven.length} piezas`
                                    : 'Registrar devolución'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
