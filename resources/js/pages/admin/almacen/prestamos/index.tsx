import { Button, ButtonLink } from '@/components/ui/button';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ACTIVOS_DEMO, diasFuera, ESTATUS_PRESTAMO, PRESTAMOS_DEMO } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlusIcon, TriangleAlertIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Almacén', href: '/admin/almacen/existencias' },
    { title: 'Préstamos', href: '/admin/almacen/prestamos' },
];

/**
 * Quién tiene qué herramienta. Es la pregunta que un kardex por cantidad no
 * puede contestar: sabe que salieron tres pulidoras, no cuál trae cada quien.
 *
 * El préstamo no mueve el saldo del almacén — la pieza sigue siendo suya, lo
 * que cambia es la custodia.
 */
export default function PrestamosIndex() {
    const [filtro, setFiltro] = useState('abiertos');

    const visibles = useMemo(
        () =>
            PRESTAMOS_DEMO.filter((p) => {
                if (filtro === 'abiertos') {
                    return p.estatus === 'abierto';
                }

                if (filtro === 'vencidos') {
                    return diasFuera(p).vencido;
                }

                if (filtro === 'devueltos') {
                    return p.estatus === 'devuelto';
                }

                return true;
            }),
        [filtro],
    );

    const vencidos = PRESTAMOS_DEMO.filter((p) => diasFuera(p).vencido).length;
    const afuera = PRESTAMOS_DEMO.filter((p) => p.estatus === 'abierto').length;
    const enReparacion = ACTIVOS_DEMO.filter((a) => a.estatus === 'en_reparacion').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Préstamos" />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Préstamos</h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Resguardo de herramienta pieza por pieza: quién la tiene, desde cuándo y en qué condición
                            salió. La pieza sigue siendo del almacén; lo que cambia es la custodia.
                        </p>
                    </div>
                    <ButtonLink href="/admin/almacen/prestamos/create" variant="primary">
                        <PlusIcon className="size-4" />
                        Prestar herramienta
                    </ButtonLink>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: los datos son de ejemplo, todavía no hay backend.</span>
                </div>

                {vencidos > 0 && (
                    <div className="alert alert-error mb-4">
                        <TriangleAlertIcon className="size-5" />
                        <span>
                            {vencidos} {vencidos === 1 ? 'pieza pasó' : 'piezas pasaron'} su fecha de retorno.
                        </span>
                    </div>
                )}

                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div className="w-56">
                        <label className="label" htmlFor="filtro">
                            <span className="label-text">Mostrar</span>
                        </label>
                        <Select id="filtro" value={filtro} onValueChange={setFiltro}>
                            <SelectItem value="abiertos">Afuera ({afuera})</SelectItem>
                            <SelectItem value="vencidos">Vencidos ({vencidos})</SelectItem>
                            <SelectItem value="devueltos">Devueltos</SelectItem>
                            <SelectItem value="todos">Todos</SelectItem>
                        </Select>
                    </div>
                    <p className="text-base-content/60 pb-3 text-sm">
                        {enReparacion > 0 && `${enReparacion} en reparación, fuera de servicio.`}
                    </p>
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Folio</th>
                                <th>Artículo</th>
                                <th>Serie</th>
                                <th>Pañol</th>
                                <th>Quién la tiene</th>
                                <th>Dónde</th>
                                <th>Salió</th>
                                <th>Debe volver</th>
                                <th className="text-right">Días fuera</th>
                                <th>Condición</th>
                                <th>Estatus</th>
                                <th className="w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {visibles.length === 0 ? (
                                <tr>
                                    <td colSpan={12} className="text-base-content/50 py-6 text-center">
                                        Ningún préstamo coincide con el filtro.
                                    </td>
                                </tr>
                            ) : (
                                visibles.map((p) => {
                                    const { dias, vencido } = diasFuera(p);
                                    const estatus = ESTATUS_PRESTAMO[p.estatus];

                                    return (
                                        <tr key={p.id} className={vencido ? 'bg-error/5' : 'hover'}>
                                            <td className="font-mono font-medium">{p.folio}</td>
                                            <td className="text-sm">{p.articulo}</td>
                                            <td className="font-mono text-xs">{p.no_serie}</td>
                                            <td>
                                                <span className="badge badge-sm badge-ghost font-mono">
                                                    {p.almacen}
                                                </span>
                                            </td>
                                            <td className="text-sm font-medium">{p.responsable}</td>
                                            <td className="text-sm">{p.destino}</td>
                                            <td className="font-mono text-sm">{p.fecha_salida}</td>
                                            <td className="font-mono text-sm">
                                                <span className={vencido ? 'text-error font-semibold' : ''}>
                                                    {p.fecha_retorno_esperada}
                                                </span>
                                            </td>
                                            <td className="text-right font-mono">
                                                <span className={vencido ? 'text-error font-semibold' : ''}>
                                                    {vencido && <TriangleAlertIcon className="mr-1 inline size-3" />}
                                                    {dias}
                                                </span>
                                            </td>
                                            <td className="text-base-content/70 text-xs">
                                                {/*
                                                 * Comparar cómo salió contra cómo volvió es lo que
                                                 * permite reclamar un daño; por eso se guardan las dos.
                                                 */}
                                                {p.condicion_retorno
                                                    ? `${p.condicion_salida} → ${p.condicion_retorno}`
                                                    : p.condicion_salida}
                                            </td>
                                            <td>
                                                <span className={`badge badge-sm ${estatus.clase}`}>
                                                    {estatus.etiqueta}
                                                </span>
                                            </td>
                                            <td>
                                                {p.estatus === 'abierto' && (
                                                    <Button size="xs" variant="outline" disabled title="La maqueta todavía no guarda">
                                                        Devolver
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <p className="text-base-content/60 mt-4 text-sm">
                    Aquí sólo aparece lo marcado <strong>por pieza</strong> en el catálogo de artículos. La herramienta
                    que se controla nada más por cantidad —módulos de andamio, extensiones— sale y regresa con una
                    salida y una devolución normales, y su saldo vive en Existencias.
                </p>
            </div>
        </AppLayout>
    );
}
