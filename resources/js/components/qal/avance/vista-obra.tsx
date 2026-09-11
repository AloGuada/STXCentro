/**
 * Una obra, una semana, una transformación.
 *
 * Cinco bloques en el orden en que se usan en la reunión: se pega el plan, se
 * mira cómo va la semana, se abre por tipo de pieza, se baja a marca por marca y
 * se termina con la carga de reparación.
 *
 * Lo que se escribe es sólo lo que no se puede deducir —qué se piensa hacer, qué
 * se da de baja y por qué—. Lo que sí se puede deducir —qué se hizo, qué pasó el
 * filtro, qué está a medias— no se pregunta: lo calcula el servidor al guardar.
 */

import { useForm, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, ClipboardCopyIcon, SaveIcon } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import { Kpi, Leyenda, Nota, Pastilla, Tarjeta, tonoDeAvance } from '@/components/qal/ui';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { estadoDeFila, porcentaje } from './calculo';
import { leerMarcas, tipoDeMarca } from './marcas';
import { Reparaciones } from './reparaciones';
import { numeroSemana, rangoSemana, semanaMas } from './semanas';
import { faseDe, FASES, type Fase, type VistaAvance } from './tipos';

/**
 * El contador en vivo debajo del área de texto.
 *
 * Sin esto, pegar sesenta marcas es un acto de fe hasta que se guarda. Además es
 * donde se avisa de lo que el parseo hizo por su cuenta: marcas repetidas que se
 * sumaron y marcas sin tipo reconocible.
 */
function ResumenPegado({ texto }: { texto: string }) {
    const marcas = useMemo(() => leerMarcas(texto), [texto]);

    if (!marcas.length) {
        return <p className="text-base-content/50 mt-2 text-xs">Nada pegado todavía.</p>;
    }

    const total = marcas.reduce((a, x) => a + x.cantidad, 0);
    const porTipo = new Map<string, number>();
    marcas.forEach((x) => {
        const tipo = tipoDeMarca(x.marca) || '—';
        porTipo.set(tipo, (porTipo.get(tipo) ?? 0) + x.cantidad);
    });

    const repetidas = marcas.filter((x) => x.repetida);
    const raras = marcas.filter((x) => !tipoDeMarca(x.marca));

    return (
        <div className="text-base-content/60 mt-2 space-y-1 text-xs">
            <div>
                <b>{total} pieza(s)</b> en {marcas.length} marca(s) ·{' '}
                {[...porTipo.entries()]
                    .sort(([a], [b]) => a.localeCompare(b, 'es'))
                    .map(([tipo, cuantas]) => `${tipo}: ${cuantas}`)
                    .join(' · ')}
            </div>
            {repetidas.length > 0 && (
                <div className="text-warning">
                    {repetidas.length} marca(s) repetidas, se han sumado:{' '}
                    {repetidas.slice(0, 5).map((x) => x.marca).join(', ')}
                </div>
            )}
            {raras.length > 0 && (
                <div className="text-warning">
                    {raras.length} sin tipo reconocible: {raras.slice(0, 5).map((x) => x.marca).join(', ')}
                </div>
            )}
        </div>
    );
}

export function VistaObra({
    obraId,
    obra,
    semana,
    fase,
    vista,
    puedeCapturar,
    onFase,
    onVolver,
}: {
    obraId: number;
    obra: string;
    semana: string;
    fase: Fase;
    vista: VistaAvance;
    puedeCapturar: boolean;
    onFase: (fase: Fase) => void;
    onVolver: () => void;
}) {
    const F = faseDe(fase);
    const { props } = usePage<SharedData & { flash?: { success?: string | null } }>();
    const { lineas, total, tipos } = vista;

    const form = useForm({
        obra_id: obraId,
        fase,
        semana,
        marcas: vista.plan?.marcas ?? '',
        bajas: vista.plan?.bajas ?? '',
        notas: vista.plan?.notas ?? '',
    });

    const guardar = (evento: FormEvent) => {
        evento.preventDefault();
        form.post('/admin/calidad/avance/programaciones', { preserveScroll: true });
    };

    /** Copiar las pendientes para pegarlas en la semana siguiente a mano. */
    const copiarPendientes = () => {
        const texto = lineas
            .filter((l) => l.pendientes > 0)
            .map((l) => (l.pendientes > 1 ? `${l.marca} x${l.pendientes}` : l.marca))
            .join('\n');
        navigator.clipboard?.writeText(texto);
    };

    const errores = [form.errors.marcas, form.errors.bajas, form.errors.notas, form.errors.semana, form.errors.obra_id].filter(Boolean);

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
            </div>

            {props.flash?.success && <div className="alert alert-success text-sm">{props.flash.success}</div>}
            {errores.length > 0 && (
                <div className="alert alert-error text-sm">
                    {errores.map((error) => (
                        <div key={error}>{error}</div>
                    ))}
                </div>
            )}

            {/* 1 · la caja donde se pega la lista */}
            <form onSubmit={guardar}>
                <Tarjeta
                    titulo={`Piezas a ${F.gerundio} en la semana ${numeroSemana(semana)}`}
                    nota={`${rangoSemana(semana)} · ${F.nombre}`}
                    acciones={
                        <>
                            <button type="button" onClick={copiarPendientes} className="btn btn-sm btn-ghost">
                                <ClipboardCopyIcon className="size-4" />
                                Copiar pendientes
                            </button>
                            {puedeCapturar && (
                                <button type="submit" disabled={form.processing} className="btn btn-sm btn-primary">
                                    <SaveIcon className="size-4" />
                                    {form.processing ? 'Guardando…' : 'Guardar'}
                                </button>
                            )}
                        </>
                    }
                >
                    <div className="grid gap-4 p-4 lg:grid-cols-2">
                        <div>
                            <textarea
                                value={form.data.marcas}
                                onChange={(e) => form.setData('marcas', e.target.value)}
                                readOnly={!puedeCapturar}
                                spellCheck={false}
                                className="textarea textarea-bordered h-48 w-full font-mono text-sm"
                                placeholder={
                                    'Pega aquí la lista de marcas, una por línea:\n\nPIP-CM1-1\nPIP-CM1-2\nPIP-TP2-7\n\nSi de una marca van varias piezas:  PIP-CM1-5 x3'
                                }
                            />
                            <ResumenPegado texto={form.data.marcas} />
                        </div>

                        <div className="space-y-3">
                            <Nota>
                                Una línea = una pieza. Pega la columna tal cual salga de tu hoja: da igual si vienen con
                                tabulaciones o comas. El tipo se saca solo de la marca.
                                {fase === '3' && (
                                    <>
                                        <br />
                                        <br />
                                        <b>Pintura lleva su propio plan.</b> Una pieza que se termina de fabricar el
                                        viernes no da tiempo a pintarse esa semana: aquí se programa lo que pintura cree
                                        que va a pintar, que puede incluir piezas fabricadas la semana pasada.
                                    </>
                                )}
                            </Nota>

                            <label className="block">
                                <span className="text-base-content/70 mb-1 block text-xs font-semibold">
                                    Notas de la semana
                                </span>
                                <textarea
                                    value={form.data.notas}
                                    onChange={(e) => form.setData('notas', e.target.value)}
                                    readOnly={!puedeCapturar}
                                    className="textarea textarea-bordered h-16 w-full text-sm"
                                    placeholder="ej. falta material para las TS"
                                />
                            </label>

                            <label className="block">
                                <span className="text-base-content/70 mb-1 block text-xs font-semibold">
                                    Piezas dadas de baja
                                </span>
                                <span className="text-base-content/50 mb-1 block text-xs">
                                    Ya no se van a fabricar. Dejan de arrastrarse — es la única forma de que una marca
                                    salga del plan para siempre. Cada una lleva su motivo: sin él, la semana se lee como
                                    incumplimiento del taller.
                                </span>
                                <textarea
                                    value={form.data.bajas}
                                    onChange={(e) => form.setData('bajas', e.target.value)}
                                    readOnly={!puedeCapturar}
                                    className="textarea textarea-bordered h-16 w-full font-mono text-sm"
                                    placeholder="una por línea, con su motivo:  PIP-CM1-5: cambio de ingeniería"
                                />
                            </label>
                        </div>
                    </div>
                </Tarjeta>
            </form>

            {/* 2 · cómo va la semana */}
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

            {/* 3 · por tipo de pieza, como la pizarra del taller */}
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

            {/* 4 · marca por marca */}
            <Tarjeta titulo="Pieza por pieza" nota={`${lineas.length} marca(s) en el plan`}>
                {lineas.length === 0 ? (
                    <p className="text-base-content/60 px-4 py-10 text-center text-sm">
                        Todavía no hay nada programado.
                        {puedeCapturar && (
                            <>
                                <br />
                                Pega arriba la lista de marcas y pulsa Guardar.
                            </>
                        )}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="table-sm table w-full whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th className="bg-base-200">Marca</th>
                                    <th className="bg-base-200">Tipo</th>
                                    <th className="bg-base-200 text-right">Piezas</th>
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
                                        <tr key={`${l.marca}|${l.desde}`} className="hover:bg-base-200/50">
                                            <td className="font-semibold">{l.marca}</td>
                                            <td>{l.tipo || '—'}</td>
                                            <td className="text-right font-mono">{l.cantidad}</td>
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
            </Tarjeta>

            {/* 5 · la cola de reparación */}
            <Reparaciones piezas={vista.reparaciones} semana={semana} fase={fase} />
        </div>
    );
}
