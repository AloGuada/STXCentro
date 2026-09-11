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

/** Lo que se manda al guardar: el árbol entero. */
export type NodoParaGuardar = {
    id: number | null;
    titulo: string;
    nota: string | null;
    hijos: NodoParaGuardar[];
};
