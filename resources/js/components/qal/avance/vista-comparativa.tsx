/**
 * La comparativa entre obras: la portada del módulo.
 *
 * **No se suman los planes de obras distintas.** Una obra adelantada taparía a
 * otra retrasada y el total parecería sano estando las dos mal, así que cada
 * obra es una tarjeta con sus dos transformaciones por separado.
 *
 * Las obras sin movimiento **se apartan, no se ocultan**: programar es un acto
 * hacia adelante, y la obra que todavía no ha producido nada es justo la que hay
 * que poder abrir para escribirle su primera semana.
 */

import { ChevronDownIcon, ChevronRightIcon } from 'lucide-react';
import { useState } from 'react';
import { Barra, Pastilla, Tarjeta } from '@/components/qal/ui';
import { cn } from '@/lib/utils';
import { lineasDeLaSemana, totalizar, type IndicePiezas } from './calculo';
import { FASES, type DefinicionFase, type Fase, type Plan, type PiezaVista } from './datos';
import { Reparaciones } from './reparaciones';
import { numeroSemana, rangoSemana } from './semanas';

type ResumenFase = {
    programadas: number;
    fabricadas: number;
    pendientes: number;
    empezadas: number;
    enReparacion: number;
    arrastre: number;
    fabricadasSemana: number;
    cumplimiento: number | null;
    salieron: number | null;
};

type ResumenObra = {
    obra: string;
    fases: Record<Fase, ResumenFase>;
    /** ¿Pasó algo esta semana? Programado, fabricado, liberado, arrastrado o en reparación. */
    viva: boolean;
};

function resumirObra(
    obra: string,
    semana: string,
    planes: Plan[],
    indices: Record<Fase, IndicePiezas>,
    enProceso: Record<Fase, Map<string, number>>,
): ResumenObra {
    const fases = {} as Record<Fase, ResumenFase>;

    FASES.forEach((F) => {
        const lineas = lineasDeLaSemana(planes, indices[F.id], enProceso[F.id], obra, F.id, semana);
        const total = totalizar(lineas);

        // Lo de la obra entera, no sólo lo del plan: una pieza rechazada puede
        // venir de un plan de hace un mes y sigue siendo carga de esta obra.
        const suyas = [...indices[F.id].entries()]
            .filter(([clave]) => clave.endsWith(`|${obra}`))
            .flatMap(([, piezas]) => piezas);

        fases[F.id] = {
            programadas: total.programadas,
            fabricadas: total.fabricadas,
            pendientes: total.pendientes,
            empezadas: total.empezadas,
            arrastre: total.arrastre,
            enReparacion: suyas.filter((p) => p.estatus === 'Rechazado').length,
            fabricadasSemana: suyas.filter((p) => p.semanaFabricada === semana).length,
            cumplimiento: total.cumplimiento,
            salieron: total.salieron,
        };
    });

    const viva = FASES.some((F) => {
        const x = fases[F.id];
        return x.programadas > 0 || x.fabricadasSemana > 0 || x.enReparacion > 0 || x.arrastre > 0;
    });

    return { obra, fases, viva };
}

function BloqueFase({
    definicion,
    resumen,
    onAbrir,
}: {
    definicion: DefinicionFase;
    resumen: ResumenFase;
    onAbrir: () => void;
}) {
    const conPlan = resumen.programadas > 0;
    const color =
        resumen.cumplimiento === null
            ? 'text-base-content/60'
            : resumen.cumplimiento >= 100
              ? 'text-success'
              : resumen.cumplimiento >= 70
                ? 'text-warning'
                : 'text-error';

    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onAbrir();
            }}
            className="border-base-300 hover:border-primary/50 flex-1 rounded-lg border p-2.5 text-left transition-colors"
        >
            <div className="text-base-content/60 text-xs font-medium">{definicion.nombre}</div>

            {conPlan ? (
                <>
                    <div className={cn('text-xl font-bold', color)}>
                        {resumen.fabricadas}{' '}
                        <span className="text-base-content/50 text-sm font-normal">de {resumen.programadas}</span>
                    </div>
                    <Barra porcentaje={resumen.cumplimiento} />
                    <div className="text-base-content/50 mt-1 text-xs">
                        {resumen.cumplimiento !== null && `${resumen.cumplimiento}% hecho`}
                        {resumen.salieron !== null && ` · ${resumen.salieron}% salieron`}
                    </div>
                </>
            ) : (
                <>
                    <div className="text-base-content/60 text-xl font-bold">
                        {resumen.fabricadasSemana}{' '}
                        <span className="text-sm font-normal">{definicion.verbo}</span>
                    </div>
                    <div className="text-base-content/50 mt-1 text-xs">sin plan escrito</div>
                </>
            )}

            <div className="mt-1.5 flex flex-wrap gap-1">
                {resumen.pendientes > 0 && <Pastilla tono="warn">{resumen.pendientes} pend.</Pastilla>}
                {resumen.empezadas > 0 && <Pastilla tono="info">{resumen.empezadas} empez.</Pastilla>}
                {resumen.enReparacion > 0 && <Pastilla tono="error">{resumen.enReparacion} rep.</Pastilla>}
            </div>
        </button>
    );
}

function TarjetaObra({
    resumen,
    onAbrir,
}: {
    resumen: ResumenObra;
    onAbrir: (obra: string, fase?: Fase) => void;
}) {
    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => onAbrir(resumen.obra)}
            onKeyDown={(e) => e.key === 'Enter' && onAbrir(resumen.obra)}
            className={cn(
                'border-base-300 bg-base-100 hover:border-primary/50 cursor-pointer rounded-xl border p-3 transition-colors',
                !resumen.viva && 'opacity-60',
            )}
        >
            <div className="mb-2 flex items-center gap-2">
                <div className="font-semibold">{resumen.obra}</div>
                {!resumen.viva && <Pastilla>sin movimiento</Pastilla>}
            </div>

            {resumen.viva ? (
                <div className="flex flex-col gap-2 sm:flex-row">
                    {FASES.map((F) => (
                        <BloqueFase
                            key={F.id}
                            definicion={F}
                            resumen={resumen.fases[F.id]}
                            onAbrir={() => onAbrir(resumen.obra, F.id)}
                        />
                    ))}
                </div>
            ) : (
                <p className="text-base-content/60 text-sm">Nada programado ni fabricado esta semana.</p>
            )}

            <div className="text-primary mt-2 text-xs font-semibold">
                {resumen.viva ? 'Abrir la obra →' : 'Programar esta obra →'}
            </div>
        </div>
    );
}

export function VistaComparativa({
    semana,
    obras,
    planes,
    indices,
    enProceso,
    piezas,
    onAbrir,
}: {
    semana: string;
    obras: string[];
    planes: Plan[];
    indices: Record<Fase, IndicePiezas>;
    enProceso: Record<Fase, Map<string, number>>;
    piezas: PiezaVista[];
    onAbrir: (obra: string, fase?: Fase) => void;
}) {
    const [verQuietas, setVerQuietas] = useState(false);

    const resumenes = obras
        .map((obra) => resumirObra(obra, semana, planes, indices, enProceso))
        .sort((a, b) => a.obra.localeCompare(b.obra, 'es'));

    const vivas = resumenes.filter((r) => r.viva);
    const quietas = resumenes.filter((r) => !r.viva);

    return (
        <div className="space-y-4">
            <Tarjeta
                titulo={`Semana ${numeroSemana(semana)}`}
                nota={rangoSemana(semana)}
                acciones={
                    <span className="text-base-content/60 text-sm">
                        {vivas.length} con movimiento · {resumenes.length} en total
                    </span>
                }
            >
                <p className="text-base-content/60 px-4 py-3 text-sm">
                    Cada obra lleva un plan por transformación: <b>2ª fabricación</b> y <b>3ª pintura</b> van a ritmos
                    distintos y no se pueden medir juntas. Entra en una obra para escribir su programación.
                </p>
            </Tarjeta>

            {vivas.length > 0 ? (
                <div className="grid gap-3 lg:grid-cols-2">
                    {vivas.map((r) => (
                        <TarjetaObra key={r.obra} resumen={r} onAbrir={onAbrir} />
                    ))}
                </div>
            ) : (
                <div className="border-base-300 bg-base-100 text-base-content/60 rounded-xl border p-10 text-center">
                    Ninguna obra tiene movimiento esta semana.
                    <div className="mt-1 text-sm">Abre cualquiera de abajo para programarla.</div>
                </div>
            )}

            {quietas.length > 0 && (
                <Tarjeta
                    titulo="Otras obras dadas de alta"
                    nota={`${quietas.length} sin movimiento esta semana`}
                    acciones={
                        <button
                            type="button"
                            onClick={() => setVerQuietas((v) => !v)}
                            className="btn btn-ghost btn-xs"
                        >
                            {verQuietas ? (
                                <ChevronDownIcon className="size-4" />
                            ) : (
                                <ChevronRightIcon className="size-4" />
                            )}
                            {verQuietas ? 'ocultar' : 'mostrar para programarlas'}
                        </button>
                    }
                >
                    {verQuietas && (
                        <div className="grid gap-3 p-4 lg:grid-cols-2">
                            {quietas.map((r) => (
                                <TarjetaObra key={r.obra} resumen={r} onAbrir={onAbrir} />
                            ))}
                        </div>
                    )}
                </Tarjeta>
            )}

            {/* La cola de reparacion es de todas las obras a la vez: aqui si se
                suma, porque una pieza parada frena al taller sin importar de
                que obra sea. Lo que no se suma son los planes. */}
            <Reparaciones piezas={piezas} semana={semana} />
        </div>
    );
}
