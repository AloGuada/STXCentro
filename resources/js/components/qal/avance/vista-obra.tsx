/**
 * Una obra, una semana, una transformación.
 *
 * El plan de la semana tiene dos momentos. Abierto es un borrador: Producción
 * agrega piezas —con su grupo de trabajo y su módulo— y las quita con libertad;
 * no cuenta en ningún número y Calidad no lo ve. Cerrado es el compromiso: ya
 * no se toca, empieza a contar, y lo que no se fabrique se arrastra.
 *
 * Después viene lo que se mira en la reunión: cómo va la semana, el corte por
 * tipo de pieza y la carga de reparación. Eso no se escribe: lo calcula el
 * servidor con las inspecciones.
 */

import { router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, LockIcon, SaveIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';
import { Kpi, Leyenda, Nota, Pastilla, Tarjeta, tonoDeAvance } from '@/components/qal/ui';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { AgregarAlPlan } from './agregar-al-plan';
import { estadoDeFila, porcentaje } from './calculo';
import { Reparaciones } from './reparaciones';
import { numeroSemana, rangoSemana, semanaMas } from './semanas';
import { faseDe, FASES, type Fase, type GrupoOpcion, type ObraOpcion, type PiezaDelBorrador, type VistaAvance } from './tipos';

const piezaDe = (l: { qr: string; qs: string | null }) => `QR ${l.qr}${l.qs ? ` · QS ${l.qs}` : ''}`;

export function VistaObra({
    obraId,
    obra,
    obras,
    semana,
    fase,
    vista,
    grupos,
    puedeCapturar,
    puedeCerrar,
    onFase,
    onVolver,
    onAbrirObra,
}: {
    obraId: number;
    obra: string;
    obras: ObraOpcion[];
    semana: string;
    fase: Fase;
    vista: VistaAvance;
    grupos: GrupoOpcion[];
    puedeCapturar: boolean;
    puedeCerrar: boolean;
    onFase: (fase: Fase) => void;
    onVolver: () => void;
    onAbrirObra: (obraId: number) => void;
}) {
    const F = faseDe(fase);
    const { props } = usePage<SharedData & { flash?: { success?: string | null }; errors?: Record<string, string> }>();
    const { plan, lineas, total, tipos } = vista;
    const borrador = vista.borrador ?? [];
    const cerrado = plan.estado === 'cerrado';
    /** El plan sigue siendo de Producción: se le agregan y se le quitan piezas. */
    const armando = puedeCapturar && !cerrado;

    const notas = useForm({ obra_id: obraId, fase, semana, notas: plan.notas ?? '' });

    const guardarNotas = (evento: FormEvent) => {
        evento.preventDefault();
        notas.post('/admin/calidad/avance/programaciones', { preserveScroll: true, onSuccess: () => notas.setDefaults() });
    };

    const quitar = (pieza: PiezaDelBorrador) => router.delete(`/admin/calidad/avance/plan/${pieza.id}`, { preserveScroll: true });

    const cerrar = () => {
        if (
            confirm(
                `¿Cerrar el plan de la semana ${numeroSemana(semana)} con ${borrador.length} pieza(s)?\n\nA partir de ahora cuenta, Calidad lo ve y ya no se le agregan ni se le quitan piezas.`,
            )
        ) {
            router.post('/admin/calidad/avance/programaciones/cerrar', { obra_id: obraId, fase, semana }, { preserveScroll: true });
        }
    };

    const errores = Object.values({ ...(props.errors ?? {}), ...notas.errors }).filter(Boolean);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <button type="button" onClick={onVolver} className="btn btn-sm btn-ghost">
                    <ArrowLeftIcon className="size-4" />
                    Todas las obras
                </button>
                <div className="font-semibold">{obra}</div>
                <div className="join">
                    {FASES.map((f) => (
                        <button
                            key={f.id}
                            type="button"
                            onClick={() => onFase(f.id)}
                            className={cn('btn join-item btn-sm', f.id === fase && 'btn-primary')}
                        >
                            {f.nombre}
                        </button>
                    ))}
                </div>
                {cerrado ? (
                    <Pastilla tono="ok">
                        Plan cerrado{plan.cerradoEl ? ` el ${plan.cerradoEl}` : ''}
                        {plan.cerradoPor ? ` · ${plan.cerradoPor}` : ''}
                    </Pastilla>
                ) : (
                    plan.estado === 'abierto' && <Pastilla tono="warn">Plan abierto: todavía no cuenta</Pastilla>
                )}
            </div>

            {props.flash?.success && <div className="alert alert-success text-sm">{props.flash.success}</div>}
            {errores.length > 0 && (
                <div className="alert alert-error text-sm">
                    {errores.map((error) => (
                        <div key={error}>{error}</div>
                    ))}
                </div>
            )}

            {/* Quien sólo consulta no ve el borrador: sabe que existe y nada más. */}
            {!puedeCapturar && plan.estado === 'abierto' && (
                <Nota>
                    Producción aún no cierra el plan de esta semana. Mientras esté abierto no cuenta ni se muestra; lo de abajo es lo que
                    viene arrastrado de semanas ya cerradas.
                </Nota>
            )}

            {/* 1 · armar el plan: agregar piezas y ver el borrador */}
            {armando && (
                <>
                    <Tarjeta titulo={`Agregar piezas a ${F.gerundio} en la semana ${numeroSemana(semana)}`} nota={`${rangoSemana(semana)} · ${F.nombre}`}>
                        <AgregarAlPlan
                            obras={obras}
                            obraId={obraId}
                            fase={fase}
                            semana={semana}
                            grupos={grupos}
                            version={borrador.map((x) => x.id).join(',')}
                            onAbrirObra={onAbrirObra}
                        />
                    </Tarjeta>

                    <Tarjeta
                        titulo="Borrador del plan"
                        nota={`${borrador.length} pieza(s) · no cuenta ni lo ve Calidad hasta cerrarlo`}
                        acciones={
                            puedeCerrar && (
                                <button type="button" className="btn btn-sm btn-primary" disabled={borrador.length === 0} onClick={cerrar}>
                                    <LockIcon className="size-4" />
                                    Cerrar plan de la semana
                                </button>
                            )
                        }
                    >
                        {borrador.length === 0 ? (
                            <p className="text-base-content/60 px-4 py-8 text-center text-sm">
                                Todavía no hay nada en el plan.
                                <br />
                                Elige arriba las piezas, su grupo y su módulo, y pulsa Agregar.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table-sm table w-full whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th className="bg-base-200">Marca</th>
                                            <th className="bg-base-200">Lote</th>
                                            <th className="bg-base-200">Pieza</th>
                                            <th className="bg-base-200">Grupo</th>
                                            <th className="bg-base-200">Módulo</th>
                                            <th className="bg-base-200" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {borrador.map((x) => (
                                            <tr key={x.id} className="hover:bg-base-200/50">
                                                <td className="font-semibold">{x.marca}</td>
                                                <td>{x.lote || '—'}</td>
                                                <td className="font-mono text-sm">{piezaDe(x)}</td>
                                                <td>{x.grupo || '—'}</td>
                                                <td className="font-mono text-sm">{x.modulo || '—'}</td>
                                                <td className="text-right">
                                                    <button type="button" className="btn btn-xs btn-ghost text-error" onClick={() => quitar(x)}>
                                                        <Trash2Icon className="size-3.5" />
                                                        Eliminar del plan
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Tarjeta>
                </>
            )}

            {/* 2 · lo que ya cuenta: el plan cerrado y lo arrastrado */}
            <Tarjeta
                titulo={cerrado ? 'Plan de la semana' : 'Arrastradas de semanas cerradas'}
                nota={`${lineas.length} pieza(s)${cerrado && total.arrastre ? ` · ${total.arrastre} arrastradas` : ''}`}
            >
                {lineas.length === 0 ? (
                    <p className="text-base-content/60 px-4 py-8 text-center text-sm">
                        {cerrado ? 'El plan se cerró sin piezas pendientes.' : 'No viene nada arrastrado de semanas anteriores.'}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th className="bg-base-200">Marca</th>
                                    <th className="bg-base-200">Lote</th>
                                    <th className="bg-base-200">Pieza</th>
                                    <th className="bg-base-200">Grupo</th>
                                    <th className="bg-base-200">Módulo</th>
                                    <th className="bg-base-200">Estado</th>
                                    <th className="bg-base-200">Presentada</th>
                                    <th className="bg-base-200">Liberada</th>
                                    <th className="bg-base-200 text-right">Insp.</th>
                                    <th className="bg-base-200">Origen</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lineas.map((l) => {
                                    const estado = estadoDeFila(l, fase);

                                    return (
                                        <tr key={l.id} className="hover:bg-base-200/50">
                                            <td className="font-semibold">{l.marca}</td>
                                            <td>{l.lote || '—'}</td>
                                            <td className="font-mono text-sm">{piezaDe(l)}</td>
                                            <td>{l.grupo || '—'}</td>
                                            <td className="font-mono text-sm">{l.modulo || '—'}</td>
                                            <td>
                                                <Pastilla tono={estado.tono}>{estado.texto}</Pastilla>
                                            </td>
                                            <td className="font-mono text-sm">{l.primera?.semanaFabricada || '—'}</td>
                                            <td className="font-mono text-sm">{l.primera?.semanaLiberada || '—'}</td>
                                            <td className="text-right font-mono">{l.primera ? l.primera.inspecciones : '—'}</td>
                                            <td>
                                                {l.arrastrada ? (
                                                    <Pastilla tono="info">arrastrada de {l.desde}</Pastilla>
                                                ) : (
                                                    <span className="text-base-content/50 text-xs">esta semana</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {(puedeCapturar || cerrado) && (
                    <form onSubmit={guardarNotas} className="border-base-300 flex flex-wrap items-end gap-2 border-t px-4 py-3">
                        <label className="min-w-0 flex-1 text-xs">
                            <span className="text-base-content/70 mb-1 block font-semibold">Notas de la semana</span>
                            <input
                                value={notas.data.notas}
                                onChange={(e) => notas.setData('notas', e.target.value)}
                                readOnly={!puedeCapturar}
                                className="input input-sm input-bordered w-full"
                                placeholder="ej. falta material para las TS"
                            />
                        </label>
                        {puedeCapturar && (
                            <button type="submit" disabled={notas.processing || !notas.isDirty} className="btn btn-sm">
                                <SaveIcon className="size-4" />
                                Guardar notas
                            </button>
                        )}
                    </form>
                )}
            </Tarjeta>

            {/* 3 · cómo va la semana */}
            <Tarjeta titulo="Cómo va la semana" nota={rangoSemana(semana)}>
                <div className="grid grid-cols-2 gap-3 p-4 lg:grid-cols-4">
                    <Kpi
                        titulo="Programadas"
                        valor={total.programadas}
                        pie={total.arrastre ? `${total.nuevas} nuevas + ${total.arrastre} arrastradas` : 'esta semana'}
                    />
                    <Kpi
                        titulo={F.verbo.charAt(0).toUpperCase() + F.verbo.slice(1)}
                        valor={total.fabricadas}
                        pie={
                            total.fabricadasSemana !== total.fabricadas
                                ? `${total.fabricadasSemana} esta semana`
                                : 'presentadas a inspección'
                        }
                    />
                    <Kpi
                        titulo="Cumplimiento"
                        valor={total.cumplimiento === null ? '—' : `${total.cumplimiento}%`}
                        pie="de lo programado"
                        tono={tonoDeAvance(total.cumplimiento)}
                    />
                    <Kpi
                        titulo="Pendientes"
                        valor={total.pendientes}
                        pie={
                            total.pendientes
                                ? total.empezadas
                                    ? `${total.empezadas} empezadas · ${total.sinEmpezar} sin empezar`
                                    : `pasan a la semana ${numeroSemana(semanaMas(semana, 1))}`
                                : 'nada pendiente'
                        }
                        tono={total.pendientes ? 'warn' : 'ok'}
                    />
                    <Kpi
                        titulo="Liberadas"
                        valor={total.liberadas}
                        pie={
                            total.tasaLiberacion === null
                                ? 'de las piezas del plan'
                                : `${total.tasaLiberacion}% de lo fabricado`
                        }
                        tono="ok"
                    />
                    <Kpi
                        titulo="Rechazadas"
                        valor={total.rechazadas}
                        pie={
                            total.tasaRechazo === null
                                ? `${total.rechazadasSemana} esta semana`
                                : `${total.tasaRechazo}% de lo fabricado`
                        }
                        tono={total.rechazadas ? 'error' : undefined}
                    />
                    <Kpi
                        titulo="Salieron de lo planeado"
                        valor={total.salieron === null ? '—' : `${total.salieron}%`}
                        pie={`${total.liberadas} liberadas de ${total.programadas} programadas`}
                        tono={tonoDeAvance(total.salieron, 80, 50)}
                    />
                    {total.empezadas > 0 && (
                        <Kpi
                            titulo={fase === '3' ? 'Listas para pintar' : 'Empezadas sin terminar'}
                            valor={total.empezadas}
                            pie={fase === '3' ? 'ya fabricadas, aún sin pintar' : 'pasaron armado, falta soldadura'}
                            tono="info"
                        />
                    )}
                </div>
                <Leyenda>
                    <b>Cumplimiento</b> mide a producción: se hizo lo que se dijo. <b>Salieron de lo planeado</b> es el
                    número de la reunión: de lo que se prometió, cuánto está liberado — arrastra tanto lo que no se hizo
                    como lo que se hizo mal. Y de lo pendiente se distingue lo <b>empezado</b> de lo que ni se ha
                    tocado: no hace falta que nadie lo declare, sale de las propias inspecciones.
                </Leyenda>
            </Tarjeta>

            {/* 4 · por tipo de pieza, como la pizarra del taller */}
            <Tarjeta titulo="Por tipo de pieza">
                <div className="overflow-x-auto">
                    <table className="table-sm table w-full whitespace-nowrap">
                        <thead>
                            <tr>
                                <th className="bg-base-200">Tipo</th>
                                <th className="bg-base-200 text-right">Programadas</th>
                                <th className="bg-base-200 text-right">Fabricadas</th>
                                <th className="bg-base-200 text-right">Pendientes</th>
                                <th className="bg-base-200 text-right">Liberadas</th>
                                <th className="bg-base-200 text-right">Rechazadas</th>
                                <th className="bg-base-200 text-right">Empezadas</th>
                                <th className="bg-base-200 text-right">% Fabric.</th>
                                <th className="bg-base-200 text-right">% Rechazo</th>
                                <th className="bg-base-200 text-right">% Salieron</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tipos.map((t) => {
                                const fabricado = porcentaje(t.fabricadas, t.programadas);
                                const rechazo = porcentaje(t.rechazadas, t.fabricadas);
                                const salieron = porcentaje(t.liberadas, t.programadas);

                                return (
                                    <tr key={t.tipo} className="hover:bg-base-200/50">
                                        <td className="font-semibold">{t.tipo}</td>
                                        <td className="text-right font-mono">{t.programadas}</td>
                                        <td className="text-right font-mono">{t.fabricadas}</td>
                                        <td className="text-right">
                                            {t.pendientes ? <Pastilla tono="warn">{t.pendientes}</Pastilla> : '—'}
                                        </td>
                                        <td className="text-right font-mono">{t.liberadas || '—'}</td>
                                        <td className="text-right">
                                            {t.rechazadas ? <Pastilla tono="error">{t.rechazadas}</Pastilla> : '—'}
                                        </td>
                                        <td className="text-right font-mono">{t.empezadas || '—'}</td>
                                        <td className="text-right font-mono">{fabricado === null ? '—' : `${fabricado}%`}</td>
                                        <td className="text-right font-mono">{rechazo === null ? '—' : `${rechazo}%`}</td>
                                        <td className="text-right font-mono font-bold">
                                            {salieron === null ? '—' : `${salieron}%`}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr className="bg-base-200 font-semibold">
                                <td>TOTAL</td>
                                <td className="text-right font-mono">{total.programadas}</td>
                                <td className="text-right font-mono">{total.fabricadas}</td>
                                <td className="text-right font-mono">{total.pendientes}</td>
                                <td className="text-right font-mono">{total.liberadas}</td>
                                <td className="text-right font-mono">{total.rechazadas}</td>
                                <td className="text-right font-mono">{total.empezadas}</td>
                                <td className="text-right font-mono">
                                    {total.cumplimiento === null ? '—' : `${total.cumplimiento}%`}
                                </td>
                                <td className="text-right font-mono">
                                    {total.tasaRechazo === null ? '—' : `${total.tasaRechazo}%`}
                                </td>
                                <td className="text-right font-mono">{total.salieron === null ? '—' : `${total.salieron}%`}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <Leyenda>
                    <b>% Fabric.</b> = fabricadas ÷ programadas · <b>% Rechazo</b> = rechazadas ÷ fabricadas ·{' '}
                    <b>% Salieron</b> = liberadas ÷ programadas, que es lo que de verdad llegó al final.
                </Leyenda>
            </Tarjeta>

            {/* 5 · la cola de reparación */}
            <Reparaciones piezas={vista.reparaciones} semana={semana} fase={fase} />
        </div>
    );
}
