/**
 * Lo que el visor 3D sabe de una marca y sus cordones, tal como lo manda
 * `GET /admin/calidad/modelos/marcas/{id}`.
 */

/** Cómo va un cordón según sus juntas: sin revisar, correcto o con defecto. */
export type EstadoCordon = 'sin' | 'correcta' | 'defecto';

export type Preparacion = {
    bisel: string;
    angulo_bisel: number | null;
    lados: number;
    penetracion: string;
    nota: string;
};

export type CordonVisor = {
    id: number;
    numero: number;
    identificador: string;
    tipo: 'filete' | 'costura';
    junta: string | null;
    piezas: string[];
    largo_mm: string;
    angulo: string | null;
    t1_mm: string | null;
    t2_mm: string | null;
    cateto_min_mm: string | null;
    cateto_max_mm: string | null;
    garganta_min_mm: string | null;
    preparacion: Preparacion | null;
    avisos: string[];
    /** En metros, en el sistema del .glb de la marca. */
    puntos: number[][];
    estado: EstadoCordon;
    correctas: number;
    con_defecto: number;
};

export type MarcaVisor = {
    id: number;
    modelo_id: number;
    marca: string;
    glb_url: string;
    ficha_url: string;
    cordones: CordonVisor[];
};

/** El color de cada estado. Naranja es «falta revisarlo», no un defecto. */
export const COLOR_ESTADO: Record<EstadoCordon, number> = {
    sin: 0xff8a1f,
    correcta: 0x22c55e,
    defecto: 0xef4444,
};

export const COLOR_SELECCION = 0x36e0ff;

export const ETIQUETA_ESTADO: Record<EstadoCordon, string> = {
    sin: 'Sin junta',
    correcta: 'Correcta',
    defecto: 'Con defecto',
};

export async function cargarMarca(id: number): Promise<MarcaVisor> {
    const respuesta = await fetch(`/admin/calidad/modelos/marcas/${id}`, { headers: { Accept: 'application/json' } });
    if (!respuesta.ok) {
        throw new Error(`No se pudo cargar la marca del modelo (HTTP ${respuesta.status}).`);
    }

    return respuesta.json();
}
