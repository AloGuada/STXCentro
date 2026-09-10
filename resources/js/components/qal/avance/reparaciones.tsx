/**
 * La carga de reparación.
 *
 * Bloque aparte, y ésa es la decisión de fondo: **una pieza rechazada ya está
 * fabricada**. Volver a meterla en el plan la contaría dos veces, y además
 * repararla no cuesta lo mismo que armarla. Vive aquí hasta que se libera.
 *
 * De los cuatro números, el que manda es «más de 2 semanas»: una cola de
 * reparación que se mueve no frena a nadie, y una pieza parada veinte días sí.
 */

import { Kpi, Pastilla, Tarjeta } from '@/components/qal/ui';
import type { Fase, PiezaVista } from './datos';
import { diasDesde } from './semanas';

/** A partir de aquí una pieza parada deja de ser normal y empieza a estorbar. */
const DIAS_VIEJA = 14;
const DIAS_AVISO = 7;

/** Cuántas filas caben antes de que la tabla deje de ayudar. */
const MAXIMO = 40;

export function Reparaciones({ piezas, semana, fase }: { piezas: PiezaVista[]; semana: string; fase?: Fase }) {
    const pendientes = piezas.filter((p) => p.estatus === 'Rechazado');
    const viejas = pendientes.filter((p) => diasDesde(p.fechaUltima) > DIAS_VIEJA);
    const nuevas = piezas.filter((p) => p.semanasRechazada.includes(semana));
    // Liberada esta semana y en una inspección posterior a la primera: eso es
    // exactamente una pieza que se rechazó, se retrabajó y salió.
    const resueltas = piezas.filter((p) => p.semanaLiberada === semana && (p.inspeccionLiberada ?? 1) > 1);

    const ordenadas = [...pendientes].sort((a, b) => diasDesde(b.fechaUltima) - diasDesde(a.fechaUltima));

    return (
        <Tarjeta
            titulo="Carga de reparación"
            nota={
                fase === '3'
                    ? 'rechazadas en pintura que siguen sin liberarse'
                    : 'rechazadas en fabricación que siguen sin liberarse'
            }
        >
            <div className="space-y-3 p-4">
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Kpi
                        titulo="Pendientes de reparar"
                        valor={pendientes.length}
                        pie="acumulado"
                        tono={pendientes.length ? 'error' : 'ok'}
                    />
                    <Kpi titulo="Rechazadas esta semana" valor={nuevas.length} pie="entran a la cola" />
                    <Kpi
                        titulo="Resueltas esta semana"
                        valor={resueltas.length}
                        pie="liberadas tras retrabajo"
                        tono="ok"
                    />
                    <Kpi
                        titulo="Más de 2 semanas"
                        valor={viejas.length}
                        pie="las que de verdad frenan"
                        tono={viejas.length ? 'warn' : undefined}
                    />
                </div>

                <div className="border-info/30 bg-info/10 text-base-content/80 rounded-lg border px-3 py-2 text-sm">
                    Una pieza rechazada <b>ya está fabricada</b>: no vuelve al plan, porque se contaría dos veces y
                    repararla no cuesta lo mismo que armarla. Vive aquí hasta que se libera.
                </div>
            </div>

            {ordenadas.length === 0 ? (
                <p className="text-base-content/60 px-4 pb-6 text-center text-sm">
                    Ninguna pieza rechazada sin resolver.
                </p>
            ) : (
                <>
                    <div className="overflow-x-auto">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th className="bg-base-200">Marca</th>
                                    <th className="bg-base-200">Obra</th>
                                    <th className="bg-base-200 text-right">Cons.</th>
                                    <th className="bg-base-200">Rechazada el</th>
                                    <th className="bg-base-200 text-right">Días parada</th>
                                    <th className="bg-base-200 text-right">Insp.</th>
                                    <th className="bg-base-200">Inspector</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ordenadas.slice(0, MAXIMO).map((p) => {
                                    const dias = diasDesde(p.fechaUltima);

                                    return (
                                        <tr key={`${p.marca}|${p.obra}|${p.consec}`} className="hover:bg-base-200/50">
                                            <td className="font-semibold">{p.marca}</td>
                                            <td className="text-sm">{p.obra}</td>
                                            <td className="text-right font-mono">{p.consec}</td>
                                            <td className="font-mono text-sm">{p.fechaUltima}</td>
                                            <td className="text-right">
                                                <Pastilla
                                                    tono={
                                                        dias > DIAS_VIEJA
                                                            ? 'error'
                                                            : dias > DIAS_AVISO
                                                              ? 'warn'
                                                              : 'neutro'
                                                    }
                                                >
                                                    {dias}
                                                </Pastilla>
                                            </td>
                                            <td className="text-right font-mono">{p.inspecciones}</td>
                                            <td className="text-sm">{p.inspector}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {ordenadas.length > MAXIMO && (
                        <p className="text-base-content/60 px-4 py-3 text-xs">
                            Se muestran las {MAXIMO} más antiguas de {ordenadas.length}.
                        </p>
                    )}
                </>
            )}
        </Tarjeta>
    );
}
