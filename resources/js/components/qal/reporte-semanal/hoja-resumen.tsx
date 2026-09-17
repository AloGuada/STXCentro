import { num, pct } from '@/components/qal/paleta';
import { totalVisual, type FilaVisual, type PuntoSerie } from '@/components/qal/reporte-semanal/datos';
import {
    Bloque,
    estadoIncidencia,
    FilaKpis,
    Hoja,
    Kpi,
    Nota,
    Origen,
    porcentaje,
} from '@/components/qal/reporte-semanal/ui';

/**
 * Hoja de portada: lo que hay que saber de la semana sin abrir el resto.
 *
 * La «lectura de la semana» se redacta a partir de los mismos números de
 * arriba, no aparte. Es la parte que en el formato en Excel se escribe a mano y
 * la que más se desincroniza: un párrafo que dice 26 % encima de una tabla que
 * dice 18 % obliga a decidir a cuál creerle.
 */
export function HojaResumen({
    anio,
    semana,
    obras,
    spotsPnd,
    incidenciasSemana,
    filas,
    kgLiberados,
    serie,
}: {
    anio: number;
    semana: number;
    obras: string[];
    spotsPnd: number;
    /** Piezas con incidencia en taller y obra durante la semana. */
    incidenciasSemana: number;
    /** La hoja 1, de donde sale la portada. */
    filas: FilaVisual[];
    /** Kilos de estructura principal liberados en la semana. */
    kgLiberados: number;
    /** La hoja 3, para comparar con la semana anterior. */
    serie: PuntoSerie[];
}) {
    const T = totalVisual(filas);
    const t2 = porcentaje(T.conRechazo2t, T.liberadas2t);
    const t3 = porcentaje(T.conRechazoPintura, T.liberadasPintura);

    // La semana anterior de la serie, para la flecha. Sin ella el porcentaje se
    // lee como bueno o malo en abstracto, que es como no leerlo.
    const previa = serie.filter((p) => p.semana < semana).slice(-1)[0];
    const contra = (hoy: number | null, antes: number | null | undefined) => {
        if (hoy === null || antes === null || antes === undefined) {
            return undefined;
        }
        const dif = hoy - antes;
        if (Math.abs(dif) <= 0.5) {
            return 'igual que la semana anterior';
        }
        return `${dif > 0 ? '▲' : '▼'} ${Math.abs(dif).toFixed(0)} pts vs. semana anterior`;
    };

    const lectura: string[] = [];
    if (t2 !== null) {
        lectura.push(
            `En 2ª transformación se liberaron ${num(T.liberadas2t)} piezas, y ${num(T.conRechazo2t)} de ellas ` +
                `habían sido rechazadas antes de llegar a liberarse: ${pct(t2, 0)}` +
                (previa?.t2 != null ? `, frente al ${pct(previa.t2, 0)} de la semana ${previa.semana}` : '') +
                `. Las otras ${num(T.liberadas2t - T.conRechazo2t)} salieron bien a la primera.`,
        );
    }
    if (t3 !== null) {
        lectura.push(
            `En pintura se liberaron ${num(T.liberadasPintura)} piezas, ${num(T.conRechazoPintura)} con rechazo ` +
                `previo: ${pct(t3, 0)}.`,
        );
    }
    if (incidenciasSemana > 0) {
        lectura.push(`Se registraron ${num(incidenciasSemana)} piezas con incidencia en taller y obra.`);
    }
    if (lectura.length === 0) {
        lectura.push('No hay inspecciones registradas en esta semana.');
    }

    return (
        <Hoja
            titulo="Reporte semanal de incidencias 2T y pintura"
            pregunta="Resumen ejecutivo · lo que hay que saber de la semana"
            meta={`Semana ${semana} · ${anio}`}
            submeta="F-STX-CA-31 · Rev. 00"
            origen={
                <Origen
                    real
                    detalle="Sale de qal_inspecciones, de PND y de las incidencias en obra: es la síntesis de las otras hojas."
                />
            }
        >
            <FilaKpis>
                <Kpi
                    valor={num(T.liberadas2t)}
                    etiqueta="Piezas liberadas 2T"
                    sub={`${num(T.liberadas2t - T.conRechazo2t)} a la primera · ${num(T.conRechazo2t)} con rechazo previo`}
                />
                <Kpi
                    valor={pct(t2, 0)}
                    etiqueta="Incidencias 2T"
                    sub={contra(t2, previa?.t2)}
                    estado={estadoIncidencia(t2)}
                />
                <Kpi
                    valor={pct(t3, 0)}
                    etiqueta="Incidencias pintura"
                    sub={contra(t3, previa?.t3)}
                    estado={estadoIncidencia(t3)}
                />
                <Kpi valor={num(Math.round(kgLiberados))} etiqueta="Kg liberados" sub="estructura principal" />
            </FilaKpis>

            <Bloque titulo="Lectura de la semana">
                <ul className="space-y-1.5">
                    {lectura.map((linea) => (
                        <li key={linea} className="text-base-content/80 flex gap-2 text-sm">
                            <span className="text-base-content/30">—</span>
                            <span>{linea}</span>
                        </li>
                    ))}
                </ul>
            </Bloque>

            <Bloque titulo="Alcance del reporte">
                <dl className="grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                    <div className="flex gap-2">
                        <dt className="text-base-content/60">Proyectos en activo:</dt>
                        <dd className="font-medium">{obras.length > 0 ? obras.join(' · ') : '—'}</dd>
                    </div>
                    <div className="flex gap-2">
                        <dt className="text-base-content/60">Ensayos no destructivos acumulados:</dt>
                        <dd className="font-medium tabular-nums">
                            {num(spotsPnd)}{' '}
                            <span className="text-base-content/50 text-xs font-normal">(real · hoja 2)</span>
                        </dd>
                    </div>
                    <div className="flex gap-2">
                        <dt className="text-base-content/60">Emitido:</dt>
                        <dd className="font-medium">
                            {new Date().toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })}
                        </dd>
                    </div>
                </dl>
            </Bloque>

            <Nota>
                Cada pieza cuenta una vez, en la semana en que se <b>liberó</b>. El porcentaje es{' '}
                <b>piezas liberadas que habían sido rechazadas antes ÷ piezas liberadas</b>: mide cuánto costó llegar a
                liberarlas, no cuántas están mal hoy. Los rechazos pueden ser de semanas anteriores; la pieza arrastra
                su historia.
            </Nota>
        </Hoja>
    );
}
