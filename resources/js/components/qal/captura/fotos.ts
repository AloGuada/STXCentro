/**
 * La evidencia que se sube con la inspección.
 *
 * La foto de la tablet sale de 4 a 12 MB y al dosier le basta una de 1600 px,
 * así que se reduce antes de subirla: la captura no se atora en la red de la
 * nave y el servidor no guarda megapíxeles que nadie va a mirar. Un PDF, o una
 * imagen que el navegador no sepa dibujar, se sube tal cual.
 */

export type Evidencia = {
    archivo: File;
    /** Vista previa; nula para lo que no es imagen (el PDF). */
    url: string | null;
};

/** Evidencia ya guardada, al corregir una inspección. */
export type FotoGuardada = { id: number; nombre: string; url: string; esImagen: boolean };

const LADO_MAXIMO = 1600;
const CALIDAD_JPEG = 0.8;

export async function comprimirImagen(archivo: File): Promise<File> {
    if (!archivo.type.startsWith('image/')) {
        return archivo;
    }

    try {
        const imagen = await createImageBitmap(archivo);
        const escala = Math.min(1, LADO_MAXIMO / Math.max(imagen.width, imagen.height));
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(imagen.width * escala);
        lienzo.height = Math.round(imagen.height * escala);
        lienzo.getContext('2d')?.drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
        imagen.close();

        const blob = await new Promise<Blob | null>((resolver) => lienzo.toBlob(resolver, 'image/jpeg', CALIDAD_JPEG));
        if (!blob || blob.size >= archivo.size) {
            return archivo;
        }

        return new File([blob], `${archivo.name.replace(/\.[^.]+$/, '')}.jpg`, { type: 'image/jpeg' });
    } catch {
        return archivo;
    }
}

export async function evidenciaDe(archivo: File): Promise<Evidencia> {
    const final = await comprimirImagen(archivo);

    return { archivo: final, url: final.type.startsWith('image/') ? URL.createObjectURL(final) : null };
}
