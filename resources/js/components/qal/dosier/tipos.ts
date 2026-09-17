/** Una sección tal como la guarda el servidor, con su número calculado por posición. */
export type NodoArbol = {
    id: number;
    numero: string;
    titulo: string;
    nota: string | null;
    hijos: NodoArbol[];
};

export type PlantillaDosier = {
    id: number;
    nombre: string;
    descripcion: string | null;
    activo: boolean;
    secciones: number;
    arbol: NodoArbol[];
};

/**
 * Una sección mientras se edita. `clave` identifica el renglón en pantalla;
 * `id` es el de la base si ya existía (nulo si es nueva): con él el servidor
 * sabe qué actualizar, qué crear y qué borrar.
 */
export type NodoEditable = {
    clave: string;
    id: number | null;
    titulo: string;
    nota: string;
    hijos: NodoEditable[];
};

export type EstatusDosier = 'borrador' | 'en_revision' | 'entregado';

/** Un renglón de la lista de dosieres. */
export type DosierDeObra = {
    id: number;
    obra: string;
    plantilla: string;
    estatus: EstatusDosier;
    entregado_at: string | null;
    secciones: number;
    /** Secciones que ya tienen al menos un PDF. */
    con_archivo: number;
    archivos: number;
    /** PDF que el motor de unión actual no puede abrir. */
    no_compatibles: number;
    actualizado: string;
};

export type ArchivoDeSeccion = {
    id: number;
    seccion_id: number;
    orden: number;
    nombre: string;
    size: number;
    paginas: number | null;
    compatible: boolean;
    subio: string | null;
    fecha: string;
    url: string;
};

/** Lo que se manda al guardar: el árbol entero. */
export type NodoParaGuardar = {
    id: number | null;
    titulo: string;
    nota: string | null;
    hijos: NodoParaGuardar[];
};
