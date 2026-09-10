/**
 * La lista de marcas de la programación semanal.
 *
 * Esto NO es dato de ejemplo: es la regla con la que se lee lo que producción
 * pega en la caja, portada tal cual de la aplicación anterior. Producción
 * programa copiando una columna de su hoja de Excel, y esa columna llega con
 * tabulaciones, comas y cantidades escritas de tres maneras distintas. Si el
 * parseo se equivoca, la semana entera se mide contra un plan que nadie
 * escribió.
 *
 * La decisión de fondo del módulo: **la unidad es la pieza, no la cantidad**.
 * Por eso el plan se guarda como marcas y no como un número — un total no se
 * puede cruzar con lo que inspeccionó calidad, y una lista sí.
 */

/** Una marca del plan, con cuántas piezas van de ella. */
export type MarcaPlan = {
    marca: string;
    cantidad: number;
    /** La marca venía dos veces en el texto y se sumaron. Se avisa al pegar. */
    repetida: boolean;
};

/**
 * ¿Esto parece una marca?
 *
 * Basta con que tenga una letra y más de un carácter. Es deliberadamente laxo:
 * el catálogo de marcas no está en esta pantalla, y rechazar una marca buena
 * por estricto es peor que aceptar una basura que se ve en el resumen.
 */
function pareceMarca(texto: string): boolean {
    return /[A-Za-zÑñ]/.test(texto) && texto.trim().length > 1;
}

/** Normaliza una marca: mayúsculas, sin espacios y sin puntuación de cola. */
function normaliza(texto: string): string {
    return texto
        .toUpperCase()
        .replace(/\s+/g, '')
        .replace(/[·.]+$/, '');
}

/**
 * Lee el texto pegado y devuelve las marcas con su cantidad.
 *
 * Se admiten tres formas, y las tres salen de pegar de Excel:
 *
 *  - una marca por línea → una pieza
 *  - `PIP-CM1-5 x3` → tres piezas de esa marca
 *  - `PIP-CM1-5 <tab> 3` → lo mismo, cuando se pegan dos columnas
 *
 * El número final de una marca —el `1` de `PIP-CM1-1`— **es parte de la marca**,
 * no una cantidad: la cantidad sólo se separa si va escrita con una `x`. Sin esa
 * regla, media programación se leería como cantidades y el plan saldría inflado.
 */
export function leerMarcas(texto: string): MarcaPlan[] {
    const salida: MarcaPlan[] = [];
    const vistas = new Map<string, MarcaPlan>();

    const apunta = (crudo: string, cantidad: number) => {
        const marca = normaliza(crudo);
        if (!marca) {
            return;
        }
        const previa = vistas.get(marca);
        if (previa) {
            previa.cantidad += cantidad;
            previa.repetida = true;
            return;
        }
        const nueva: MarcaPlan = { marca, cantidad, repetida: false };
        vistas.set(marca, nueva);
        salida.push(nueva);
    };

    texto.split(/[\r\n]+/).forEach((linea) => {
        const campos = linea
            .split(/[\t;,]+/)
            .map((x) => x.trim())
            .filter((x) => x !== '');

        if (!campos.length) {
            return;
        }

        // «MARCA <tab> 3»: dos columnas y la segunda sólo números. Es como sale
        // de pegar dos columnas de Excel.
        if (campos.length === 2 && /^\d+$/.test(campos[1]) && pareceMarca(campos[0])) {
            apunta(campos[0], parseInt(campos[1], 10) || 1);
            return;
        }

        campos.forEach((campo) => {
            const conCantidad = campo.match(/^(.+?)\s*[xX]\s*(\d+)$/);
            if (conCantidad && pareceMarca(conCantidad[1])) {
                apunta(conCantidad[1], parseInt(conCantidad[2], 10) || 1);
            } else {
                apunta(campo, 1);
            }
        });
    });

    return salida;
}

/** El texto que se vuelve a guardar, ya normalizado. */
export function textoDeMarcas(marcas: MarcaPlan[]): string {
    return marcas.map((x) => (x.cantidad > 1 ? `${x.marca} x${x.cantidad}` : x.marca)).join('\n');
}

/**
 * El tipo de pieza que se deduce de la marca.
 *
 * La marca es NAVE-TIPO+consecutivo (`PIP-TP2-1`): el tipo son las letras del
 * segundo tramo. Sirve sólo para agrupar el resumen; si no se reconoce, la pieza
 * se cuenta igual bajo «—». No se rechaza nada por no encajar en el patrón.
 */
export function tipoDeMarca(marca: string): string {
    const partes = marca.toUpperCase().trim().split('-');
    if (partes.length < 2) {
        return '';
    }
    const letras = partes[1].match(/^[A-ZÑ]+/);
    return letras ? letras[0] : '';
}
