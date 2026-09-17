import { BarrasTotalYParte } from '@/components/qal/graficas';
import { num, pct } from '@/components/qal/paleta';
import {
    Bloque,
    estadoIncidencia,
    FilaKpis,
    Hoja,
    Kpi,
    Nota,
    Origen,
    Pastilla,
    porcentaje,
    SinCaptura,
    Tabla,
} from '@/components/qal/reporte-semanal/ui';
import type { QalMetodoPnd, QalReporteSemanalPnd } from '@/types/models';

const METODOS: QalMetodoPnd[] = ['PT', 'MT', 'UT', 'RT', 'VT'];

/**
 * Hoja 2: pruebas no destructivas.
 *
 * Es la única hoja del reporte que sale de la base. Tres cosas que se corrigen
 * respecto del formato en Excel y que hay que conservar:
 *
 *  - **La unidad es el spot, no la junta.** Cada renglón del informe del
 *    laboratorio es un punto examinado; `J-18-1-2` es el segundo punto de la
 *    junta `18-1`. Contar juntas subestima lo ensayado y mueve el porcentaje de
 *    rechazo, que es el número que mira el cliente.
 *  - **Los métodos que no se usaron no salen.** En la hoja oficial aparecen con
 *    «0 %» donde Excel calculó `#¡DIV/0!`, y un 0 % se lee como «salió todo
 *    bien». Un método sin ensayos no tiene tasa.
 *  - **El avance va sobre los spots ACEPTADOS**, no sobre los realizados: un
 *    punto rechazado se ensayó, pero no cumple, y no puede contar como parte de
 *    lo entregado.
 *
 * Va en acumulado del proyecto y no de la semana, igual que el formato: lo que
 * se pactó con el cliente es el total del contrato.
 */
export function HojaPnd({ filas }: { filas: QalReporteSemanalPnd[] }) {
    // Un método sin un solo ensayo en ninguna obra no aporta dos columnas de
    // rayas; se cae de la tabla y se dice en la nota.
    const usados = METODOS.filter((m) => filas.some((f) => f.metodos[m].spots > 0));
    const fuera = METODOS.filter((m) => !usados.includes(m));

    const T = filas.reduce(
        (a, f) => ({
            spots: a.spots + f.spots,
            rechazados: a.rechazados + f.rechazados,
            aceptados: a.aceptados + f.aceptados,
            // Las obras sin plan no suman al compromiso: si sumaran cero, el
            // avance global saldría inflado por las que nadie ha capturado.
            comprometidos: a.comprometidos + (f.comprometidos ?? 0),
            conPlan: a.conPlan + (f.comprometidos === null ? 0 : 1),
        }),
        { spots: 0, rechazados: 0, aceptados: 0, comprometidos: 0, conPlan: 0 },
    );

    const rechazoTotal = porcentaje(T.rechazados, T.spots);
    const avanceTotal = T.comprometidos > 0 ? Math.min(100, (T.aceptados / T.comprometidos) * 100) : null;
    const sinPlan = filas.filter((f) => f.comprometidos === null);

    const columnas = [
        'Obra',
        'Total de PND',
        ...usados.flatMap((m) => [`${m} ok`, `${m} rech.`]),
        '% Rechazo',
        'Rechazos',
        'Piezas del proyecto',
        'Spots requeridos',
        'Spots con PND OK',
        'Avance',
        'Estatus',
    ];

    const celdaMetodo = (metodo: { spots: number; aceptados: number; rechazados: number }, cual: 'ok' | 'rech') =>
        metodo.spots > 0 ? (
            num(cual === 'ok' ? metodo.aceptados : metodo.rechazados)
        ) : (
            <span className="text-base-content/30">—</span>
        );

    return (
        <Hoja
            numero={2}
            titulo="Pruebas no destructivas"
            pregunta="¿Cuántos ensayos se hicieron y cuántos salieron rechazados?"
            meta="Acumulado del proyecto"
            submeta={usados.length > 0 ? usados.join(' · ') : 'sin ensayos capturados'}
            origen={<Origen real detalle="Sale de qal_pnd_reportes y del plan de PND de cada obra." />}
        >
            {filas.length === 0 ? (
                <SinCaptura>
                    No hay ningún informe de laboratorio capturado todavía. Los informes se teclean en{' '}
                    <b>Calidad → PND</b>, y esta hoja se llena sola en cuanto exista el primero.
                </SinCaptura>
            ) : (
                <>
                    <FilaKpis>
                        <Kpi valor={num(T.spots)} etiqueta="Ensayos realizados" sub="puntos examinados, no juntas" />
                        <Kpi
                            valor={num(T.rechazados)}
                            etiqueta="Ensayos rechazados"
                            estado={T.rechazados > 0 ? 'med' : 'ok'}
                        />
                        <Kpi
                            valor={pct(rechazoTotal)}
                            etiqueta="% de rechazo"
                            sub="sobre el total de ensayos"
                            estado={estadoIncidencia(rechazoTotal)}
                        />
                        <Kpi
                            valor={pct(avanceTotal, 0)}
                            etiqueta="Avance del plan"
                            sub={
                                T.comprometidos > 0
                                    ? `${num(T.aceptados)} aceptados de ${num(T.comprometidos)} comprometidos`
                                    : 'ninguna obra tiene plan capturado'
                            }
                        />
                    </FilaKpis>

                    <Bloque
                        titulo="Reporte general de pruebas no destructivas"
                        apunte="ensayos por método y avance de estructura principal"
                    >
                        <Tabla
                            columnas={columnas}
                            filas={filas.map((f) => {
                                const rechazo = porcentaje(f.rechazados, f.spots);
                                const avance =
                                    f.comprometidos && f.comprometidos > 0
                                        ? Math.min(100, (f.aceptados / f.comprometidos) * 100)
                                        : null;

                                return [
                                    f.obra,
                                    <b key="tot">{num(f.spots)}</b>,
                                    ...usados.flatMap((m) => [
                                        celdaMetodo(f.metodos[m], 'ok'),
                                        celdaMetodo(f.metodos[m], 'rech'),
                                    ]),
                                    <Pastilla key="pc" texto={pct(rechazo)} estado={estadoIncidencia(rechazo)} />,
                                    num(f.rechazados),
                                    f.pz_total !== null ? (
                                        num(f.pz_total)
                                    ) : (
                                        <span className="text-warning text-xs">falta</span>
                                    ),
                                    f.comprometidos !== null ? (
                                        num(f.comprometidos)
                                    ) : (
                                        <span className="text-warning text-xs">falta</span>
                                    ),
                                    num(f.aceptados),
                                    avance === null ? '—' : <b key="av">{pct(avance)}</b>,
                                    avance === null ? (
                                        <Pastilla key="es" texto="sin compromiso" estado="neu" />
                                    ) : avance >= 100 ? (
                                        <Pastilla key="es" texto="Terminado" estado="ok" />
                                    ) : (
                                        <Pastilla key="es" texto="En proceso" estado="med" />
                                    ),
                                ];
                            })}
                            pie={[
                                [
                                    'TOTAL',
                                    num(T.spots),
                                    ...usados.flatMap((m) => {
                                        const suma = filas.reduce(
                                            (a, f) => ({
                                                ok: a.ok + f.metodos[m].aceptados,
                                                re: a.re + f.metodos[m].rechazados,
                                            }),
                                            { ok: 0, re: 0 },
                                        );
                                        return [num(suma.ok), num(suma.re)];
                                    }),
                                    <Pastilla
                                        key="pc"
                                        texto={pct(rechazoTotal)}
                                        estado={estadoIncidencia(rechazoTotal)}
                                    />,
                                    num(T.rechazados),
                                    '',
                                    T.comprometidos > 0 ? num(T.comprometidos) : '—',
                                    num(T.aceptados),
                                    pct(avanceTotal),
                                    '',
                                ],
                            ]}
                        />
                    </Bloque>

                    <Bloque titulo="Ensayos por método" apunte="el total y la parte rechazada">
                        <BarrasTotalYParte
                            datos={usados.map((m) => ({
                                nombre: m,
                                total: filas.reduce((a, f) => a + f.metodos[m].spots, 0),
                                parte: filas.reduce((a, f) => a + f.metodos[m].rechazados, 0),
                            }))}
                            nombreTotal="Ensayos realizados"
                            nombreParte="Rechazados"
                        />
                    </Bloque>

                    <Bloque titulo="Marcas ensayadas" apunte="piezas distintas, no ensayos">
                        <Tabla
                            columnas={['Obra', 'Marcas con ensayo', 'Sin ningún rechazo', '%']}
                            filas={filas.map((f) => [
                                f.obra,
                                num(f.piezas_con_pnd),
                                num(f.piezas_sin_rechazo),
                                pct(porcentaje(f.piezas_sin_rechazo, f.piezas_con_pnd), 0),
                            ])}
                        />
                        <Nota>
                            En el formato oficial «Piezas con PND OK» significa dos cosas distintas según la fila: en
                            unos proyectos son <b>ensayos</b> aceptados y en otros, <b>piezas</b> distintas —264 contra
                            391—. Aquí se separan: los ensayos aceptados están en la tabla de arriba, porque son la
                            misma unidad que el compromiso; esto son marcas del informe del laboratorio.
                        </Nota>
                    </Bloque>

                    <Nota aviso={sinPlan.length > 0 || fuera.length > 0}>
                        {sinPlan.length > 0 && (
                            <>
                                <b>
                                    {sinPlan.length === 1
                                        ? '1 obra no tiene plan de PND capturado'
                                        : `${sinPlan.length} obras no tienen plan de PND capturado`}
                                </b>{' '}
                                ({sinPlan.map((f) => f.obra).join(' · ')}): sus ensayos se cuentan, pero no pueden
                                mostrar avance porque falta el denominador. Se pacta en{' '}
                                <b>Calidad → PND → Plan comprometido</b>.{' '}
                            </>
                        )}
                        {fuera.length > 0 && (
                            <>
                                Los métodos {fuera.join(', ')} no aparecen porque no tienen un solo ensayo: un método
                                que no se usó no tiene tasa de rechazo, y un «0 %» ahí se leería como que salió todo
                                bien.{' '}
                            </>
                        )}
                        El avance es <b>spots aceptados ÷ spots comprometidos</b> y se corta en 100 %: lo ensayado de
                        más sigue siendo trabajo hecho, pero no es más contrato cumplido.
                    </Nota>
                </>
            )}
        </Hoja>
    );
}
