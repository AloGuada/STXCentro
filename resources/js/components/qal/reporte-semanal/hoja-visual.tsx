import { BarrasTotalYParte } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';
import { INSPECCION_VISUAL, totalVisual } from '@/components/qal/reporte-semanal/datos';
import {
    Bloque,
    estadoIncidencia,
    Hoja,
    Nota,
    Origen,
    Pastilla,
    porcentaje,
    Rejilla2,
    Tabla,
} from '@/components/qal/reporte-semanal/ui';

/** Porcentaje en pastilla, o el aviso de que no hay denominador. */
function pastillaPct(parte: number, total: number) {
    const p = porcentaje(parte, total);

    if (p === null) {
        return <Pastilla texto={parte > 0 ? 's/ liberadas' : '0%'} estado="neu" />;
    }

    return <Pastilla texto={pct(p, 0)} estado={estadoIncidencia(p)} />;
}

/**
 * Hoja 1: de lo liberado esta semana, cuánto costó llegar a liberarlo.
 *
 * El denominador son las piezas **liberadas**, y «con rechazo previo» es un
 * subconjunto suyo. Por eso el porcentaje no puede pasar de 100: es la parte de
 * lo entregado que costó dos vueltas o más.
 */
export function HojaVisual({ semana }: { semana: number }) {
    const T = totalVisual(INSPECCION_VISUAL);

    return (
        <Hoja
            numero={1}
            titulo="Inspección visual de la semana"
            pregunta="De lo liberado esta semana, ¿cuánto costó llegar a liberarlo?"
            meta={`Semana ${semana}`}
            submeta="2ª transformación y pintura"
            origen={
                <Origen
                    real={false}
                    detalle="Se calcula de la inspección visual, y qal_inspecciones todavía no existe."
                />
            }
        >
            <Bloque titulo="Piezas por proyecto">
                <Tabla
                    columnas={[
                        'Proyecto',
                        'Liberadas 2T',
                        'Con rechazo previo',
                        '% 2T',
                        'Liberadas pintura',
                        'Con rechazo previo',
                        '% pintura',
                    ]}
                    vacia="Ninguna pieza se liberó en esta semana."
                    filas={INSPECCION_VISUAL.map((f) => [
                        f.obra,
                        num(f.liberadas2t),
                        num(f.conRechazo2t),
                        pastillaPct(f.conRechazo2t, f.liberadas2t),
                        num(f.liberadasPintura),
                        num(f.conRechazoPintura),
                        pastillaPct(f.conRechazoPintura, f.liberadasPintura),
                    ])}
                    pie={[
                        [
                            'TOTAL',
                            num(T.liberadas2t),
                            num(T.conRechazo2t),
                            pastillaPct(T.conRechazo2t, T.liberadas2t),
                            num(T.liberadasPintura),
                            num(T.conRechazoPintura),
                            pastillaPct(T.conRechazoPintura, T.liberadasPintura),
                        ],
                    ]}
                />
            </Bloque>

            <Rejilla2>
                <Bloque titulo="2ª transformación">
                    <BarrasTotalYParte
                        datos={INSPECCION_VISUAL.map((f) => ({
                            nombre: f.obra,
                            total: f.liberadas2t,
                            parte: f.conRechazo2t,
                        }))}
                        nombreTotal="Piezas liberadas 2T"
                        nombreParte="Con rechazo previo"
                    />
                </Bloque>
                <Bloque titulo="Pintura">
                    <BarrasTotalYParte
                        datos={INSPECCION_VISUAL.map((f) => ({
                            nombre: f.obra,
                            total: f.liberadasPintura,
                            parte: f.conRechazoPintura,
                        }))}
                        nombreTotal="Piezas liberadas pintura"
                        nombreParte="Con rechazo previo"
                    />
                </Bloque>
            </Rejilla2>

            <Nota>
                El denominador son las piezas <b>liberadas</b> esta semana; «con rechazo previo» son las que, antes de
                liberarse, fueron rechazadas al menos una vez. Por eso el porcentaje nunca pasa de 100: es la parte de
                lo entregado que costó dos vueltas o más. Verde por debajo del 15 % · ámbar entre 15 y 25 % · rojo a
                partir del 25 %.
            </Nota>
        </Hoja>
    );
}
