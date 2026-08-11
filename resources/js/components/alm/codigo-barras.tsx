/**
 * Código de barras Code 39.
 *
 * Se dibuja en el navegador con SVG en vez de pedirle una imagen al servidor:
 * la hoja de etiquetas imprime decenas a la vez y así no hay ni una petición
 * por etiqueta.
 *
 * Code 39 y no Code 128 porque el catálogo maneja códigos cortos en mayúsculas
 * (`ART-00013`, `PUL-4120-07`): Code 39 los cubre completos, lo lee cualquier
 * lector sin configurarlo y su tabla cabe aquí. El precio es que ocupa más
 * ancho por carácter, que en una etiqueta de anaquel no estorba.
 */

/**
 * Cada carácter son 9 elementos que alternan barra y espacio empezando por
 * barra: `n` es angosto (1 módulo) y `w` es ancho (3 módulos).
 */
const PATRONES: Record<string, string> = {
    '0': 'nnnwwnwnn',
    '1': 'wnnwnnnnw',
    '2': 'nnwwnnnnw',
    '3': 'wnwwnnnnn',
    '4': 'nnnwwnnnw',
    '5': 'wnnwwnnnn',
    '6': 'nnwwwnnnn',
    '7': 'nnnwnnwnw',
    '8': 'wnnwnnwnn',
    '9': 'nnwwnnwnn',
    A: 'wnnnnwnnw',
    B: 'nnwnnwnnw',
    C: 'wnwnnwnnn',
    D: 'nnnnwwnnw',
    E: 'wnnnwwnnn',
    F: 'nnwnwwnnn',
    G: 'nnnnnwwnw',
    H: 'wnnnnwwnn',
    I: 'nnwnnwwnn',
    J: 'nnnnwwwnn',
    K: 'wnnnnnnww',
    L: 'nnwnnnnww',
    M: 'wnwnnnnwn',
    N: 'nnnnwnnww',
    O: 'wnnnwnnwn',
    P: 'nnwnwnnwn',
    Q: 'nnnnnnwww',
    R: 'wnnnnnwwn',
    S: 'nnwnnnwwn',
    T: 'nnnnwnwwn',
    U: 'wwnnnnnnw',
    V: 'nwwnnnnnw',
    W: 'wwwnnnnnn',
    X: 'nwnnwnnnw',
    Y: 'wwnnwnnnn',
    Z: 'nwwnwnnnn',
    '-': 'nwnnnnwnw',
    '.': 'wwnnnnwnn',
    ' ': 'nwwnnnwnn',
    $: 'nwnwnwnnn',
    '/': 'nwnwnnnwn',
    '+': 'nwnnnwnwn',
    '%': 'nnnwnwnwn',
    /** Delimitador: abre y cierra todo código Code 39. */
    '*': 'nwnnwnwnn',
};

/** Lo que Code 39 sabe representar. Fuera de esto, no hay etiqueta. */
export function esCodificable(valor: string): boolean {
    const limpio = valor.trim().toUpperCase();

    return limpio.length > 0 && [...limpio].every((c) => c in PATRONES && c !== '*');
}

type Barra = { x: number; ancho: number };

/**
 * Convierte el texto en las barras negras y su posición, medidas en módulos.
 * Los espacios no se dibujan: son el papel entre barra y barra.
 */
function barrasDe(valor: string): { barras: Barra[]; modulos: number } {
    const texto = `*${valor.trim().toUpperCase()}*`;
    const barras: Barra[] = [];
    let x = 0;

    [...texto].forEach((caracter, indice) => {
        const patron = PATRONES[caracter];

        if (!patron) {
            return;
        }

        [...patron].forEach((elemento, posicion) => {
            const ancho = elemento === 'w' ? 3 : 1;

            // Los elementos pares son barra; los impares, espacio.
            if (posicion % 2 === 0) {
                barras.push({ x, ancho });
            }

            x += ancho;
        });

        // Separación entre caracteres: un módulo de espacio, salvo al final.
        if (indice < texto.length - 1) {
            x += 1;
        }
    });

    return { barras, modulos: x };
}

type Props = {
    valor: string;
    /** Alto de las barras en px. El ancho sale del contenido. */
    altura?: number;
    /** Imprime el código en texto debajo, como toda etiqueta de anaquel. */
    mostrarTexto?: boolean;
    className?: string;
};

export function CodigoBarras({ valor, altura = 48, mostrarTexto = true, className }: Props) {
    if (!esCodificable(valor)) {
        return (
            <span className="text-base-content/40 text-xs italic">
                {valor.trim() === '' ? 'Sin código de barras' : 'Código no imprimible'}
            </span>
        );
    }

    const { barras, modulos } = barrasDe(valor);
    const alturaTexto = mostrarTexto ? 14 : 0;

    return (
        <div className={className}>
            <svg
                viewBox={`0 0 ${modulos} ${altura + alturaTexto}`}
                className="h-auto w-full"
                role="img"
                aria-label={`Código de barras ${valor}`}
            >
                <rect width={modulos} height={altura + alturaTexto} fill="#ffffff" />
                {barras.map((barra) => (
                    <rect
                        key={barra.x}
                        x={barra.x}
                        y={0}
                        width={barra.ancho}
                        height={altura}
                        fill="#000000"
                    />
                ))}
            </svg>
            {mostrarTexto && (
                <div className="text-center font-mono text-[10px] tracking-widest text-black">{valor}</div>
            )}
        </div>
    );
}
