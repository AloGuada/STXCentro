/**
 * Límites de subida de archivos.
 *
 * Deben mantenerse alineados con la configuración del servidor:
 * - MAX_FILE_SIZE_MB  ↔ php.ini `upload_max_filesize` y la regla `max:10240` de Laravel
 * - MAX_TOTAL_UPLOAD_MB debe ser MENOR que `post_max_size` (64M) para dejar margen a los
 *   demás campos del formulario y al overhead del multipart.
 * - MAX_FILES_PER_REQUEST ↔ php.ini `max_file_uploads`
 */
export const MAX_FILE_SIZE_MB = 10;
export const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

export const MAX_TOTAL_UPLOAD_MB = 60;
export const MAX_TOTAL_UPLOAD_BYTES = MAX_TOTAL_UPLOAD_MB * 1024 * 1024;

export const MAX_FILES_PER_REQUEST = 20;

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
