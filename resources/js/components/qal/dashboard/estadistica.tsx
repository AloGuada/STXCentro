/**
 * Pestaña **Estadística** del tablero: ¿es real?
 *
 * Las otras dos pestañas cuentan lo que pasó. Ésta responde a la única pregunta
 * que un porcentaje suelto no puede: si la diferencia que se está mirando es una
 * señal o es ruido. Por eso aquí casi nada es una gráfica de barras — son
 * pruebas, con su n, su p y su lectura en castellano al lado.
 *
 * Reglas que vienen del tablero anterior y no se tocan:
 *
 *  - **El Cpk se calcula por espesor requerido, nunca global.** Cada proyecto
 *    pinta con un sistema distinto; un Cpk que mezcla 16 mils con 3 no describe
 *    a ninguno de los dos.
 *  - **El espesor se compara en margen, no en mils.** El histograma en mils
 *    salía con dos montañas que parecían dos procesos rotos y eran dos sistemas
 *    de pintura.
 *  - **La comparación va dentro de una etapa.** 2ª y pintura tienen tasas muy
 *    distintas: mezclarlas hace «significativo» a cualquier factor repartido de
 *    forma desigual entre ellas.
 */

import { useState } from 'react';
import {
    CAPACIDAD,
    CARTA_P,
    COBERTURA_ESPESOR,
    DESCRIPTIVA,
    ETIQUETA_FACTOR,
    MARGEN_ESPESOR,
    MIN_PIEZAS_CPK,
    PRUEBAS_DEPENDENCIA,
    TENDENCIA_RECHAZO,
    type EtapaPrueba,
    type FactorPrueba,
} from '@/components/qal/dashboard/datos';
import {
    BarraCobertura,
    Etiqueta,
    NotaCallada,
    Rejilla2,
    SelectorTarjeta,
    Tabla,
    TarjetaGrafica,
    type Tono,
} from '@/components/qal/dashboard/ui';
import { CartaP, HistogramaMargen } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';

/** El p, escrito como se escribe en un informe. */
function valorP(p: number): string {
    return p < 0.001 ? '<0.001' : p.toFixed(3);
}

/** Referencia industrial del Cpk: ≥1.33 capaz · 1.0–1.33 marginal · <1.0 no capaz. */
function veredictoCpk(cpk: number): { texto: string; tono: Tono } {
    if (cpk >= 1.33) {
        return { texto: 'Capaz', tono: 'ok' };
    }
    return cpk >= 1 ? { texto: 'Marginal', tono: 'alerta' } : { texto: 'No capaz', tono: 'malo' };
}

export function TabEstadistica() {
    const [factor, setFactor] = useState<FactorPrueba>('p2_subetapa');
    const [etapa, setEtapa] = useState<EtapaPrueba>('2ª');

    const prueba = PRUEBAS_DEPENDENCIA[etapa][factor];
    const tendencia = TENDENCIA_RECHAZO[etapa];
    const coberturaEspesor = (COBERTURA_ESPESOR.con / COBERTURA_ESPESOR.total) * 100;

    return (
        <div className="space-y-3">
            <Rejilla2>
                <TarjetaGrafica
                    titulo="Carta-p · control estadístico"
                    apunte="% de piezas rechazadas, por semana"
                    pie={
                        <>
                            {CARTA_P.preliminar ? (
                                <>
                                    <b>Límites preliminares</b> ({CARTA_P.subgrupos} semanas de 20 recomendadas). Ya
                                    sirven para vigilar —un punto sobre el UCL merece investigarse— y se irán afinando
                                    conforme entren más semanas.{' '}
                                </>
                            ) : (
                                <>Límites establecidos con {CARTA_P.subgrupos} semanas. </>
                            )}
                            Los límites se mueven porque el número de piezas cambia cada semana: una de 12 piezas
                            tolera más variación que una de 90. Un punto fuera del UCL indica causa asignable, no
                            variación normal.
                        </>
                    }
                >
                    <CartaP datos={CARTA_P.puntos} pbar={CARTA_P.pbar} />
                </TarjetaGrafica>

                <TarjetaGrafica
                    titulo="Margen sobre el espesor requerido"
                    apunte="3ª · cuánto se pasa cada pieza de su mínimo"
                    pie={
                        <>
                            <b>{num(MARGEN_ESPESOR.piezas)} piezas medidas</b> de{' '}
                            {num(MARGEN_ESPESOR.piezasPintura)} de pintura (
                            {Math.round((MARGEN_ESPESOR.piezas / MARGEN_ESPESOR.piezasPintura) * 100)} %). Margen
                            mediano <b>+{MARGEN_ESPESOR.medianaPct} %</b> (+{MARGEN_ESPESOR.medianaMils} mils sobre el
                            mínimo) · rango de {MARGEN_ESPESOR.minPct} % a +{MARGEN_ESPESOR.maxPct} %.{' '}
                            {MARGEN_ESPESOR.bajoMinimo > 0 ? (
                                <b className="text-error">{MARGEN_ESPESOR.bajoMinimo} piezas por debajo del mínimo.</b>
                            ) : (
                                'Ninguna pieza por debajo del mínimo.'
                            )}{' '}
                            Cada pieza se compara con el espesor que le pide SU proyecto, así que los sistemas de 3 y
                            de 16 mils se leen en la misma gráfica. Pintar de más tampoco es gratis: material y horas.
                        </>
                    }
                >
                    <HistogramaMargen bins={MARGEN_ESPESOR.bins} />
                </TarjetaGrafica>
            </Rejilla2>

            <TarjetaGrafica
                titulo="Capacidad de proceso · espesor de pintura"
                apunte="¿el espesor aplicado se mantiene con holgura sobre el mínimo?"
                pie={
                    <>
                        Referencia industrial: <b>≥1.33</b> capaz · <b>1.0–1.33</b> marginal · <b>&lt;1.0</b> no capaz.
                        Sólo hay límite inferior —el mínimo del proyecto—, así que Cpk = (media − mínimo) ÷ 3σ.
                        Cobertura: <b>{num(COBERTURA_ESPESOR.con)} de {num(COBERTURA_ESPESOR.total)}</b> piezas de
                        pintura tienen espesor medido ({Math.round(coberturaEspesor)} %). El resto se inspeccionó sin
                        registrar la medición; no es que no se pintaran.
                    </>
                }
                ancha
            >
                <p className="text-base-content/60 mb-3 text-xs">
                    El <b>Cpk</b> dice cuántas veces cabe la variación del proceso entre el promedio y el mínimo
                    exigido. Un 1.33 significa que el espesor tendría que empeorar mucho antes de bajar del mínimo. Se
                    calcula por <b>espesor requerido</b> porque cada proyecto pinta con un sistema distinto y
                    mezclarlos no significa nada.
                </p>
                <Tabla
                    columnas={['Espesor requerido', 'Obra', 'Piezas medidas', 'Media', 'σ', 'Cpk', 'Bajo el mínimo']}
                    atenuadas={CAPACIDAD.map((g) => g.n < MIN_PIEZAS_CPK)}
                    filas={CAPACIDAD.map((g) => {
                        const v = g.cpk !== null ? veredictoCpk(g.cpk) : null;

                        return [
                            <b key="req">{g.requerido} mils</b>,
                            <span key="obra" className="text-base-content/70 text-xs">
                                {g.obras.join(', ')}
                            </span>,
                            num(g.n),
                            g.media.toFixed(2),
                            g.sigma.toFixed(2),
                            g.n < MIN_PIEZAS_CPK ? (
                                <span key="cpk" className="text-base-content/50 text-xs">
                                    hacen falta {MIN_PIEZAS_CPK} piezas (hay {g.n})
                                </span>
                            ) : g.cpk === null || v === null ? (
                                <span key="cpk" className="text-base-content/50 text-xs">
                                    sin variación entre piezas
                                </span>
                            ) : (
                                <span key="cpk" className="inline-flex items-center gap-2">
                                    <b>{g.cpk.toFixed(2)}</b>
                                    <Etiqueta texto={v.texto} tono={v.tono} />
                                </span>
                            ),
                            g.fuera > 0 ? <b key="f" className="text-error">{g.fuera}</b> : '0',
                        ];
                    })}
                />
            </TarjetaGrafica>

            <TarjetaGrafica
                titulo="Estadística descriptiva"
                apunte="cuánto mide una pieza típica y cuánto varía"
                pie={
                    <>
                        <b>Piezas con dato</b>: piezas distintas que tienen esa medición, no registros — una pieza
                        inspeccionada tres veces cuenta una vez, con su última medición. <b>Cobertura</b>: de todas las
                        piezas que pasaron por esa etapa, en cuántas se capturó; por debajo del 30 % la fila se atenúa,
                        porque la media existe pero describe a una minoría.
                        <br />
                        <b>Mediana antes que media</b>: la mediana no se mueve aunque haya un valor disparatado, y la
                        media sí. Cuando las dos se separan mucho hay cola — media 1,124 kg contra mediana 820 kg
                        significa que la mayoría son piezas ligeras y unas pocas muy pesadas estiran el promedio.
                        <br />
                        <b>Variabilidad</b> es la σ dividida entre la media, que la vuelve comparable entre cosas
                        medidas en unidades distintas. En un dato de proceso, alta significa que ahí no hay estándar;
                        en un dato de producto, sólo que se fabrica de todo.
                    </>
                }
                ancha
            >
                <p className="text-base-content/60 mb-3 text-xs">
                    Las demás gráficas cuentan <b>cuántas piezas salen mal</b>. Esta tabla no habla de calidad:
                    describe <b>cómo es la pieza típica</b> y <b>cuánto se parecen entre sí</b>. Sirve para saber si un
                    número raro de otro panel es normal o es un error de captura, para saber qué se está capturando de
                    verdad, y para poner en contexto las tasas normalizadas, que salen de estas mismas variables.
                </p>
                <Tabla
                    columnas={[
                        'Variable',
                        'Piezas con dato',
                        'Cobertura',
                        'Media',
                        'Mediana',
                        'σ',
                        'Variabilidad',
                        'Mínimo',
                        'Máximo',
                    ]}
                    atenuadas={DESCRIPTIVA.map((v) => v.conDato / v.universo < 0.3)}
                    filas={DESCRIPTIVA.map((v) => {
                        const f = (x: number) => x.toFixed(v.decimales) + (v.unidad ? ` ${v.unidad}` : '');
                        const cv = v.cv < 15 ? 'baja' : v.cv < 40 ? 'media' : 'alta';

                        return [
                            <div key="v">
                                <b>{v.variable}</b>
                                <div className="text-base-content/50 text-xs">
                                    {v.ayuda}
                                    {v.descartados ? (
                                        <b className="text-error"> {v.descartados} valor imposible descartado.</b>
                                    ) : null}
                                </div>
                            </div>,
                            <span key="n">
                                <b>{num(v.conDato)}</b>{' '}
                                <span className="text-base-content/50 text-xs">de {num(v.universo)}</span>
                            </span>,
                            <BarraCobertura key="c" pct={(v.conDato / v.universo) * 100} />,
                            f(v.media),
                            <b key="med">{f(v.mediana)}</b>,
                            f(v.sigma),
                            <span key="cv">
                                {cv} <span className="text-base-content/50 text-xs">{v.cv} %</span>
                            </span>,
                            f(v.min),
                            f(v.max),
                        ];
                    })}
                />
            </TarjetaGrafica>

            <TarjetaGrafica
                titulo="¿El rechazo depende de…?"
                apunte="pruebas estadísticas sobre lo que ya está capturado"
                controles={
                    <>
                        <SelectorTarjeta
                            etiqueta="Factor a probar"
                            value={factor}
                            onChange={setFactor}
                            opciones={[
                                { valor: 'p2_subetapa', texto: 'Sub-etapa' },
                                { valor: 'tipo', texto: 'Tipo de pieza' },
                                { valor: 'soldador', texto: 'Soldador' },
                                { valor: 'obra', texto: 'Obra' },
                                { valor: 'inspector', texto: 'Inspector' },
                            ]}
                        />
                        <SelectorTarjeta
                            etiqueta="Etapa de la comparación"
                            value={etapa}
                            onChange={setEtapa}
                            opciones={[
                                { valor: '2ª', texto: 'Comparar dentro de 2ª' },
                                { valor: '3ª', texto: 'Comparar dentro de 3ª' },
                            ]}
                        />
                    </>
                }
                ancha
            >
                <p className="text-base-content/60 mb-3 text-xs">
                    Aquí una pieza cuenta como rechazada si su <b>veredicto final</b> lo es. En «Factores de riesgo» y
                    en la portada cuenta si fue rechazada <b>alguna vez</b>, que sale más alto porque incluye lo que ya
                    se corrigió. Son dos preguntas distintas: qué queda mal al cierre, y cuánto trabajo hubo que
                    rehacer. La comparación va siempre <b>dentro de una etapa</b>: 2ª y pintura tienen tasas muy
                    distintas, y mezclarlas vuelve «significativo» a cualquier factor repartido de forma desigual.
                </p>

                {!prueba ? (
                    <NotaCallada>
                        En {etapa} no hay muestra para probar el {ETIQUETA_FACTOR[factor]}: hacen falta al menos dos
                        categorías con 5 piezas cada una y 20 piezas en total. Algunos factores sólo existen en una
                        etapa —la sub-etapa es de 2ª, el soldador casi también—, y en la otra la prueba no aplica.
                    </NotaCallada>
                ) : (
                    <>
                        <div className="mb-3 flex flex-wrap items-center gap-2">
                            <Etiqueta
                                texto={prueba.p < 0.05 ? 'Sí influye' : 'Sin evidencia de que influya'}
                                tono={prueba.p < 0.05 ? 'malo' : 'ok'}
                            />
                            <span className="text-base-content/60 text-xs">
                                chi² = <b>{prueba.chi2.toFixed(2)}</b> · gl = {prueba.gl} · p ={' '}
                                <b>{valorP(prueba.p)}</b> · fuerza de la relación:{' '}
                                <b>{prueba.v >= 0.4 ? 'fuerte' : prueba.v >= 0.2 ? 'moderada' : 'débil'}</b> (V de
                                Cramér {prueba.v.toFixed(2)}) · {num(prueba.piezas)} piezas de {etapa}
                            </span>
                        </div>

                        <Tabla
                            columnas={[
                                ETIQUETA_FACTOR[factor].charAt(0).toUpperCase() + ETIQUETA_FACTOR[factor].slice(1),
                                'Piezas',
                                'Rechazadas',
                                '% rechazo',
                                'Frente a lo esperado',
                            ]}
                            filas={prueba.detalle.map((d) => [
                                <b key="k">{d.categoria}</b>,
                                num(d.n),
                                num(d.rechazadas),
                                <b key="p">{pct(d.pct, 0)}</b>,
                                <span
                                    key="z"
                                    className={
                                        d.z >= 2
                                            ? 'text-error'
                                            : d.z <= -2
                                              ? 'text-success'
                                              : 'text-base-content/50'
                                    }
                                >
                                    {d.z >= 2
                                        ? 'peor de lo esperado'
                                        : d.z <= -2
                                          ? 'mejor de lo esperado'
                                          : 'dentro de lo normal'}{' '}
                                    <span className="text-base-content/50 text-xs">
                                        ({d.esperadas < 1 ? d.esperadas.toFixed(1) : Math.round(d.esperadas)}{' '}
                                        esperadas)
                                    </span>
                                </span>,
                            ])}
                        />

                        <p className="text-base-content/60 mt-2 text-xs">
                            <b>Lectura:</b>{' '}
                            {prueba.p < 0.05 ? (
                                <>
                                    con una tasa general del {pct(prueba.base, 0)},{' '}
                                    <b>{prueba.detalle[0].categoria}</b> se rechaza al {pct(prueba.detalle[0].pct, 0)} y{' '}
                                    <b>{prueba.detalle[prueba.detalle.length - 1].categoria}</b> al{' '}
                                    {pct(prueba.detalle[prueba.detalle.length - 1].pct, 0)}. La diferencia es mayor que
                                    la que daría el azar, así que ahí hay algo que mirar — pero la prueba dice{' '}
                                    <i>que</i> difieren, no <i>por qué</i>: puede ser el {ETIQUETA_FACTOR[factor]} o
                                    algo que viaja con él.
                                </>
                            ) : (
                                <>
                                    las diferencias de un {ETIQUETA_FACTOR[factor]} a otro caben dentro de lo que
                                    produce el azar con esta cantidad de piezas. No prueba que sean iguales: con más
                                    datos podría aparecer una diferencia real.
                                </>
                            )}
                            {prueba.fuera > 0 && (
                                <>
                                    {' '}
                                    <b>{prueba.fuera} categoría(s)</b> con menos de 5 piezas quedaron fuera para no
                                    distorsionar.
                                </>
                            )}
                            {prueba.celdasBajas > 0 && (
                                <>
                                    {' '}
                                    <b className="text-error">Aviso:</b> {prueba.celdasBajas} celda(s) esperaban menos
                                    de 5 casos; tómalo como indicio.
                                </>
                            )}
                        </p>
                    </>
                )}

                <div className="border-base-300 mt-4 border-t pt-3">
                    <h4 className="text-sm font-semibold">¿Está mejorando con el tiempo?</h4>
                    <p className="text-base-content/60 mt-1 mb-2 text-xs">
                        Prueba de tendencia: mira si la proporción de piezas rechazadas sube o baja de forma sostenida
                        semana a semana, en vez de comparar dos semanas sueltas.
                    </p>

                    <div className="flex flex-wrap items-center gap-2">
                        <Etiqueta
                            texto={
                                tendencia.p < 0.05 ? (tendencia.z < 0 ? 'Mejorando' : 'Empeorando') : 'Sin tendencia clara'
                            }
                            tono={tendencia.p < 0.05 ? (tendencia.z < 0 ? 'ok' : 'malo') : 'neutro'}
                        />
                        <span className="text-base-content/60 text-xs">
                            z = {tendencia.z.toFixed(2)} · p = {valorP(tendencia.p)} · {tendencia.semanas} semanas de{' '}
                            {etapa}
                        </span>
                    </div>

                    <p className="text-base-content/60 mt-2 text-xs">
                        {tendencia.puntos.map((p, i) => (
                            <span key={p.semana}>
                                {i > 0 && ' · '}
                                {p.semana}: <b>{pct(p.pct, 0)}</b>{' '}
                                <span className="opacity-70">
                                    ({p.rechazadas}/{p.n})
                                </span>
                            </span>
                        ))}
                    </p>

                    <p className="text-base-content/60 mt-2 text-xs">
                        {tendencia.p < 0.05
                            ? tendencia.z < 0
                                ? 'El rechazo baja de forma sostenida: lo que se está haciendo funciona.'
                                : 'El rechazo sube de forma sostenida. Conviene revisar qué cambió: obra, personal, tipo de pieza o criterio de inspección.'
                            : 'Sube y baja, pero sin dirección: con estas semanas no se puede decir que mejore ni que empeore.'}
                        {tendencia.semanas < 6 && (
                            <> Con {tendencia.semanas} semanas la prueba es preliminar; gana solidez a partir de 6.</>
                        )}
                    </p>
                </div>
            </TarjetaGrafica>
        </div>
    );
}
