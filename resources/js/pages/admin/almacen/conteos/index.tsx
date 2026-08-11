import { ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    ALMACENES_DEMO,
    ARTICULOS_DEMO,
    CLASES_ABC,
    CONTEOS_DEMO,
    ESTATUS_CONTEO,
    REGLAS_ABC,
    resumenConteo,
} from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarClockIcon, PlusIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
];

/**
 * Inventario cíclico: contar un pedazo del almacén cada semana en vez de parar
 * todo un fin de semana al año.
 *
 * Dos motores conviven. El **programa ABC** genera solo las hojas que tocan,
 * según cada cuánto hay que repasar cada clase de artículo; el **conteo suelto**
 * lo levanta el jefe de almacén cuando sospecha un faltante y no puede esperar
 * a que le toque a esa zona.
 *
 * Ninguno de los dos mueve el saldo: al cerrar generan un **ajuste**, que es el
 * único documento al que el kardex le permite corregir existencias.
 */
export default function ConteosIndex() {
    const [almacen, setAlmacen] = useState('');
    const [estatus, setEstatus] = useState('');

    const visibles = CONTEOS_DEMO.filter(
        (c) => (!almacen || c.almacen === almacen) && (!estatus || c.estatus === estatus),
    );

    const abiertos = CONTEOS_DEMO.filter((c) => c.estatus === 'pendiente' || c.estatus === 'contando');
    const vencidos = abiertos.filter((c) => resumenConteo(c).vencido);
    const programados = abiertos.filter((c) => c.origen === 'programado').length;
    const manuales = abiertos.filter((c) => c.origen === 'manual').length;

    /** Cuántos artículos con kardex caen en cada clase. Es la carga del programa. */
    const porClase = (clase: string) =>
        ARTICULOS_DEMO.filter((a) => a.controla_inventario && a.clasificacion_abc === clase).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventarios cíclicos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Inventarios cíclicos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Se cuenta una parte del almacén a la vez, sin parar la operación. Lo caro se repasa cada
                            mes; lo barato, cada semestre.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/conteos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Nuevo conteo suelto
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-box border-base-300 border p-4">
                        <p className="text-base-content/60 text-sm">Hojas abiertas</p>
                        <p className="mt-1 text-3xl font-semibold">{abiertos.length}</p>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {programados} del programa · {manuales} sueltas
                        </p>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <p className="text-base-content/60 text-sm">Vencidas</p>
                        <p className={`mt-1 text-3xl font-semibold ${vencidos.length > 0 ? 'text-error' : ''}`}>
                            {vencidos.length}
                        </p>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {vencidos.length > 0
                                ? 'Se les pasó la fecha y nadie las ha contado.'
                                : 'Todo al corriente.'}
                        </p>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <p className="text-base-content/60 text-sm">Artículos en el programa</p>
                        <p className="mt-1 text-3xl font-semibold">
                            {ARTICULOS_DEMO.filter((a) => a.controla_inventario).length}
                        </p>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Lo que no lleva kardex no entra: un flete no se cuenta.
                        </p>
                    </div>
                </div>

                <div className="rounded-box border-base-300 mb-6 border">
                    <div className="border-base-300 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <CalendarClockIcon className="text-base-content/50 size-4" />
                            <h2 className="font-medium">Programa ABC</h2>
                        </div>
                        <Link href="/admin/almacen/articulos" className="btn btn-ghost btn-sm">
                            Clasificar artículos
                        </Link>
                    </div>
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th className="w-24">Clase</th>
                                <th className="w-32">Frecuencia</th>
                                <th>Qué entra</th>
                                <th className="text-right">Artículos</th>
                            </tr>
                        </thead>
                        <tbody>
                            {REGLAS_ABC.map((r) => (
                                <tr key={r.clasificacion} className="hover">
                                    <td>
                                        <span className={`badge badge-sm ${CLASES_ABC[r.clasificacion]}`}>
                                            {r.clasificacion}
                                        </span>
                                    </td>
                                    <td>
                                        {r.etiqueta}
                                        <span className="text-base-content/50 block text-xs">
                                            cada {r.frecuencia_dias} días
                                        </span>
                                    </td>
                                    <td className="text-base-content/70 text-sm">{r.descripcion}</td>
                                    <td className="text-right font-mono">{porClase(r.clasificacion)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="border-base-300 text-base-content/60 border-t px-4 py-2 text-sm">
                        La clase se cambia artículo por artículo desde el catálogo. De ahí salen solas las hojas de cada
                        semana.
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-56">
                        <label className="label label-text text-xs">Almacén</label>
                        <Select value={almacen} onValueChange={setAlmacen}>
                            <SelectItem value="">Todos los almacenes</SelectItem>
                            {ALMACENES_DEMO.map((a) => (
                                <SelectItem key={a.id} value={a.clave}>
                                    {a.clave} — {a.nombre}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>

                    <div className="w-48">
                        <label className="label label-text text-xs">Estado</label>
                        <Select value={estatus} onValueChange={setEstatus}>
                            <SelectItem value="">Todos</SelectItem>
                            {Object.entries(ESTATUS_CONTEO).map(([valor, { etiqueta }]) => (
                                <SelectItem key={valor} value={valor}>
                                    {etiqueta}
                                </SelectItem>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Origen</th>
                                <th>Almacén</th>
                                <th>Zona</th>
                                <th>Programado</th>
                                <th>Responsable</th>
                                <th className="text-right">Avance</th>
                                <th className="text-right">Diferencias</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-base-content/50 py-6 text-center">
                                        No hay conteos con esos filtros.
                                    </td>
                                </tr>
                            ) : (
                                visibles.map((c) => {
                                    const resumen = resumenConteo(c);

                                    return (
                                        <tr key={c.id} className="hover">
                                            <td>
                                                <Link
                                                    href={`/admin/almacen/conteos/${c.id}`}
                                                    className="link link-hover font-mono font-medium"
                                                >
                                                    {c.folio}
                                                </Link>
                                                {c.ajuste_folio && (
                                                    <span className="text-base-content/50 block text-xs">
                                                        → {c.ajuste_folio}
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost">
                                                    {c.origen === 'programado' ? 'Programa' : 'Suelto'}
                                                </span>
                                                {c.clasificacion && (
                                                    <span
                                                        className={`badge badge-xs ml-1 ${CLASES_ABC[c.clasificacion]}`}
                                                    >
                                                        {c.clasificacion}
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">{c.almacen}</span>
                                            </td>
                                            <td className="text-base-content/70 text-sm">
                                                {c.ubicacion ?? (
                                                    <span className="text-base-content/40">Todo el almacén</span>
                                                )}
                                            </td>
                                            <td className="font-mono text-xs">
                                                {c.fecha_programada}
                                                {resumen.vencido && (
                                                    <span className="text-error ml-1" title="Se pasó la fecha">
                                                        <TriangleAlertIcon className="inline size-3" />
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-sm">{c.responsable}</td>
                                            <td className="text-right font-mono text-xs">
                                                {resumen.contados}/{resumen.total}
                                            </td>
                                            <td className="text-right font-mono">
                                                {resumen.diferencias > 0 ? (
                                                    <span className="text-error font-semibold">
                                                        {resumen.diferencias}
                                                    </span>
                                                ) : (
                                                    <span className="text-base-content/30">—</span>
                                                )}
                                            </td>
                                            <td>
                                                <span className={`badge badge-sm ${ESTATUS_CONTEO[c.estatus].clase}`}>
                                                    {ESTATUS_CONTEO[c.estatus].etiqueta}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    El conteo <strong>no mueve el saldo</strong>. Al cerrarse genera un <strong>ajuste</strong> con las
                    diferencias, que es el único documento al que el kardex le permite corregir existencias — y así la
                    corrección queda con folio, motivo y quién la autorizó.
                </p>
            </div>
        </AppLayout>
    );
}
