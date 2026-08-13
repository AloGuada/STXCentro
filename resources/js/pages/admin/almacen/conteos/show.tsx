import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { CLASES_ABC, CONTEOS_DEMO, ESTATUS_CONTEO, resumenConteo } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, PrinterIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Props = {
    /** Id de la hoja. La maqueta lo lee de la URL y busca en los datos demo. */
    conteoId?: number;
};

/**
 * Captura de un conteo.
 *
 * Se teclea **lo contado**, nunca la diferencia: el sistema la calcula. Pedirle
 * al almacenista que reste él mismo es pedirle que decida qué es un error, y
 * ahí es donde el conteo deja de servir como control.
 *
 * Mientras cuenta no ve el saldo del sistema, y por la misma razón: un número a
 * la vista es una respuesta sugerida. Se destapa al terminar, ya con lo contado
 * escrito.
 */
export default function ConteoShow({ conteoId }: Props) {
    const conteo = CONTEOS_DEMO.find((c) => c.id === conteoId) ?? CONTEOS_DEMO[0];
    const cerrado = conteo.estatus === 'cerrado' || conteo.estatus === 'cancelado';

    const [contados, setContados] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            conteo.renglones
                .filter((r) => r.cantidad_contada !== null)
                .map((r) => [r.producto_id, String(r.cantidad_contada)]),
        ),
    );
    // Al cerrar se compara contra el sistema; antes de eso el número estorba.
    const [verSistema, setVerSistema] = useState(cerrado);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Insumos', href: '/admin/almacen/existencias' },
        { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
        { title: conteo.folio, href: `/admin/almacen/conteos/${conteo.id}` },
    ];

    const filas = conteo.renglones.map((renglon) => {
        const capturado = contados[renglon.producto_id];
        const contado = capturado === undefined || capturado === '' ? null : Number(capturado);
        const diferencia = contado === null ? null : contado - renglon.cantidad_sistema;

        return { renglon, contado, diferencia };
    });

    const pendientes = filas.filter((f) => f.contado === null).length;
    const conDiferencia = filas.filter((f) => f.diferencia !== null && f.diferencia !== 0);
    const resumen = resumenConteo(conteo);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Conteo ${conteo.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{conteo.folio}</h1>
                            <span className={`badge badge-sm ${ESTATUS_CONTEO[conteo.estatus].clase}`}>
                                {ESTATUS_CONTEO[conteo.estatus].etiqueta}
                            </span>
                            <span className="badge badge-sm badge-ghost">
                                {conteo.origen === 'programado' ? 'Programa ABC' : 'Conteo suelto'}
                            </span>
                            {conteo.clasificacion && (
                                <span className={`badge badge-sm ${CLASES_ABC[conteo.clasificacion]}`}>
                                    Clase {conteo.clasificacion}
                                </span>
                            )}
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Almacén {conteo.almacen} · {conteo.ubicacion ?? 'todo el almacén'} · programado el{' '}
                            {conteo.fecha_programada} · cuenta {conteo.responsable}
                            {resumen.vencido && <span className="text-error"> · vencido</span>}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/conteos" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <Button variant="outline" disabled>
                            <PrinterIcon className="size-4" />
                            Imprimir hoja
                        </Button>
                    </div>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: la captura todavía no guarda nada.</span>
                </div>

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="text-base-content/70 text-sm">
                        {pendientes === 0 ? (
                            <span>Todo contado.</span>
                        ) : (
                            <span>
                                Faltan <strong>{pendientes}</strong> de {filas.length} renglones.
                            </span>
                        )}
                        {conDiferencia.length > 0 && verSistema && (
                            <span className="text-error"> · {conDiferencia.length} con diferencia.</span>
                        )}
                    </div>

                    {!cerrado && (
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={verSistema}
                                onChange={(e) => setVerSistema(e.target.checked)}
                            />
                            <span className="text-sm">Mostrar lo que dice el sistema</span>
                        </label>
                    )}
                </div>

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Ubicación</th>
                                <th className="w-36 text-right">Contado</th>
                                {verSistema && <th className="text-right">Sistema</th>}
                                {verSistema && <th className="text-right">Diferencia</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map(({ renglon, contado, diferencia }) => (
                                <tr key={renglon.producto_id} className="hover">
                                    <td className="font-mono text-xs">{renglon.codigo}</td>
                                    <td>{renglon.descripcion}</td>
                                    <td className="text-base-content/60 text-sm">
                                        {renglon.ubicacion ?? <span className="text-base-content/40">—</span>}
                                    </td>
                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.001"
                                                className="input-sm w-24 text-right font-mono"
                                                value={contados[renglon.producto_id] ?? ''}
                                                onChange={(e) =>
                                                    setContados((prev) => ({
                                                        ...prev,
                                                        [renglon.producto_id]: e.target.value,
                                                    }))
                                                }
                                                disabled={cerrado}
                                                aria-label={`Contado de ${renglon.codigo}`}
                                            />
                                            <span className="text-base-content/40 text-xs">{renglon.unidad}</span>
                                        </div>
                                    </td>
                                    {verSistema && (
                                        <td className="text-right font-mono">{numero(renglon.cantidad_sistema)}</td>
                                    )}
                                    {verSistema && (
                                        <td className="text-right font-mono">
                                            {diferencia === null ? (
                                                <span className="text-base-content/30">—</span>
                                            ) : diferencia === 0 ? (
                                                <span className="text-success">0</span>
                                            ) : (
                                                <span className="text-error font-semibold">
                                                    <TriangleAlertIcon className="mr-1 inline size-3" />
                                                    {diferencia > 0 ? '+' : ''}
                                                    {numero(diferencia)}
                                                </span>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {conteo.ajuste_folio && (
                    <div className="alert alert-success mt-4">
                        <span>
                            Este conteo cerró con diferencias y generó el ajuste{' '}
                            <strong className="font-mono">{conteo.ajuste_folio}</strong>, que es el que movió el saldo.
                        </span>
                    </div>
                )}

                {!cerrado && (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <p className="text-base-content/60 max-w-2xl text-sm">
                            Al cerrar, las diferencias se convierten en un <strong>ajuste</strong> con motivo «conteo
                            físico». El conteo no toca el saldo por su cuenta: así la corrección queda con folio y con
                            quién la autorizó.
                        </p>
                        <div className="flex gap-2">
                            <Button variant="outline" disabled>
                                Guardar avance
                            </Button>
                            <Button disabled title={pendientes > 0 ? 'Faltan renglones por contar' : undefined}>
                                Cerrar y generar ajuste
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
