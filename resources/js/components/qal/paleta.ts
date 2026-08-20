/**
 * Los colores de las gráficas del tablero, y sólo de las gráficas.
 *
 * El resto de la pantalla usa los tokens del tema (`primary`, `base-300`…);
 * esto existe aparte porque Recharts pinta con valores de color, no con clases,
 * y porque un color de dato tiene que cumplir cosas que un color de interfaz no:
 * separarse de sus vecinos también para quien no distingue rojo y verde.
 *
 * Los dos juegos están **validados**, no elegidos a ojo (validate_palette.js,
 * seis comprobaciones: banda de luminosidad, croma mínimo, separación bajo
 * daltonismo, separación en visión normal y contraste contra el fondo):
 *
 *  - `claro` pasa las cinco salvo el contraste del ámbar (2.1:1), que la guía
 *    permite si el dato se puede leer sin depender del color. Por eso toda
 *    gráfica de este tablero lleva leyenda y etiquetas directas.
 *  - `oscuro` **no es el claro invertido**: el ámbar se vuelve a pisar
 *    (`#f59e0b` → `#d97706`) porque sobre fondo oscuro se salía de la banda de
 *    luminosidad. Verde, rojo y azul aguantan los dos fondos.
 *
 * Si se toca un color hay que volver a correr el validador. Un vecino a menos de
 * ΔE 15 en visión normal no se distingue por mucho que se vea bien en la
 * pantalla de quien lo eligió.
 */

import { useAppearance } from '@/hooks/use-appearance';

export type PaletaTablero = {
    /** Serie única: magnitud, no identidad. Casi todas las gráficas son ésta. */
    acento: string;
    /**
     * Las dos series cuando hay que distinguir identidad (fabricación contra
     * pintura). Azul y naranja quemado: el par se separa ΔE 36 en visión normal
     * y ΔE 31 bajo daltonismo, en los dos fondos.
     */
    serie1: string;
    serie2: string;
    /** Los tres veredictos. Son estado, no categorías: no se reusan para «serie 4». */
    bien: string;
    pendiente: string;
    mal: string;
    /** Rejilla y ejes: una sombra por encima del fondo, nunca protagonistas. */
    rejilla: string;
    /** Tinta de los textos de la gráfica. El texto nunca lleva el color de la serie. */
    tinta: string;
    tintaSuave: string;
};

const CLARO: PaletaTablero = {
    acento: '#2563eb',
    serie1: '#2563eb',
    serie2: '#c2410c',
    bien: '#15803d',
    pendiente: '#f59e0b',
    mal: '#e11d48',
    rejilla: '#e5e7eb',
    tinta: '#1f2937',
    tintaSuave: '#6b7280',
};

const OSCURO: PaletaTablero = {
    acento: '#2563eb',
    serie1: '#2563eb',
    serie2: '#c2410c',
    bien: '#15803d',
    // Re-pisado para el fondo oscuro; el #f59e0b del modo claro se salía de banda.
    pendiente: '#d97706',
    mal: '#e11d48',
    rejilla: '#374151',
    tinta: '#e5e7eb',
    tintaSuave: '#9ca3af',
};

export function usePaleta(): PaletaTablero {
    const { resolvedAppearance } = useAppearance();

    return resolvedAppearance === 'dark' ? OSCURO : CLARO;
}

/** Formatea un porcentaje para etiqueta directa. `null` es «no hay base». */
export function pct(valor: number | null, decimales = 1): string {
    return valor === null || !Number.isFinite(valor) ? '—' : `${valor.toFixed(decimales)}%`;
}

/** Miles con separador local, que es como se leen las piezas. */
export function num(valor: number): string {
    return valor.toLocaleString('es-MX');
}
