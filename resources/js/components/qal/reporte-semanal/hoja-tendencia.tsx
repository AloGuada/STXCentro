import { EvolucionRechazo } from '@/components/qal/graficas';
import { pct } from '@/components/qal/paleta';
import { SERIE_ANIO } from '@/components/qal/reporte-semanal/datos';
import { Bloque, estadoIncidencia, FilaKpis, Hoja, Kpi, Nota, Origen } from '@/components/qal/reporte-semanal/ui';

/** Promedio de la serie ignorando las semanas sin base. */
function promedio(valores: (number | null)[]): number | null {
    const con = valores.filter((v): v is number => v !== null);
    return con.length > 0 ? con.reduce((a, b) => a + b, 0) / con.length : null;
}

/**
 * Hoja 3: la semana contra el año.
 *
 * Existe para responder a la única pregunta que un porcentaje suelto no puede:
 * si el 18 % de esta semana es un pico o es la tónica. Se calcula con la misma
 * fórmula que la hoja 1 —liberadas con rechazo previo ÷ liberadas—; con otra
 * definición, la tendencia estaría midiendo algo distinto de lo que hay arriba.
 */
export function HojaTendencia({ anio, semana }: { anio: number; semana: number }) {
    const m2 = promedio(SERIE_ANIO.map((p) => p.t2));
    const m3 = promedio(SERIE_ANIO.map((p) => p.t3));
    const actual = SERIE_ANIO.find((p) => p.semana === semana);

    const contra = (valor: number | null | undefined, media: number | null) => {
        if (valor === null || valor === undefined || media === null) {
            return undefined;
        }
        if (valor > media + 0.5) {
            return 'por encima del promedio';
        }
        return valor < media - 0.5 ? 'por debajo del promedio' : 'en el promedio';
    };

    return (
        <Hoja
            numero={3}
            titulo="Tendencia del año"
            pregunta="¿Esta semana es un pico o es la tónica?"
            meta={`${SERIE_ANIO.length} semanas`}
            submeta={`con datos en ${anio}`}
            origen={
                <Origen
                    real={false}
                    detalle="Es la serie de la hoja 1, así que depende de la misma tabla que todavía no existe."
                />
            }
        >
            <FilaKpis>
                <Kpi valor={pct(m2, 0)} etiqueta="Promedio 2T del año" estado={estadoIncidencia(m2)} />
                <Kpi valor={pct(m3, 0)} etiqueta="Promedio pintura del año" estado={estadoIncidencia(m3)} />
                <Kpi
                    valor={pct(actual?.t2 ?? null, 0)}
                    etiqueta={`2T · semana ${semana}`}
                    sub={contra(actual?.t2, m2)}
                    estado={estadoIncidencia(actual?.t2 ?? null)}
                />
                <Kpi
                    valor={pct(actual?.t3 ?? null, 0)}
                    etiqueta={`Pintura · semana ${semana}`}
                    sub={contra(actual?.t3, m3)}
                    estado={estadoIncidencia(actual?.t3 ?? null)}
                />
            </FilaKpis>

            <Bloque titulo="Incidencias semana a semana">
                <EvolucionRechazo
                    datos={SERIE_ANIO.map((p) => ({
                        semana: `S${p.semana}`,
                        fabricacion: p.t2 ?? 0,
                        pintura: p.t3 ?? 0,
                    }))}
                />
            </Bloque>

            <Nota>
                Las dos series se dibujan separadas y no se promedian: hoy ni son las mismas piezas ni tienen el mismo
                nivel, así que una media entre fabricación y pintura sería un número que no le corresponde a ninguna de
                las dos.
            </Nota>
        </Hoja>
    );
}
