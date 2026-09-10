import type { AlmAlmacenTipo } from '@/types/models';

const TIPOS: Record<AlmAlmacenTipo, string> = {
    insumos: 'Insumos',
    montaje: 'Montaje',
    herramienta: 'Herramienta',
};

export function etiquetaDeTipo(tipo: AlmAlmacenTipo): string {
    return TIPOS[tipo] ?? tipo;
}

/**
 * Cómo se nombra el almacén en pantalla. La clave sola se repite entre obras,
 * así que se acompaña del número de obra cuando cuelga de una.
 */
export function etiquetaDeAlmacen(almacen: { clave: string; obra?: { no: string } | null }): string {
    return almacen.obra ? `${almacen.clave} · ${almacen.obra.no}` : `${almacen.clave} · Central`;
}
