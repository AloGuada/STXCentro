import { BarrasRanking } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';
import { totalMontaje, type FilaMontaje } from '@/components/qal/reporte-semanal/datos';
import {
    Bloque,
    estadoMontaje,
    FilaKpis,
    Hoja,
    Kpi,
    ListaValores,
    Nota,
    Origen,
    Pastilla,
    porcentaje,
    Rejilla2,
    Tabla,
} from '@/components/qal/reporte-semanal/ui';

/**
 * Hojas 4 y 5: montaje e incidencias en obra, y las de pintura.
 *
 * Son la misma cuenta con otras etiquetas, así que se escriben una vez. Lo que
 * no se puede colapsar son las **dos áreas**: el formato publica sus
 * porcentajes por separado porque no significan lo mismo —una incidencia de
 * taller es un defecto que se escapó de planta; una de montaje o de obra es un
 * problema aparecido en sitio, y se repara más caro—. Un solo porcentaje
 * escondería cuál de las dos manda.
 *
 * El denominador es siempre **piezas montadas**: responde «de lo que ya está en
 * obra, cuánto dio problema».
 */
export function HojaMontaje({
    numero,
    titulo,
    pregunta,
    semana,
    filas,
    montadasSemana,
    nombreA,
    nombreB,
    columnaA,
    columnaB,
    variante,
    notaAreas,
}: {
    numero: number;
    titulo: string;
    pregunta: string;
    semana: number;
    filas: FilaMontaje[];
    montadasSemana: number;
    nombreA: string;
    nombreB: string;
    columnaA: string;
    columnaB: string;
    variante: 'montaje' | 'pintura';
    notaAreas: string;
}) {
    const T = totalMontaje(filas);
    const total = T.a + T.b;
    const totalSemana = T.aSemana + T.bSemana;
    const pi = porcentaje(total, T.montadas);

    const porcentajeCelda = (parte: number, denominador: number) => {
        const p = porcentaje(parte, denominador);
        return p === null ? '—' : <Pastilla texto={pct(p, 2)} estado={estadoMontaje(p)} />;
    };

    return (
        <Hoja
            numero={numero}
            titulo={titulo}
            pregunta={pregunta}
            meta="Acumulado del año"
            submeta={`al corte de la semana ${semana}`}
            origen={
                <Origen
                    real
                    detalle="Sale de qal_obra_montaje y qal_obra_incidencias, que llena el módulo de incidencias en obra."
                />
            }
        >
            <FilaKpis>
                {variante === 'montaje' ? (
                    <>
                        <Kpi valor={num(T.montadas)} etiqueta="Piezas montadas" sub="acumulado del año" />
                        <Kpi
                            valor={num(total)}
                            etiqueta="Piezas con incidencia"
                            sub={`${num(T.a)} de taller · ${num(T.b)} de montaje`}
                            estado={total > 0 ? 'med' : 'ok'}
                        />
                        <Kpi
                            valor={pct(pi, 2)}
                            etiqueta="% de incidencias"
                            sub="sobre lo montado"
                            estado={estadoMontaje(pi)}
                        />
                        <Kpi
                            valor={num(totalSemana)}
                            etiqueta="De esta semana"
                            sub={`semana ${semana}`}
                            estado={totalSemana > 0 ? 'med' : 'ok'}
                        />
                    </>
                ) : (
                    <>
                        <Kpi valor={num(total)} etiqueta="Piezas con incidencia" sub="de pintura" />
                        <Kpi valor={num(T.a)} etiqueta="En taller de pintura" estado={T.a > 0 ? 'med' : 'ok'} />
                        <Kpi valor={num(T.b)} etiqueta="En obra" estado={T.b > 0 ? 'med' : 'ok'} />
                        <Kpi valor={pct(pi, 2)} etiqueta="% sobre lo montado" estado={estadoMontaje(pi)} />
                    </>
                )}
            </FilaKpis>

            <Rejilla2>
                <div className="space-y-2">
                    <Bloque titulo="% Incidencias totales" apunte="acumulado">
                        <ListaValores
                            filas={[
                                { etiqueta: nombreA, valor: num(T.a) },
                                { etiqueta: nombreB, valor: num(T.b) },
                                { etiqueta: 'Total de incidencias', valor: num(total) },
                                { etiqueta: 'Total de piezas montadas', valor: num(T.montadas) },
                                { etiqueta: `% ${nombreA.toLowerCase()}`, valor: porcentajeCelda(T.a, T.montadas) },
                                { etiqueta: `% ${nombreB.toLowerCase()}`, valor: porcentajeCelda(T.b, T.montadas) },
                                { etiqueta: '% total', valor: porcentajeCelda(total, T.montadas), destacada: true },
                            ]}
                        />
                    </Bloque>
                    <Nota>
                        Los tres porcentajes se calculan sobre las <b>piezas montadas</b>, que es el denominador del
                        formato: responde «de lo que ya está en obra, cuánto dio problema». {notaAreas}
                    </Nota>
                </div>

                <div className="space-y-2">
                    <Bloque titulo="% Incidencias de la semana" apunte={`semana ${semana}`}>
                        <ListaValores
                            filas={[
                                { etiqueta: nombreA, valor: num(T.aSemana) },
                                { etiqueta: nombreB, valor: num(T.bSemana) },
                                { etiqueta: 'Incidencias totales', valor: num(totalSemana), destacada: true },
                                { etiqueta: 'Piezas montadas en la semana', valor: num(montadasSemana) },
                                {
                                    etiqueta: '% de la semana',
                                    valor:
                                        montadasSemana > 0 ? (
                                            porcentajeCelda(totalSemana, montadasSemana)
                                        ) : (
                                            <Pastilla texto="sin montaje capturado" estado="neu" />
                                        ),
                                },
                            ]}
                        />
                    </Bloque>
                    <Nota>
                        {totalSemana > 0 ? (
                            <>
                                El formato oficial sólo cuenta las incidencias de la semana; aquí va además el montaje
                                de esa semana, porque dos incidencias sobre 20 piezas montadas y sobre 200 no son la
                                misma noticia.
                            </>
                        ) : (
                            <>
                                <b>No se registró ninguna incidencia en la semana {semana}.</b> En el formato en Excel
                                eso se escribe a mano en «Observaciones»; aquí sale solo, para que no se confunda con
                                «no se reportó».
                            </>
                        )}
                    </Nota>
                </div>
            </Rejilla2>

            <Bloque titulo="Detalle por obra">
                <Tabla
                    columnas={[
                        'Obra',
                        'Pz montadas',
                        'Pz totales',
                        'Por montar',
                        '% avance',
                        columnaA,
                        columnaB,
                        'Total',
                        '% incidencias',
                        `${columnaA} sem`,
                        `${columnaB} sem`,
                    ]}
                    vacia="Sin avance de montaje capturado."
                    filas={filas.map((f) => [
                        f.obra,
                        num(f.montadas),
                        f.totales !== null ? num(f.totales) : <span className="text-warning text-xs">falta</span>,
                        f.totales !== null ? num(Math.max(0, f.totales - f.montadas)) : '—',
                        f.totales !== null ? (
                            <Pastilla texto={pct(porcentaje(f.montadas, f.totales), 0)} estado="neu" />
                        ) : (
                            '—'
                        ),
                        num(f.a),
                        num(f.b),
                        num(f.a + f.b),
                        porcentajeCelda(f.a + f.b, f.montadas),
                        num(f.aSemana),
                        num(f.bSemana),
                    ])}
                    pie={[
                        [
                            'TOTAL',
                            num(T.montadas),
                            '',
                            '',
                            '',
                            num(T.a),
                            num(T.b),
                            num(total),
                            porcentajeCelda(total, T.montadas),
                            num(T.aSemana),
                            num(T.bSemana),
                        ],
                    ]}
                />
                <Nota>
                    Las piezas totales de cada obra salen de su ficha en Calidad; el avance montado, del módulo de
                    incidencias en obra. Sin piezas totales no se puede calcular el avance, y la fila lo dice en vez de
                    dar un porcentaje sobre un denominador supuesto. Sólo salen las obras con avance de montaje
                    capturado: sin denominador no hay porcentaje que publicar.
                </Nota>
            </Bloque>

            <Rejilla2>
                <Bloque titulo="Incidencias de la semana por obra">
                    <BarrasRanking
                        datos={filas.map((f) => ({ nombre: f.obra, valor: f.aSemana + f.bSemana }))}
                        decimales={0}
                    />
                </Bloque>
                <Bloque titulo="Incidencias acumuladas por obra">
                    <BarrasRanking datos={filas.map((f) => ({ nombre: f.obra, valor: f.a + f.b }))} decimales={0} />
                </Bloque>
            </Rejilla2>
        </Hoja>
    );
}
