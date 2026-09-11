/**
 * La hoja impresa: la A4 apaisada con las cuatro isométricas, lista para
 * «Guardar como PDF».
 *
 * Se monta en un portal al final de <body> y sólo existe mientras se imprime:
 * en pantalla no se ve, y al imprimir se esconde todo lo demás. Las imágenes
 * pesan varios MB, así que se sueltan al cerrar el diálogo de impresión.
 */

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { generarHoja, type CordonHoja, type HojaGenerada, type ModoHoja } from './hoja';
import type { CordonVisor, MarcaVisor } from './tipos';

export type CabeceraHoja = { titulo: string; subtitulo: string; resumen: ReactNode };

/** Una línea del pie. `completa` ocupa su propio renglón y no se parte. */
export type Nota = { texto: ReactNode; color?: string; completa?: boolean };

type Encargo = { hoja: HojaGenerada; cabecera: CabeceraHoja; notas: Nota[] };

const ESTILOS = `
.hoja-impresa { display: none; }
@page { size: A4 landscape; margin: 8mm; }
@media print {
  html, body { height: auto !important; overflow: visible !important; background: #fff !important; }
  body > *:not(.hoja-impresa) { display: none !important; }
  .hoja-impresa { display: block; font: 10px/1.35 system-ui, sans-serif; color: #111; }
  .hoja-impresa .cab { display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 1.5px solid #111; padding-bottom: 3mm; margin-bottom: 3mm; }
  .hoja-impresa .cab h2 { font-size: 16px; font-weight: 700; margin: 0 0 1mm; }
  .hoja-impresa .cab .sub { font-size: 10px; color: #444; }
  .hoja-impresa .cab .tot { text-align: right; font-size: 10px; }
  .hoja-impresa .cab .tot b { font-size: 13px; }
  .hoja-impresa .rejilla { display: grid; grid-template-columns: 1fr 1fr; gap: 3mm; }
  .hoja-impresa .rejilla.alta { grid-template-columns: repeat(4, 1fr); }
  .hoja-impresa figure { margin: 0; break-inside: avoid; }
  .hoja-impresa img { width: 100%; display: block; border: 0.4px solid #bbb; }
  .hoja-impresa figcaption { font-size: 9px; color: #444; padding-top: 1mm; }
  .hoja-impresa .pie { margin-top: 3mm; padding-top: 2mm; border-top: 0.4px solid #bbb;
    font-size: 8.5px; color: #555; display: flex; flex-wrap: wrap; gap: 1mm 6mm; }
  .hoja-impresa .pie .completa { flex: 1 1 100%; min-width: 0; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; }
  /* Con borde y no con fondo: Chrome no imprime fondos si no se marca
     «Gráficos de fondo», y la leyenda saldría con el cuadro en blanco. */
  .hoja-impresa .llave { display: inline-block; width: 0; height: 0; vertical-align: 0;
    margin-right: 3px; border: 4.5px solid currentColor; }
}`;

function HojaImpresa({ hoja, cabecera, notas, onTerminar }: Encargo & { onTerminar: () => void }) {
    const raiz = useRef<HTMLDivElement>(null);
    const terminar = useRef(onTerminar);

    useEffect(() => {
        terminar.current = onTerminar;
    }, [onTerminar]);

    // Se imprime cuando las imágenes ya están listas para pintarse: antes, la
    // hoja saldría con las celdas en blanco.
    useEffect(() => {
        let vivo = true;
        const alTerminar = () => terminar.current();
        window.addEventListener('afterprint', alTerminar);
        const imagenes = Array.from(raiz.current?.querySelectorAll('img') ?? []);
        Promise.all(imagenes.map((imagen) => imagen.decode().catch(() => undefined))).then(() => {
            if (vivo) {
                window.print();
            }
        });

        return () => {
            vivo = false;
            window.removeEventListener('afterprint', alTerminar);
        };
    }, []);

    return createPortal(
        <div ref={raiz} className="hoja-impresa">
            <style>{ESTILOS}</style>
            <div className="cab">
                <div>
                    <h2>{cabecera.titulo}</h2>
                    <div className="sub">{cabecera.subtitulo}</div>
                </div>
                <div className="tot">{cabecera.resumen}</div>
            </div>
            <div className={`rejilla ${hoja.clase}`}>
                {hoja.vistas.map((vista) => (
                    <figure key={vista.nombre}>
                        <img src={vista.src} alt={vista.nombre} />
                        <figcaption>{vista.nombre}</figcaption>
                    </figure>
                ))}
            </div>
            <div className="pie">
                {notas.map((nota, indice) => (
                    <span key={indice} className={nota.completa ? 'completa' : undefined} style={nota.color ? { color: nota.color } : undefined}>
                        {nota.texto}
                    </span>
                ))}
            </div>
        </div>,
        document.body,
    );
}

/**
 * Generar e imprimir una hoja desde cualquier pantalla: `imprimir` recibe cómo
 * prepararla, y `hoja` es lo que hay que montar para que salga.
 */
export function useHojaImpresa() {
    const [generando, setGenerando] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [encargo, setEncargo] = useState<Encargo | null>(null);

    const imprimir = async (clave: string, preparar: () => Promise<Encargo>) => {
        setGenerando(clave);
        setError(null);
        try {
            setEncargo(await preparar());
        } catch (e) {
            setError(e instanceof Error ? e.message : 'No se pudo generar la hoja.');
        } finally {
            setGenerando(null);
        }
    };

    const hoja = encargo ? <HojaImpresa {...encargo} onTerminar={() => setEncargo(null)} /> : null;

    return { generando, error, imprimir, hoja };
}

export const cordonesDeHoja = (marca: MarcaVisor): CordonHoja[] =>
    marca.cordones.map((cordon) => ({ numero: cordon.numero, puntos: cordon.puntos }));

const metros = (cordones: CordonVisor[]) => (cordones.reduce((suma, cordon) => suma + Number(cordon.largo_mm), 0) / 1000).toFixed(2);

/** Lo que dice la hoja de la marca arriba: qué es, cuánto pesa, cuánto se suelda. */
export function cabeceraDeMarca(marca: MarcaVisor): CabeceraHoja {
    const filete = marca.cordones.filter((cordon) => cordon.tipo !== 'costura');
    const costura = marca.cordones.filter((cordon) => cordon.tipo === 'costura');

    return {
        titulo: marca.marca + (marca.nombre ? ` · ${marca.nombre}` : ''),
        subtitulo: [
            `${marca.piezas} piezas`,
            `${Number(marca.peso_kg)} kg`,
            marca.bbox_mm && `${marca.bbox_mm.map((medida) => Math.round(medida)).join(' × ')} mm`,
            `${marca.ensambles} ensambles en el modelo`,
        ]
            .filter(Boolean)
            .join(' · '),
        resumen: marca.cordones.length ? (
            <>
                <b>
                    {marca.cordones.length} cordones · {metros(marca.cordones)} m
                </b>
                <br />
                {filete.length} de filete ({metros(filete)} m) · {costura.length} de costura ({metros(costura)} m)
            </>
        ) : (
            <b>Sin cordones calculados</b>
        ),
    };
}

const EXPLICACION: Record<ModoHoja, string> = {
    sombreado: 'Cada cordón sale en las cuatro vistas; el número va en su punto medio.',
    linea: 'Cada cordón sale en las cuatro vistas; el número va en su punto medio.',
    reparto:
        'Cada cordón se numera UNA vez, en una vista que lo alcanza; el reparto se equilibra entre las cuatro. Número, línea y cordón comparten color. En trazo tenue, el resto.',
    repartonum:
        'Cada cordón se numera UNA vez, en una vista que lo alcanza; el reparto se equilibra entre las cuatro. El número va sobre la propia junta. En trazo tenue, el resto.',
};

/** El pie: la leyenda de quien pide la hoja, cómo leerla y lo que quedó sin numerar. */
export function notasDeHoja(hoja: HojaGenerada, leyenda: Nota[], explicacion = EXPLICACION[hoja.modo], alFinal: Nota[] = []): Nota[] {
    return [
        ...leyenda,
        { texto: explicacion },
        ...(hoja.apretado ? [{ texto: 'Números encogidos por densidad de llamadas.', color: '#a02c00' }] : []),
        ...(hoja.modo !== 'sombreado' ? [{ texto: 'Acero en dibujo de línea.' }] : []),
        ...(hoja.ocultos.length
            ? [
                  {
                      texto: `Sin numerar por quedar tapados en las cuatro vistas (${hoja.ocultos.length}): ${hoja.ocultos.map((numero) => `S${numero}`).join(', ')}`,
                      color: '#a02c00',
                      completa: true,
                  },
              ]
            : []),
        ...alFinal,
    ];
}

const MODOS: { modo: ModoHoja; texto: string; ayuda: string }[] = [
    { modo: 'sombreado', texto: 'PDF', ayuda: 'Hoja A4 con vistas isométricas y los cordones numerados' },
    { modo: 'linea', texto: 'PDF línea', ayuda: 'La misma hoja pero en dibujo de línea, sin el acero sombreado' },
    { modo: 'reparto', texto: 'PDF reparto', ayuda: 'Dibujo de línea y cada cordón numerado UNA sola vez, en una vista desde la que se ve' },
    { modo: 'repartonum', texto: 'PDF reparto Nº', ayuda: 'Igual que PDF reparto pero con el número encima de la junta, sin línea de referencia' },
];

/** Las cuatro hojas de demo3d para una marca del modelo. */
export function BotonesHoja({ marca }: { marca: MarcaVisor }) {
    const { generando, error, imprimir, hoja } = useHojaImpresa();

    const pedir = (modo: ModoHoja) =>
        imprimir(modo, async () => {
            const generada = await generarHoja({ glbUrl: marca.glb_url, cordones: cordonesDeHoja(marca), modo });
            const leyenda: Nota[] = [
                {
                    texto: (
                        <>
                            <span className="llave" style={{ color: '#d83a00' }} />
                            cordón de soldadura, numerado S1…S{marca.cordones.length}: el mismo número de la junta en el mapeo
                        </>
                    ),
                },
            ];

            return {
                hoja: generada,
                cabecera: cabeceraDeMarca(marca),
                notas: marca.cordones.length ? notasDeHoja(generada, leyenda) : [{ texto: 'Esta marca no tiene cordones calculados.' }],
            };
        });

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {MODOS.map((opcion) => (
                <button
                    key={opcion.modo}
                    type="button"
                    title={opcion.ayuda}
                    disabled={generando !== null}
                    onClick={() => pedir(opcion.modo)}
                    className="btn btn-xs btn-outline"
                >
                    {generando === opcion.modo ? 'Generando…' : opcion.texto}
                </button>
            ))}
            {error && <span className="text-error text-xs">{error}</span>}
            {hoja}
        </div>
    );
}
